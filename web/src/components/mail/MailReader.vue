<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ME } from '../../api'
import { collisionWith } from '../../domain/claim'
import { clock } from '../../domain/clock'
import { isoDay, addDays } from '../../domain/dates'
import type { Mail } from '../../domain/types'
import { useFormat } from '../../composables/useFormat'
import { useAgendaStore } from '../../stores/agenda'
import { useMailsStore } from '../../stores/mails'
import { useSettingsStore } from '../../stores/settings'
import { useSuivisStore } from '../../stores/suivis'
import { useTasksStore } from '../../stores/tasks'
import { useUiStore } from '../../stores/ui'
import RelanceDialog from '../suivis/RelanceDialog.vue'
import AiBadge from '../ui/AiBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ActivityNotes from './ActivityNotes.vue'
import AvailabilityDialog from './AvailabilityDialog.vue'
import ClaimPanel from './ClaimPanel.vue'
import SuiviBanner from './SuiviBanner.vue'
import SuiviPanel from './SuiviPanel.vue'

const props = defineProps<{ mail: Mail }>()
const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const suivis = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const settings = useSettingsStore()
const ui = useUiStore()

const panel = ref(true)
const showDraft = ref(false)
const showAvail = ref(false)
const showCollision = ref(false)
const dismissed = ref<Set<string>>(new Set())

