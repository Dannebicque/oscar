<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ToolbarButton, ToolbarRoot, ToolbarSeparator } from 'reka-ui'
import { useRouter } from 'vue-router'
import { session } from '../../api'
import { collisionWith } from '../../domain/claim'
import { clock } from '../../domain/clock'
import { isoDay, addDays } from '../../domain/dates'
import type { Mail } from '../../domain/types'
import { useFormat } from '../../composables/useFormat'
import { SHORTCUT_EVENTS } from '../../composables/useShortcuts'
import { useAgendaStore } from '../../stores/agenda'
import { useMailsStore } from '../../stores/mails'
import { useSettingsStore } from '../../stores/settings'
import { useSuivisStore } from '../../stores/suivis'
import { useTasksStore } from '../../stores/tasks'
import { useUiStore } from '../../stores/ui'
import RelanceDialog from '../suivis/RelanceDialog.vue'
import Avatar from '../ui/Avatar.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import OscarSays from '../ui/OscarSays.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ActivityNotes from './ActivityNotes.vue'
import AvailabilityDialog from './AvailabilityDialog.vue'
import ClaimPanel from './ClaimPanel.vue'
import SuiviBanner from './SuiviBanner.vue'
import SuiviPanel from './SuiviPanel.vue'

const props = defineProps<{ mail: Mail; showBack?: boolean }>()
const emit = defineEmits<{ back: [] }>()
const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const suivis = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const settings = useSettingsStore()
const ui = useUiStore()

/** Volet de suivi : ouvert d'emblée sur grand écran, à ouvrir sur les autres (il passe alors sous le message). */
const panel = ref(typeof matchMedia === 'function' ? matchMedia('(min-width: 1500px)').matches : true)
const showDraft = ref(false)
const showAvail = ref(false)
const showCollision = ref(false)
const dismissed = ref<Set<string>>(new Set())
onMounted(() => { void mails.loadBody(props.mail.id); if (aiOn.value && !shared.value) void mails.loadHints(props.mail.id); else if (aiOn.value && shared.value) void mails.loadHints(props.mail.id) })
// « r » : répondre (raccourci Gmail), si les raccourcis sont actifs.
const onReplyKey = () => reply()
onMounted(() => window.addEventListener(SHORTCUT_EVENTS.reply, onReplyKey))
onBeforeUnmount(() => window.removeEventListener(SHORTCUT_EVENTS.reply, onReplyKey))
const bodyLoading = computed(() => mails.loadingBody.has(props.mail.id))
const bodyFailed = computed(() => mails.bodyError.has(props.mail.id))

const suivi = computed(() => (props.mail.suivi ? suivis.byId(props.mail.suivi) : undefined))
const shared = computed(() => props.mail.claim !== null)
const collision = computed(() => (shared.value ? collisionWith(props.mail, session.name) : null))
const hints = computed(() => props.mail.hints)
const showSuggestFollow = computed(
  () => !suivi.value && !shared.value && props.mail.folder === 'inbox' && settings.s.aiMode !== 'off' && settings.s.aiSuggestFollow && !dismissed.value.has('follow') && (hints.value.suggestFollow ?? !!(hints.value.task || hints.value.availability)),
)
const aiOn = computed(() => settings.s.aiMode !== 'off')

const stub = (key: string) => ui.notify(t('mail.actionDone', { action: t(`mail.${key}`), subject: props.mail.subject }), { tone: 'info' })

function reply() {
  if (collision.value) { showCollision.value = true; return }
  router.push({ name: 'compose', query: { reply: props.mail.id } })
}
async function takeOverAndReply() {
  await mails.takeOver(props.mail.id)
  ui.notify(t('mail.claim.takeOverDone', { who: props.mail.claim?.by ?? '' }))
  showCollision.value = false
  router.push({ name: 'compose', query: { reply: props.mail.id } })
}
async function act(fn: () => Promise<unknown>, msg: string) {
  try {
    await fn()
    ui.notify(msg)
  } catch (e) {
    // « Claire s'en occupe » : l'API prévient au lieu de laisser un doublon ; l'état affiché est déjà rafraîchi.
    ui.notify(mails.conflictMessage(e) ?? t('common.actionFailed'), { tone: 'error' })
  }
}
async function createSuivi(ai = false) {
  const due = isoDay(addDays(clock.now(), Math.ceil(settings.s.relanceHours / 24) + 1))
  await mails.follow(props.mail.id, { dueAt: due, suggestedByAi: ai })
  ui.notify(t('mail.suiviCreated', { subject: props.mail.subject }))
}
async function closeSuivi() {
  if (!suivi.value) return
  const undo = await suivis.close(suivi.value.id)
  ui.notify(t('suivis.closed', { subject: suivi.value.subject }), { undo: undo && (() => undo()) })
}
async function addEvent() {
  const e = hints.value.event!
  await agenda.add({ calendar: 'iut', title: e.title, start: e.start, end: e.end, location: null })
  dismissed.value.add('event')
  ui.notify(t('mail.ai.eventDone', { title: e.title }))
}
async function addTask() {
  const h = hints.value.task!
  await tasks.add({ title: h.title, dueAt: h.dueAt, origin: 'mail', source: props.mail.subject })
  dismissed.value.add('task')
  ui.notify(t('mail.ai.taskDone', { title: h.title }))
}
function ignore(key: string) {
  dismissed.value.add(key)
  ui.notify(t('mail.ai.ignored'), { tone: 'info' })
}
function sendAvailability() {
  showAvail.value = false
  ui.notify(t('compose.sent', { to: props.mail.from.name }))
}
</script>

