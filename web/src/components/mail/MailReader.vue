<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ToolbarButton, ToolbarRoot, ToolbarSeparator } from 'reka-ui'
import { useRouter } from 'vue-router'
import { api, session } from '../../api'
import { collisionWith } from '../../domain/claim'
import { clock } from '../../domain/clock'
import { followUpDue, isoDay } from '../../domain/dates'
import type { Attachment, Mail, MoveTarget, InvitationResponse } from '../../domain/types'
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
import ToneChip from '../ui/ToneChip.vue'
import SuggestBadge from '../ui/SuggestBadge.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import OscarSays from '../ui/OscarSays.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ActivityNotes from './ActivityNotes.vue'
import AvailabilityDialog from './AvailabilityDialog.vue'
import AttachmentsMenu from './AttachmentsMenu.vue'
import HoldsPanel from '../agenda/HoldsPanel.vue'
import { overlapping } from '../../domain/holds'
import { sameId } from '../../domain/mailIds'
import Callout from '../ui/Callout.vue'
import ConfirmTrashDialog from './ConfirmTrashDialog.vue'
import MoveDialog from './MoveDialog.vue'
import InvitationReplyDialog from './InvitationReplyDialog.vue'
import MailBodyText from './MailBodyText.vue'
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
onMounted(() => { void mails.loadBody(props.mail.id); void mails.loadAttachments(props.mail.id); void mails.loadInvitation(props.mail.id); if (!agenda.events.length) void agenda.load(); else void agenda.loadHolds(); if (!tasks.items.length) void tasks.load(); if (aiOn.value) void mails.loadHints(props.mail.id) })
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
/** La mention « IA » suit ce que le modèle a réellement donné, champ par champ. */
const byModel = (field: string) => hints.value.fromModel?.includes(field) === true

