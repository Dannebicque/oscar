<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ToolbarButton, ToolbarRoot, ToolbarSeparator } from 'reka-ui'
import { useRouter } from 'vue-router'
import { api, session } from '../../api'
import { collisionWith } from '../../domain/claim'
import { clock } from '../../domain/clock'
import { followUpDue, isoDay } from '../../domain/dates'
import type { Attachment, Mail, MoveTarget } from '../../domain/types'
import { useFormat } from '../../composables/useFormat'
import { SHORTCUT_EVENTS, type ShortcutAction } from '../../composables/useShortcuts'
import { useAgendaStore } from '../../stores/agenda'
import { useMailsStore } from '../../stores/mails'
import { useSettingsStore } from '../../stores/settings'
import { useSuivisStore } from '../../stores/suivis'
import { useTasksStore } from '../../stores/tasks'
import { useUiStore } from '../../stores/ui'
import RelanceDialog from '../suivis/RelanceDialog.vue'
import SelectField from '../ui/SelectField.vue'
import Avatar from '../ui/Avatar.vue'
import AiBadge from '../ui/AiBadge.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import OscarSays from '../ui/OscarSays.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ActivityNotes from './ActivityNotes.vue'
import AvailabilityDialog from './AvailabilityDialog.vue'
import AttachmentsMenu from './AttachmentsMenu.vue'
import ConfirmTrashDialog from './ConfirmTrashDialog.vue'
import MoveDialog from './MoveDialog.vue'
import { isCustom, pathOf, prettyPath } from '../../domain/folders'
import ClaimPanel from './ClaimPanel.vue'
import SuiviBanner from './SuiviBanner.vue'
import SuiviPanel from './SuiviPanel.vue'
import ThreadMessages from './ThreadMessages.vue'

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
onMounted(() => { void mails.loadBody(props.mail.id); void mails.loadAttachments(props.mail.id); void mails.loadInvitation(props.mail.id); if (!agenda.events.length) void agenda.load(); if (aiOn.value && !shared.value) void mails.loadHints(props.mail.id); else if (aiOn.value && shared.value) void mails.loadHints(props.mail.id) })
// « r » : répondre (raccourci Gmail), si les raccourcis sont actifs.
const onReplyKey = () => reply(false)
onMounted(() => window.addEventListener(SHORTCUT_EVENTS.reply, onReplyKey))
onBeforeUnmount(() => window.removeEventListener(SHORTCUT_EVENTS.reply, onReplyKey))
// e / # / s : archiver, corbeille, suivre. Mêmes gestes que les boutons, donc même annulation possible.
function onActKey(e: Event) {
  const a = (e as CustomEvent<ShortcutAction>).detail
  if (a === 'follow') { if (!suivi.value && !shared.value) void createSuivi(false) }
  else if (props.mail.folder !== (a === 'archive' ? 'archive' : 'trash')) void relocate(a)
}
onMounted(() => window.addEventListener(SHORTCUT_EVENTS.act, onActKey))
onBeforeUnmount(() => window.removeEventListener(SHORTCUT_EVENTS.act, onActKey))
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

/** Invitation d'agenda (F-25) : lire ne change rien ; répondre est un clic, et la réponse part à l'organisateur. */
const inv = computed(() => props.mail.invitation ?? null)
const calChoice = ref('')
const workCalendars = computed(() => agenda.sources.filter((x) => !x.personal))
const calId = computed(() => calChoice.value || workCalendars.value[0]?.id || '')
const overlaps = computed(() => (inv.value ? agenda.events.filter((e) => e.start < inv.value!.end && e.end > inv.value!.start && e.id !== inv.value!.uid).slice(0, 3) : []))
const invBusy = ref(false)
async function answer(response: 'accepted' | 'tentative' | 'declined') {
  invBusy.value = true
  try {
    const r = await mails.respondInvitation(props.mail.id, response, response === 'declined' ? undefined : calId.value || undefined)
    if (r.added) await agenda.load()
    ui.notify(t(`mail.invitation.sent.${response}`, { organizer: inv.value?.organizerName ?? '' }) + (r.added ? ' ' + t('mail.invitation.added') : ''))
  } catch (e) {
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  } finally { invBusy.value = false }
}
const showMove = ref(false)
async function download(a: Attachment) {
  try { await api.mails.download(props.mail.id, a) } catch { ui.notify(t('mail.downloadFailed'), { tone: 'error' }) }
}
/** Archiver, corbeille ou classement : message parti d'ici, annulation possible depuis la notification. */
const showTrash = ref(false)
function relocate(folder: MoveTarget) {
  showMove.value = false
  // La corbeille demande confirmation (réglable) ; archiver et classer restent immédiats, avec annulation.
  if (folder === 'trash' && settings.s.confirmDelete) { showTrash.value = true; return }
  void doRelocate(folder)
}
function confirmTrash(dontAsk: boolean) {
  showTrash.value = false
  if (dontAsk) settings.s.confirmDelete = false
  void doRelocate('trash')
}
async function doRelocate(folder: MoveTarget) {
  try {
    const undo = await mails.moveTo(props.mail.id, folder)
    ui.notify(t('mail.moved', { subject: props.mail.subject, folder: isCustom(folder) ? prettyPath(pathOf(folder)) : t(`mail.folder.${folder}`) }), { undo: undo ? async () => { await undo(); ui.notify(t('mail.movedBack')) } : undefined })
    // La liste propose le message suivant (traitement à la chaîne au clavier) ; sans liste, retour aux mails.
    const step = { handled: false, id: props.mail.id }
    window.dispatchEvent(new CustomEvent(SHORTCUT_EVENTS.advance, { detail: step }))
    if (!step.handled) router.push({ name: 'mails' })
  } catch (e) {
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  }
}
const forward = () => router.push({ name: 'compose', query: { forward: props.mail.id } })