const suivi = computed(() => (props.mail.suivi ? suivis.byId(props.mail.suivi) : undefined))
const shared = computed(() => props.mail.claim !== null)
const collision = computed(() => (shared.value ? collisionWith(props.mail, ME) : null))
const hints = computed(() => props.mail.hints)
const showSuggestFollow = computed(
  () => !suivi.value && !shared.value && props.mail.folder === 'inbox' && settings.s.aiMode !== 'off' && settings.s.aiSuggestFollow && !dismissed.value.has('follow') && !!(hints.value.task || hints.value.availability),
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
  await fn()
  ui.notify(msg)
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
    <header class="head">
      <div class="title">
        <h2>{{ mail.subject }}</h2>
        <p class="muted">{{ t('mail.from', { who: `${mail.from.name} <${mail.from.address}>` }) }} · <time :datetime="mail.at">{{ fmt.dateTime(mail.at) }}</time></p>
      </div>
      <BaseButton v-if="suivi" variant="ghost" icon="sidebar" icon-only :label="t('mail.togglePanel')" :expanded="panel" @click="panel = !panel" />
    </header>

    <div class="toolbar" role="toolbar" :aria-label="t('mail.reading')">
      <BaseButton icon="reply" :label="t('mail.reply')" @click="reply" />
      <BaseButton icon="replyAll" :label="t('mail.replyAll')" @click="reply" />
      <BaseButton icon="forward" :label="t('mail.forward')" @click="stub('forward')" />
      <BaseButton icon="archive" :label="t('mail.archive')" @click="stub('archive')" />
      <BaseButton icon="folder" :label="t('mail.move')" @click="stub('move')" />
      <BaseButton icon="trash" :label="t('mail.delete')" @click="stub('delete')" />
      <BaseButton v-if="!suivi && !shared" icon="follow" :label="t('mail.createSuivi')" @click="createSuivi(false)" />
    </div>

    <ClaimPanel
      v-if="shared" :mail="mail" @take="act(() => mails.take(mail.id), t('mail.claim.takeDone', { subject: mail.subject }))"
      @release="act(() => mails.release(mail.id), t('mail.claim.released'))" @handle="act(() => mails.handle(mail.id), t('mail.claim.handled2'))"
      @reopen="act(() => mails.reopen(mail.id), t('mail.claim.reopened'))" @take-over="act(() => mails.takeOver(mail.id), t('mail.claim.takeOverDone', { who: mail.claim?.by ?? '' }))" @reply="reply"
    />
    <SuiviBanner
      v-else-if="suivi" :suivi="suivi" @draft="showDraft = true" @stop="suivis.stopRelance(suivi.id)" @close="closeSuivi"
      @keep-open="suivis.keepOpen(suivi.id)" @keep-waiting="suivis.keepWaiting(suivi.id)" @reopen="suivis.setStatus(suivi.id, 'inProgress')"
    />

    <section v-if="showSuggestFollow" class="ai-card" aria-labelledby="sf-h">
      <AiBadge />
      <div><h3 id="sf-h">{{ t('mail.ai.suggestFollow') }}</h3><p>{{ t('mail.ai.suggestFollowText') }}</p></div>
      <div class="row"><BaseButton variant="primary" :label="t('mail.createSuivi')" @click="createSuivi(true)" /><BaseButton :label="t('common.ignore')" @click="ignore('follow')" /></div>
    </section>

    <div class="cols" :class="{ withPanel: suivi && panel }">
      <div class="content">
        <div class="body">
          <p v-for="(p, i) in mail.paragraphs" :key="i">{{ p }}</p>
          <p v-if="mail.attachments.length" class="attach">
            <span v-for="a in mail.attachments" :key="a.name" class="file">{{ a.name }} · {{ a.size }}</span>
          </p>
        </div>

        <section v-if="aiOn && hints.availability && !dismissed.has('avail')" class="ai-card" aria-labelledby="av-h">
          <AiBadge />
          <div>
            <h3 id="av-h">{{ t('mail.ai.availTitle') }}</h3>
            <p>{{ t('mail.ai.availText', { n: hints.availability.slots.length, date: fmt.date(hints.availability.slots[0].start) }) }}</p>
          </div>
          <BaseButton variant="primary" icon="sparkles" :label="t('mail.ai.availAction')" @click="showAvail = true" />
        </section>

        <section v-if="aiOn && settings.s.aiSuggestTasks && (hints.event || hints.task)" class="detected" aria-labelledby="det-h">
          <h3 id="det-h">{{ t('mail.ai.detected') }}</h3>
          <div v-if="hints.event && !dismissed.has('event')" class="ai-card small">
            <AiBadge />
            <div>
              <p class="kick">{{ t('mail.ai.eventKicker', { quote: hints.event.quote }) }}</p>
              <p><strong>{{ hints.event.title }}</strong></p>
              <p class="muted">{{ t('mail.ai.eventWhen', { date: fmt.dateLong(hints.event.start), start: fmt.time(hints.event.start), end: fmt.time(hints.event.end), cal: 'CalDAV' }) }}</p>
            </div>
            <div class="row"><BaseButton variant="primary" :label="t('mail.ai.eventAdd')" @click="addEvent" /><BaseButton :label="t('common.ignore')" @click="ignore('event')" /></div>
          </div>
          <div v-if="hints.task && !dismissed.has('task')" class="ai-card small">
            <AiBadge />
            <div>
              <p class="kick">{{ t('mail.ai.taskKicker', { quote: hints.task.quote }) }}</p>
              <p><strong>{{ hints.task.title }}</strong></p>
              <p class="muted">{{ t('mail.ai.taskDue', { date: fmt.dateLong(hints.task.dueAt) }) }}</p>
            </div>
            <div class="row"><BaseButton variant="primary" :label="t('mail.ai.taskAdd')" @click="addTask" /><BaseButton :label="t('common.ignore')" @click="ignore('task')" /></div>
          </div>
        </section>

        <ActivityNotes v-if="shared" :mail="mail" @add-note="(x) => act(() => mails.addNote(mail.id, x), t('mail.notes.added'))" />
      </div>
      <SuiviPanel v-if="suivi && panel" :suivi="suivi" />
    </div>
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
.reader { display: grid; gap: var(--space-3); align-content: start; min-width: 0; }
.head { display: flex; justify-content: space-between; gap: var(--space-3); align-items: flex-start; }
h2 { font-size: var(--fs-lg); }
.toolbar { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.cols { display: grid; gap: var(--space-4); }
.cols.withPanel { grid-template-columns: minmax(0, 1fr) 260px; }
.content { display: grid; gap: var(--space-4); min-width: 0; align-content: start; }
.body { display: grid; gap: var(--space-3); padding: var(--space-4); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); max-width: 72ch; }
.file { display: inline-block; padding: 2px 10px; border: 1px solid var(--c-border); border-radius: var(--radius-pill); font-size: var(--fs-sm); }
.ai-card { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; padding: var(--space-3) var(--space-4); border: 1px solid var(--c-ai); background: var(--c-ai-soft); border-radius: var(--radius-md); }
.ai-card > div:not(.row) { flex: 1; min-width: 200px; }
.ai-card h3 { font-size: var(--fs-md); }
.kick { font-size: var(--fs-sm); font-weight: 700; color: var(--c-ai); }
.row { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.detected { display: grid; gap: var(--space-2); }
.detected > h3 { font-size: var(--fs-md); }
@media (max-width: 1100px) { .cols.withPanel { grid-template-columns: minmax(0, 1fr); } }
</style>