/** Invitation d'agenda (F-25) : lire ne change rien ; répondre est un clic, et la réponse part à l'organisateur. */
const inv = computed(() => props.mail.invitation ?? null)
const calChoice = ref('')
const workCalendars = computed(() => agenda.sources.filter((x) => !x.personal))
const calId = computed(() => calChoice.value || workCalendars.value[0]?.id || '')
/** L'invitation jointe fait foi : pas de rendez-vous « repéré » à côté d'elle, qui ferait doublon ou la contredirait. */
/** Déjà dans l'agenda ou dans les tâches : ne pas reproposer ce qui est fait, même après un rechargement. */
const detectedEvent = computed(() => {
  const e = hints.value.event
  return inv.value || !e || agenda.events.some((x) => x.title === e.title && Date.parse(x.start) === Date.parse(e.start)) ? undefined : e
})
const detectedTask = computed(() => {
  const h = hints.value.task
  return !h || tasks.items.some((x) => x.title === h.title && x.source === props.mail.subject) ? undefined : h
})
/** Une fois acceptée, l'invitation est dans l'agenda : elle ne se chevauche pas elle-même. */
const isThisInvitation = (e: { title: string; start: string; end: string }) => e.title === inv.value!.title && Date.parse(e.start) === Date.parse(inv.value!.start) && Date.parse(e.end) === Date.parse(inv.value!.end)
const overlaps = computed(() => (inv.value ? agenda.events.filter((e) => overlapping(e, inv.value!) && !isThisInvitation(e)).slice(0, 3) : []))
const invBusy = ref(false)
/** Créneaux en provisoire (F-74) liés à ce message ou à son suivi, et ceux qu'une invitation chevauche. */
const myHolds = computed(() => agenda.holds.filter((h) => [h.mailId, h.repliedToMailId].some((x) => sameId(x, mails.refOf(props.mail))) || (!!props.mail.suivi && h.suiviId === props.mail.suivi)))
const heldClash = computed(() => (inv.value ? agenda.holds.find((h) => overlapping(h, inv.value!)) ?? null : null))
/** Ce qui occupe déjà la plage : rendez-vous et créneaux proposés. Un simple calcul, sur ton agenda, pour toi seul. */
const conflicts = computed(() => [
  ...overlaps.value.map((o) => t('mail.invitation.overlap', { title: o.title, time: fmt.time(o.start) })),
  ...(heldClash.value ? [t('holds.clashHold', { who: heldClash.value.contact })] : []),
])
const addedWithoutReply = computed(() => !!inv.value && !props.mail.invitationResponse && agenda.events.some(isThisInvitation))
/** Une fois répondu, les boutons laissent la place à « Modifier ma réponse » : on ne propose pas de refaire ce qui est fait. */
const changing = ref(false)
const choices = computed(() => !props.mail.invitationResponse || changing.value)
const offered = (r: InvitationResponse) => props.mail.invitationResponse !== r
/** Accepter, peut-être, refuser : le message est d'abord montré ; rien ne part ici. */
const replying = ref<InvitationResponse | null>(null)
async function prepare(response: InvitationResponse) {
  // L'agenda doit être connu pour dire les chevauchements et savoir où inscrire.
  if (!agenda.sources.length) await agenda.load()
  replying.value = response
}
async function answer(response: InvitationResponse, message: string | undefined, send = true) {
  invBusy.value = true
  try {
    const wanted = response === 'declined' ? undefined : calId.value || undefined
    const r = await mails.respondInvitation(props.mail.id, response, wanted, { message, send })
    replying.value = null
    changing.value = false
    if (r.added) await agenda.load()
    if (!send) { ui.notify(t('mail.invitation.addedOnly')); return }
    const inCalendar = r.inCalendar ?? r.added
    const sent = t(`mail.invitation.sent.${response}`, { organizer: inv.value?.organizerName ?? '' })
    if (response !== 'declined' && !inCalendar) ui.notify(`${sent} ${t('mail.invitation.notAdded')}`, { tone: 'error' })
    else ui.notify(sent + (r.added ? ' ' + t('mail.invitation.added') : ''))
  } catch (e) {
    // La fenêtre reste ouverte : le texte relu n'est pas perdu.
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  } finally { invBusy.value = false }
}
const showMove = ref(false)
async function download(a: Attachment) {
  try { await api.mails.download(props.mail.id, a) } catch { ui.notify(t('mail.downloadFailed'), { tone: 'error' }) }
}
/** Archiver, corbeille ou classement : message parti d'ici, annulation possible depuis la notification. */
const onVacation = computed(() => settings.vacationActive(clock.now()) && props.mail.folder === 'inbox' && !shared.value)
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
/** Un rendez-vous repéré dans le texte s'ajoute à l'agenda de travail ; personne n'est prévenu (une invitation, elle, se répond). */
async function addEvent() {
  const e = hints.value.event!
  if (!calId.value) { ui.notify(t('agenda.noSource'), { tone: 'error' }); return }
  try {
    await agenda.add({ calendar: calId.value, title: e.title, start: e.start, end: e.end, location: null })
  } catch {
    ui.notify(t('agenda.createFailed'), { tone: 'error' })
    return
  }
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
        <Callout v-if="onVacation" tone="info" icon="clock" :level="3" :title="t('vacation.replyTitle')">
          <p>{{ t('vacation.replyText') }}</p>
          <template #actions><BaseButton icon="reply" :label="t('vacation.reply')" @click="router.push({ name: 'compose', query: { reply: mail.id, absence: '1' } })" /></template>
        </Callout>
        <SuiviBanner
          v-else-if="suivi" :suivi="suivi" @draft="showDraft = true" @stop="suivis.stopRelance(suivi.id)" @close="closeSuivi"
          @keep-open="suivis.keepOpen(suivi.id)" @keep-waiting="suivis.keepWaiting(suivi.id)" @reopen="suivis.setStatus(suivi.id, 'inProgress')"
        />

        <OscarSays v-if="showSuggestFollow" situation="suggestFollow" :level="3" suggestion :ai="byModel('suggestFollow')">
          <template #actions><BaseButton variant="primary" :label="t('mail.createSuivi')" @click="createSuivi(true)" /><BaseButton :label="t('common.ignore')" @click="ignore('follow')" /></template>
        </OscarSays>

        <section v-if="myHolds.length" :aria-label="t('holds.title')">
          <HoldsPanel :holds="myHolds" :mail-link="false" />
        </section>

        <section v-if="inv" class="invite" :aria-label="t('mail.invitation.title')">
          <h3 class="ih"><BaseIcon name="calendar" :size="18" />{{ t('mail.invitation.title') }}</h3>
          <p class="it">{{ inv.title }}</p>
          <dl class="idl">
            <dt>{{ t('agenda.when') }}</dt><dd>{{ fmt.dateLong(inv.start) }}, {{ fmt.time(inv.start) }} – {{ fmt.time(inv.end) }}</dd>
            <template v-if="inv.location"><dt>{{ t('agenda.evPlace') }}</dt><dd>{{ inv.location }}</dd></template>
            <dt>{{ t('mail.invitation.organizer') }}</dt><dd>{{ inv.organizerName }}</dd>
          </dl>
          <p v-for="c in conflicts" :key="c" class="warn" role="status"><BaseIcon name="alert" :size="16" /> {{ c }}</p>
          <p v-if="mail.invitationResponse" class="done" role="status"><BaseIcon name="checkCircle" :size="16" /> {{ t(`mail.invitation.state.${mail.invitationResponse}`) }}</p>
          <p v-if="addedWithoutReply" class="done" role="status"><BaseIcon name="checkCircle" :size="16" /> {{ t('mail.invitation.state.addedOnly') }}</p>
          <div v-if="mail.invitationResponse" class="btns">
            <BaseButton variant="ghost" :expanded="changing" :label="t(changing ? 'mail.invitation.keepAnswer' : 'mail.invitation.change')" @click="changing = !changing" />
          </div>
          <div v-if="choices" class="iact">
            <SelectField v-if="workCalendars.length > 1" id="inv-cal" :model-value="calId" :label="t('mail.invitation.calendar')" :options="workCalendars.map((c) => ({ value: c.id, label: c.label }))" @update:model-value="(v: string) => (calChoice = v)" />
            <div class="btns">
              <BaseButton v-if="offered('accepted')" variant="primary" icon="check" :disabled="invBusy" :label="t('mail.invitation.accept')" @click="prepare('accepted')" />
              <BaseButton v-if="offered('tentative')" :disabled="invBusy" :label="t('mail.invitation.maybe')" @click="prepare('tentative')" />
              <BaseButton v-if="offered('declined')" variant="danger" icon="x" :disabled="invBusy" :label="t('mail.invitation.decline')" @click="prepare('declined')" />
            </div>
            <p class="hint">{{ t('mail.invitation.help') }}</p>
          </div>
        </section>

        <section v-if="aiOn && hints.summary" class="summary" :aria-label="t('mail.ai.summary')">
          <p class="sh"><BaseIcon :name="byModel('summary') ? 'sparkles' : 'bulb'" :size="16" /><strong>{{ t('mail.ai.summary') }}</strong><SuggestBadge :ai="byModel('summary')" /></p>
          <p>{{ hints.summary }}</p>
        </section>

        <div class="body" :class="{ mine: mail.folder === 'sent' }">
          <!-- Qui a écrit ce texte et quand : sans cela, on le confond avec les messages voisins de la conversation. -->
          <p class="by">
            <Avatar :name="mail.folder === 'sent' ? t('mail.thread.me') : mail.from.name" :size="28" />
            <strong>{{ mail.folder === 'sent' ? t('mail.thread.me') : mail.from.name }}</strong>
            <ToneChip v-if="mails.repliedAt(mail)" tone="success" icon="reply" :label="t('mail.repliedOn', { date: fmt.dateTime(mails.repliedAt(mail)!) })" />
            <time class="muted" :datetime="mail.at">{{ fmt.dateTime(mail.at) }}</time>
          </p>
          <MailBodyText :key="mail.id" :mail="mail" />
          <p v-if="bodyLoading" class="muted" role="status">{{ t('mail.bodyLoading') }}</p>
          <p v-else-if="bodyFailed" class="error-text" role="alert">{{ t('mail.bodyError') }} <BaseButton :label="t('common.retry')" @click="mails.loadBody(mail.id)" /></p>
          <p v-else-if="mails.gone.has(mail.id)" class="callout" data-tone="neutral">{{ t('mail.bodyGone') }}</p>
          <p v-else-if="!mail.paragraphs.length && !mail.html" class="muted">{{ t('mail.bodyEmpty') }}</p>
          <AttachmentsMenu v-if="mail.attachments.length" :items="mail.attachments" @download="download" />
        </div>

        <OscarSays v-if="aiOn && hints.availability && !dismissed.has('avail')" situation="availability" :params="{ who: mail.from.name.split(' ')[0], n: hints.availability.slots.length }" :level="3" suggestion>
          <template #actions><BaseButton variant="primary" icon="sparkles" :label="t('mail.ai.availAction')" @click="showAvail = true" /></template>
        </OscarSays>

        <section v-if="aiOn && settings.s.aiSuggestTasks && (detectedEvent || detectedTask)" class="detected" aria-labelledby="det-h">
          <h3 id="det-h">{{ t('mail.ai.detected') }}</h3>
          <OscarSays v-if="detectedEvent && !dismissed.has('event')" situation="suggestEvent" :level="3" suggestion :ai="byModel('event')">
            <template #more>
              <p class="item"><strong>{{ detectedEvent.title }}</strong></p>
              <p class="kick">{{ t('mail.ai.eventKicker', { quote: detectedEvent.quote }) }}</p>
              <p class="muted">{{ t('mail.ai.eventWhen', { date: fmt.dateLong(detectedEvent.start), start: fmt.time(detectedEvent.start), end: fmt.time(detectedEvent.end), cal: agenda.sourceOf(calId)?.label ?? '' }) }}</p>
            </template>
            <template #actions><BaseButton variant="primary" :label="t('mail.ai.eventAdd')" @click="addEvent" /><BaseButton :label="t('common.ignore')" @click="ignore('event')" /></template>
          </OscarSays>
          <OscarSays v-if="detectedTask && !dismissed.has('task')" situation="suggestTask" :level="3" suggestion :ai="byModel('task')">
            <template #more>
              <p class="item"><strong>{{ detectedTask.title }}</strong></p>
              <p class="kick">{{ t('mail.ai.taskKicker', { quote: detectedTask.quote }) }}</p>
              <p class="muted">{{ t('mail.ai.taskDue', { date: fmt.dateLong(detectedTask.dueAt) }) }}</p>
            </template>
            <template #actions><BaseButton variant="primary" :label="t('mail.ai.taskAdd')" @click="addTask" /><BaseButton :label="t('common.ignore')" @click="ignore('task')" /></template>
          </OscarSays>
        </section>

        <ThreadMessages :mail="mail" />

        <ActivityNotes v-if="shared" :mail="mail" @add-note="(x) => act(() => mails.addNote(mail.id, x), t('mail.notes.added'))" />
      </div>
    </div>
    <SuiviPanel v-if="suivi && panel" :suivi="suivi" />
  </article>

  <ConfirmTrashDialog :open="showTrash" :count="1" :with-suivi="suivi ? 1 : 0" @confirm="confirmTrash" @close="showTrash = false" />
  <MoveDialog :open="showMove" :current="mail.folder" @close="showMove = false" @pick="relocate" />

  <InvitationReplyDialog v-if="inv" :response="replying" :invitation="inv" :conflicts="conflicts" :can-add="!!calId" :added="!!inv && agenda.events.some(isThisInvitation)" :busy="invBusy" @close="replying = null" @send="(m: string) => answer(replying!, m)" @add-only="answer(replying!, undefined, false)" />
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
/* minmax(0, 1fr) : un message très large défile dans son cadre sans élargir le lecteur. */
.body { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--space-3); padding: var(--space-4) var(--space-5); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); }
.body.mine { border-inline-start: 4px solid var(--c-accent); }
.by { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin: 0; padding-bottom: var(--space-2); border-bottom: var(--bw) solid var(--c-border); }
.by time { margin-inline-start: auto; font-size: var(--fs-sm); }
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