function reply(ai = false) {
  if (collision.value) { showCollision.value = true; return }
  router.push({ name: 'compose', query: { reply: props.mail.id, ...(ai === true ? { ai: '1' } : {}) } })
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
  const due = isoDay(followUpDue(clock.now(), settings.s.relanceHours, settings.s.businessDays))
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
/** Créneaux choisis : Oscar prépare le brouillon dans l'écran de rédaction, rien ne part ici. */
function prepareAvailability(slots: string[]) {
  showAvail.value = false
  router.push({ name: 'compose', query: { reply: props.mail.id, ai: '1', slots: slots.join(',') } })
}
const canDraft = computed(() => aiOn.value && props.mail.folder === 'inbox' && props.mail.from.name !== 'Oscar')
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
        <ToolbarButton v-if="canDraft" as-child><BaseButton variant="ai" icon="sparkles" :label="t('mail.ai.suggestReply')" @click="reply(true)" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="replyAll" icon-only :title="t('mail.replyAll')" :label="t('mail.replyAll')" @click="reply" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="forward" icon-only :title="t('mail.forward')" :label="t('mail.forward')" @click="forward" /></ToolbarButton>
        <ToolbarSeparator class="sep" />
        <ToolbarButton as-child><BaseButton icon="archive" icon-only :title="t('mail.archive')" :label="t('mail.archive')" @click="relocate('archive')" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="folder" icon-only :title="t('mail.move')" :label="t('mail.move')" @click="showMove = true" /></ToolbarButton>
        <ToolbarButton as-child><BaseButton icon="trash" icon-only :title="t('mail.delete')" :label="t('mail.delete')" @click="relocate('trash')" /></ToolbarButton>
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

        <ThreadMessages :mail="mail" />

        <section v-if="inv" class="invite" :aria-label="t('mail.invitation.title')">
          <h3 class="ih"><BaseIcon name="calendar" :size="18" />{{ t('mail.invitation.title') }}</h3>
          <p class="it">{{ inv.title }}</p>
          <dl class="idl">
            <dt>{{ t('agenda.when') }}</dt><dd>{{ fmt.dateLong(inv.start) }}, {{ fmt.time(inv.start) }} – {{ fmt.time(inv.end) }}</dd>
            <template v-if="inv.location"><dt>{{ t('agenda.evPlace') }}</dt><dd>{{ inv.location }}</dd></template>
            <dt>{{ t('mail.invitation.organizer') }}</dt><dd>{{ inv.organizerName }}</dd>
          </dl>
          <p v-for="o in overlaps" :key="o.id" class="warn" role="status"><BaseIcon name="alert" :size="16" /> {{ t('mail.invitation.overlap', { title: o.title, time: fmt.time(o.start) }) }}</p>
          <p v-if="mail.invitationResponse" class="done" role="status"><BaseIcon name="checkCircle" :size="16" /> {{ t(`mail.invitation.state.${mail.invitationResponse}`) }}</p>
          <div class="iact">
            <SelectField v-if="workCalendars.length > 1" id="inv-cal" :model-value="calId" :label="t('mail.invitation.calendar')" :options="workCalendars.map((c) => ({ value: c.id, label: c.label }))" @update:model-value="(v: string) => (calChoice = v)" />
            <div class="btns">
              <BaseButton variant="primary" icon="check" :disabled="invBusy" :label="t('mail.invitation.accept')" @click="answer('accepted')" />
              <BaseButton :disabled="invBusy" :label="t('mail.invitation.maybe')" @click="answer('tentative')" />
              <BaseButton variant="danger" icon="x" :disabled="invBusy" :label="t('mail.invitation.decline')" @click="answer('declined')" />
            </div>
          </div>
          <p class="hint">{{ t('mail.invitation.help') }}</p>
        </section>

        <section v-if="aiOn && hints.summary" class="summary" :aria-label="t('mail.ai.summary')">
          <p class="sh"><BaseIcon name="sparkles" :size="16" /><strong>{{ t('mail.ai.summary') }}</strong><AiBadge /></p>
          <p>{{ hints.summary }}</p>
        </section>

        <div class="body">
          <p v-for="(p, i) in mail.paragraphs" :key="i">{{ p }}</p>
          <p v-if="bodyLoading" class="muted" role="status">{{ t('mail.bodyLoading') }}</p>
          <p v-else-if="bodyFailed" class="error-text" role="alert">{{ t('mail.bodyError') }} <BaseButton :label="t('common.retry')" @click="mails.loadBody(mail.id)" /></p>
          <p v-else-if="mail.paragraphs.length === 0" class="muted">{{ t('mail.bodyEmpty') }}</p>
          <AttachmentsMenu v-if="mail.attachments.length" :items="mail.attachments" @download="download" />
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

  <ConfirmTrashDialog :open="showTrash" :count="1" :with-suivi="suivi ? 1 : 0" @confirm="confirmTrash" @close="showTrash = false" />
  <MoveDialog :open="showMove" :current="mail.folder" @close="showMove = false" @pick="relocate" />

  <RelanceDialog v-if="suivi" :open="showDraft" :queue="[suivi]" single @close="showDraft = false" />
  <AvailabilityDialog :open="showAvail" :mail="mail" @close="showAvail = false" @pick="prepareAvailability" />

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
.toolbar { display: flex; flex-wrap: wrap; row-gap: var(--space-1); gap: var(--space-1); align-items: center; padding-bottom: var(--space-3); border-bottom: var(--bw) solid var(--c-border); }
.toolbar :deep(.btn) { min-height: 40px; }
.sep { width: 1px; align-self: stretch; background: var(--c-border); margin: 4px var(--space-1); }
.body { display: grid; gap: var(--space-3); padding: var(--space-4) var(--space-5); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); }
.invite { display: grid; gap: var(--space-2); padding: var(--space-3) var(--space-4); border-radius: var(--radius-lg); background: var(--c-surface); border: var(--bw-card) solid var(--c-primary); }
.ih { display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-md); color: var(--c-accent); }
.it { font-size: var(--fs-lg); font-weight: 800; margin: 0; }
.idl { display: grid; grid-template-columns: max-content 1fr; gap: var(--space-1) var(--space-4); margin: 0; }
.idl dt { color: var(--c-text-muted); } .idl dd { margin: 0; font-weight: 600; }
.invite .warn, .invite .done { display: flex; gap: var(--space-2); align-items: center; margin: 0; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); color: var(--c-text); }
.invite .warn { background: var(--c-warn-soft); } .invite .done { background: var(--c-success-soft); }
.iact { display: grid; gap: var(--space-2); } .btns { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.summary { display: grid; gap: var(--space-1); padding: var(--space-3) var(--space-4); border-radius: var(--radius-lg); background: var(--c-ai-soft); border: var(--bw) solid var(--c-ai); color: var(--c-text); }
.sh { display: flex; align-items: center; gap: var(--space-2); color: var(--c-ai); }
.moves { border: 0; padding: 0; margin: 0; display: grid; gap: 2px; }
.moves legend { font-weight: 700; margin-bottom: var(--space-2); }
.check { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); }
.detected { display: grid; gap: var(--space-2); }
.kick { font-size: var(--fs-sm); font-weight: 700; color: var(--c-ai); }
.back { display: none; }
/* Selon la largeur DU VOLET de lecture (pas de la fenêtre) : volet de suivi sous le message, boutons réduits à leur icône. */
@container reading (max-width: 980px) { .reader { display: block; overflow-y: auto; } .main { overflow: visible; } .reader > :deep(.side) { width: auto; margin: 0 var(--space-5) var(--space-6); border: var(--bw-card) solid var(--c-border); border-top: 4px solid var(--c-accent); border-radius: var(--radius-lg); } .reader > :deep(.side) .top { border-bottom: 0; padding-bottom: 0; } }
@container reading (max-width: 760px) {
  .toolbar :deep(.btn > span) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .toolbar :deep(.btn) { padding: 0; }
}
@media (max-width: 720px) { .back { display: inline-flex; } .main { padding: var(--space-3); } .reader > :deep(.side) { margin: 0 var(--space-3) var(--space-4); } }
</style>