<template>
  <article class="reader" :aria-label="t('mail.reading')">
    <div class="main">
      <header class="head">
        <button v-if="showBack" type="button" class="btn icon ghost back" :aria-label="t('common.back')" @click="emit('back')"><BaseIcon name="chevronLeft" /></button>
        <Avatar :name="mail.from.name" :size="42" />
        <div class="title">
          <h2>{{ mail.subject }}</h2>
          <p class="muted">{{ mail.from.name }} <span class="addr">&lt;{{ mail.from.address }}&gt;</span> · <time :datetime="mail.at">{{ fmt.dateTime(mail.at) }}</time></p>
        </div>
        <BaseButton v-if="suivi" variant="ghost" icon="sidebar" icon-only :label="t('mail.togglePanel')" :expanded="panel" @click="panel = !panel" />
      </header>

      <ToolbarRoot class="toolbar" :aria-label="t('mail.reading')">
        <ToolbarButton as-child><BaseButton icon="reply" :label="t('mail.reply')" @click="reply" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="replyAll" :label="t('mail.replyAll')" @click="reply" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="forward" :label="t('mail.forward')" @click="stub('forward')" /></ToolbarButton>
        <ToolbarSeparator class="sep" />
        <ToolbarButton as-child><BaseButton icon="archive" :label="t('mail.archive')" @click="stub('archive')" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="folder" :label="t('mail.move')" @click="stub('move')" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="trash" :label="t('mail.delete')" @click="stub('delete')" /></ToolbarButton>
        <ToolbarButton v-if="!suivi && !shared" as-child><BaseButton variant="primary" icon="follow" :label="t('mail.createSuivi')" @click="createSuivi(false)" /></ToolbarButton>
      </ToolbarRoot>

      <div class="stack">
        <ClaimPanel
          v-if="shared" :mail="mail" @take="act(() => mails.take(mail.id), t('mail.claim.takeDone', { subject: mail.subject }))"
          @release="act(() => mails.release(mail.id), t('mail.claim.released'))" @handle="act(() => mails.handle(mail.id), t('mail.claim.handled2'))"
          @reopen="act(() => mails.reopen(mail.id), t('mail.claim.reopened'))" @take-over="act(() => mails.takeOver(mail.id), t('mail.claim.takeOverDone', { who: mail.claim?.by ?? '' }))" @reply="reply"
        />
        <SuiviBanner
          v-else-if="suivi" :suivi="suivi" @draft="showDraft = true" @stop="suivis.stopRelance(suivi.id)" @close="closeSuivi"
          @keep-open="suivis.keepOpen(suivi.id)" @keep-waiting="suivis.keepWaiting(suivi.id)" @reopen="suivis.setStatus(suivi.id, 'inProgress')"
        />

        <OscarSays v-if="showSuggestFollow" situation="suggestFollow" :level="3" suggestion>
          <template #actions><BaseButton variant="primary" :label="t('mail.createSuivi')" @click="createSuivi(true)" /><BaseButton :label="t('common.ignore')" @click="ignore('follow')" /></template>
        </OscarSays>

        <div class="body">
          <p v-for="(p, i) in mail.paragraphs" :key="i">{{ p }}</p>
          <p v-if="bodyLoading" class="muted" role="status">{{ t('mail.bodyLoading') }}</p>
          <p v-else-if="bodyFailed" class="error-text" role="alert">{{ t('mail.bodyError') }} <BaseButton :label="t('common.retry')" @click="mails.loadBody(mail.id)" /></p>
          <p v-else-if="mail.paragraphs.length === 0" class="muted">{{ t('mail.bodyEmpty') }}</p>
          <p v-if="mail.attachments.length" class="cluster"><span v-for="a in mail.attachments" :key="a.name" class="chip"><BaseIcon name="paperclip" :size="14" />{{ a.name }} · {{ a.size }}</span></p>
        </div>

        <OscarSays v-if="aiOn && hints.availability && !dismissed.has('avail')" situation="availability" :params="{ who: mail.from.name.split(' ')[0], n: hints.availability.slots.length }" :level="3" suggestion>
          <template #actions><BaseButton variant="primary" icon="sparkles" :label="t('mail.ai.availAction')" @click="showAvail = true" /></template>
        </OscarSays>

        <section v-if="aiOn && settings.s.aiSuggestTasks && (hints.event || hints.task)" class="detected" aria-labelledby="det-h">
          <h3 id="det-h">{{ t('mail.ai.detected') }}</h3>
          <OscarSays v-if="hints.event && !dismissed.has('event')" situation="suggestEvent" :level="3" suggestion>
            <template #more>
              <p class="item"><strong>{{ hints.event.title }}</strong></p>
              <p class="kick">{{ t('mail.ai.eventKicker', { quote: hints.event.quote }) }}</p>
              <p class="muted">{{ t('mail.ai.eventWhen', { date: fmt.dateLong(hints.event.start), start: fmt.time(hints.event.start), end: fmt.time(hints.event.end), cal: 'CalDAV' }) }}</p>
            </template>
            <template #actions><BaseButton variant="primary" :label="t('mail.ai.eventAdd')" @click="addEvent" /><BaseButton :label="t('common.ignore')" @click="ignore('event')" /></template>
          </OscarSays>
          <OscarSays v-if="hints.task && !dismissed.has('task')" situation="suggestTask" :level="3" suggestion>
            <template #more>
              <p class="item"><strong>{{ hints.task.title }}</strong></p>
              <p class="kick">{{ t('mail.ai.taskKicker', { quote: hints.task.quote }) }}</p>
              <p class="muted">{{ t('mail.ai.taskDue', { date: fmt.dateLong(hints.task.dueAt) }) }}</p>
            </template>
            <template #actions><BaseButton variant="primary" :label="t('mail.ai.taskAdd')" @click="addTask" /><BaseButton :label="t('common.ignore')" @click="ignore('task')" /></template>
          </OscarSays>
        </section>

        <ActivityNotes v-if="shared" :mail="mail" @add-note="(x) => act(() => mails.addNote(mail.id, x), t('mail.notes.added'))" />
      </div>
    </div>
    <SuiviPanel v-if="suivi && panel" :suivi="suivi" />
  </article>

  <RelanceDialog v-if="suivi" :open="showDraft" :queue="[suivi]" single @close="showDraft = false" />
  <AvailabilityDialog :open="showAvail" :mail="mail" @close="showAvail = false" @send="sendAvailability" />

  <BaseDialog :open="showCollision" :title="t('mail.claim.warnTitle', { who: collision ?? '' })" @close="showCollision = false">
    <p>{{ t('mail.claim.warnText', { who: collision ?? '' }) }}</p>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="showCollision = false" />
      <BaseButton :label="t('mail.claim.writeAnyway')" @click="router.push({ name: 'compose', query: { reply: mail.id } })" />
      <BaseButton variant="primary" :label="t('mail.claim.takeOverAndReply')" @click="takeOverAndReply" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.reader { display: grid; grid-template-columns: minmax(0, 1fr) auto; min-height: 0; height: 100%; background: var(--c-bg); }
.reader > :deep(.side) { width: 280px; }
.main { min-width: 0; min-height: 0; overflow-y: auto; display: grid; align-content: start; gap: var(--space-3); padding: var(--space-4) var(--space-5) var(--space-6); }
.head { display: flex; gap: var(--space-3); align-items: center; }
.title { flex: 1; min-width: 0; }
.title h2 { overflow-wrap: anywhere; }
.addr { overflow-wrap: anywhere; }
.toolbar { display: flex; flex-wrap: wrap; gap: var(--space-1); align-items: center; padding-bottom: var(--space-3); border-bottom: var(--bw) solid var(--c-border); }
.toolbar :deep(.btn) { min-height: 40px; }
.sep { width: 1px; align-self: stretch; background: var(--c-border); margin: 4px var(--space-1); }
.body { display: grid; gap: var(--space-3); padding: var(--space-4) var(--space-5); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); max-width: 76ch; }
.detected { display: grid; gap: var(--space-2); }
.kick { font-size: var(--fs-sm); font-weight: 700; color: var(--c-ai); }
.back { display: none; }
/* Selon la largeur DU VOLET de lecture (pas de la fenêtre) : volet de suivi sous le message, boutons réduits à leur icône. */
@container reading (max-width: 980px) { .reader { display: block; overflow-y: auto; } .main { overflow: visible; } .reader > :deep(.side) { width: auto; border-left: 0; border-top: var(--bw) solid var(--c-border); } }
@container reading (max-width: 760px) {
  .toolbar :deep(.btn > span) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .toolbar :deep(.btn) { padding: 0; }
}
@media (max-width: 720px) { .back { display: inline-flex; } .main { padding: var(--space-3); } }
</style>
