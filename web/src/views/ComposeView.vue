<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, api } from '../api'
import OscarMark from '../components/OscarMark.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import CheckboxField from '../components/ui/CheckboxField.vue'
import SelectField from '../components/ui/SelectField.vue'
import TextField from '../components/ui/TextField.vue'
import TextareaField from '../components/ui/TextareaField.vue'
import RecipientInput from '../components/ui/RecipientInput.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { followUpDue, isoDay } from '../domain/dates'
import { searchDirectory } from '../domain/directory'
import { useOscar } from '../oscar/useOscar'
import { useAccountsStore } from '../stores/accounts'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const settings = useSettingsStore()
const accounts = useAccountsStore()
const ui = useUiStore()
const { say } = useOscar()

const to = ref<string[]>([])
const cc = ref<string[]>([])
const bcc = ref<string[]>([])
const showCc = ref(false)
const subject = ref('')
const body = ref(`\n\n${t('compose.signature')}`)
const follow = ref(false)
const aiPicked = ref(false)
const delayHours = ref<number>(settings.s.relanceHours)
const submitted = ref(false)
const sending = ref(false)
/** Brouillon préparé par Oscar : ton et créneaux modifiables, régénérable ; rien ne part sans clic. */
const aiDraft = ref(false)
const aiTone = ref<'warm' | 'short'>('warm')
const aiBusy = ref(false)
const aiFailed = ref(false)
const slotStarts = ref<string[]>(String(route.query.slots ?? '').split(',').filter(Boolean))
const edited = ref(false)
const draftId = ref<string | null>(route.query.draft ? String(route.query.draft) : null)
const MAX_BYTES = 5 * 1024 * 1024
const files = ref<File[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const attachError = ref('')
const totalBytes = computed(() => files.value.reduce((n, f) => n + f.size, 0))
function pickFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const added = [...(input.files ?? [])]
  input.value = ''
  attachError.value = ''
  for (const f of added) {
    if (totalBytes.value + f.size > MAX_BYTES) { attachError.value = t('compose.attachTooBig'); continue }
    files.value = [...files.value, f]
  }
}
const readBase64 = (f: File) => new Promise<string>((ok, ko) => { const r = new FileReader(); r.onload = () => ok(String(r.result).split(',')[1] ?? ''); r.onerror = () => ko(r.error); r.readAsDataURL(f) })
const sizeLabel = (n: number) => (n < 1024 * 1024 ? `${Math.max(1, Math.round(n / 1024))} Ko` : `${(n / 1024 / 1024).toFixed(1)} Mo`)
const quoteOn = ref(true)
const original = computed(() => (route.query.reply ? mails.byId(String(route.query.reply)) : undefined))
const originalSlots = computed(() => original.value?.hints.availability?.slots ?? [])
const quote = computed(() => (original.value ? `\n\n> ${original.value.paragraphs.join('\n> ')}` : ''))
const slotLabel = (x: { start: string; end: string }) => `${fmt.dateLong(x.start)}, ${fmt.time(x.start)} – ${fmt.time(x.end)}`
const toneOptions = computed(() => (['warm', 'short'] as const).map((v) => ({ value: v, label: t(`compose.ai.tone.${v}`) })))

onMounted(async () => {
  if (!mails.items.length) await mails.load()
  await accounts.load().catch(() => undefined)
  const acc = accounts.mail[0]
  if (acc?.relanceHours) delayHours.value = acc.relanceHours
  const dr = draftId.value ? mails.byId(draftId.value) : undefined
  if (dr) {
    to.value = dr.to.map((x) => x.address)
    subject.value = dr.subject
    await mails.loadBody(dr.id)
    body.value = mails.byId(dr.id)?.paragraphs.join('\n\n') ?? ''
    follow.value = false
    return
  }
  const fwd = route.query.forward ? mails.byId(String(route.query.forward)) : undefined
  if (fwd) {
    subject.value = /^(tr|fwd?)\s*:/i.test(fwd.subject) ? fwd.subject : `Tr : ${fwd.subject}`
    body.value = `\n\n${t('compose.signature')}\n\n${t('compose.forwarded', { who: fwd.from.name, date: fmt.dateTime(fwd.at) })}\n${fwd.paragraphs.join('\n')}`
    return
  }
  const o = original.value
  if (o) {
    to.value = [o.from.address]
    subject.value = /^re\s*:/i.test(o.subject) ? o.subject : `Re : ${o.subject}`
    body.value = `\n\n${t('compose.signature')}`
    if (route.query.ai && settings.s.aiMode !== 'off') { aiDraft.value = true; follow.value = true; aiPicked.value = true; await regenerate() }
  } else {
    // Oscar précoche « Suivre la réponse » quand le message appelle visiblement une réponse : modifiable.
    follow.value = true
    aiPicked.value = true
  }
})

async function regenerate() {
  const o = original.value
  if (!o) return
  aiBusy.value = true
  aiFailed.value = false
  try {
    const lines = slotStarts.value.map((st) => originalSlots.value.find((x) => x.start === st)).filter((x): x is { start: string; end: string } => !!x).map(slotLabel)
    body.value = await api.mails.replyDraft(o.id, { tone: aiTone.value, slots: lines })
    edited.value = false
  } catch { aiFailed.value = true } finally { aiBusy.value = false }
}
/** Ton ou créneaux changés : le brouillon suit, sauf si l'utilisateur l'a retouché à la main (alors il décide de régénérer). */
watch([aiTone, slotStarts], () => { if (aiDraft.value && !edited.value) void regenerate() }, { deep: true })
const toggleSlot = (start: string, on: boolean) => { slotStarts.value = on ? [...slotStarts.value, start] : slotStarts.value.filter((x) => x !== start) }
const title = computed(() => (original.value ? t('compose.replyTo', { who: original.value.from.name }) : t('compose.title')))
const dueDate = computed(() => isoDay(followUpDue(clock.now(), delayHours.value, accounts.mail[0]?.businessDays ?? settings.s.businessDays)))
const invalid = computed(() => submitted.value && (!subject.value.trim() || to.value.length === 0))
const who = computed(() => (to.value[0] ? searchDirectory(to.value[0], 1)[0]?.name ?? to.value[0] : '…'))
const DELAYS = [{ v: 24, k: 'h24' }, { v: 48, k: 'h48' }, { v: 72, k: 'd3' }, { v: 168, k: 'd7' }]
const delayOptions = computed(() => DELAYS.map((d) => ({ value: d.v, label: t(`compose.delays.${d.k}`) })))

async function send() {
  submitted.value = true
  if (!subject.value.trim() || to.value.length === 0) return
  sending.value = true
  try {
    const r = await mails.send({
      to: to.value, cc: cc.value, bcc: bcc.value, subject: subject.value.trim(), body: quoteOn.value && original.value ? body.value + quote.value : body.value,
      attachments: await Promise.all(files.value.map(async (f) => ({ name: f.name, type: f.type || 'application/octet-stream', content: await readBase64(f) }))),
      followHours: follow.value ? delayHours.value : undefined, followSuggestedByAi: follow.value && aiPicked.value,
      inReplyToMailId: route.query.reply ? String(route.query.reply) : undefined,
    })
    if (draftId.value) await mails.moveTo(draftId.value, 'trash').catch(() => undefined)
    ui.notify(r.followDueAt ? say('sentFollow', { date: fmt.date(r.followDueAt) }) : t('compose.sent', { to: who.value }))
    router.push({ name: 'mails' })
  } catch (e) {
    // Rien n'est parti et aucun suivi n'est créé : on le dit clairement, sans humour, avec une action (§5, F-79).
    const noAccount = e instanceof ApiError && e.message === 'no-account'
    ui.notify(noAccount ? t('compose.noAccount') : say('errorSend'), { tone: 'error' })
  } finally {
    sending.value = false
  }
}
async function saveDraft() {
  try {
    await mails.saveDraft({
      to: to.value, cc: cc.value, bcc: bcc.value, subject: subject.value.trim(), body: body.value,
      inReplyToMailId: route.query.reply ? String(route.query.reply) : undefined, replaces: draftId.value ?? undefined,
    })
    // Le brouillon enregistré remplace l'ancien : on repart du plus récent de la boîte « Brouillons ».
    draftId.value = mails.inFolder('personal', 'drafts').value.find((m) => m.subject === subject.value.trim())?.id ?? null
    ui.notify(t('compose.draftSaved'), { tone: 'info' })
  } catch (e) {
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  }
}
</script>

<template>
  <PageHeader :title="title" :subtitle="aiDraft ? t('compose.ai.prepared') : undefined">
    <RouterLink v-if="original" class="btn ghost" :to="{ name: 'mails', params: { id: original.id } }">{{ t('compose.backToMail') }}</RouterLink>
  </PageHeader>
  <form class="form" novalidate @submit.prevent="send">
    <div class="main stack">
      <article v-if="original" class="card origin" :aria-label="t('compose.original')">
        <p class="muted small">{{ t('compose.receivedFrom', { who: original.from.name, date: fmt.dateTime(original.at) }) }}</p>
        <p class="osub">{{ original.subject }}</p>
        <p v-for="(p, i) in original.paragraphs.slice(0, 6)" :key="i" class="otext">{{ p }}</p>
      </article>

      <section class="card compose-card stack" :aria-label="t('compose.title')">
        <div class="field">
          <label for="to">{{ t('compose.to') }}</label>
          <RecipientInput id="to" v-model="to" :label="t('compose.to')" :invalid="submitted && to.length === 0" described-by="dir-help" />
          <p id="dir-help" class="hint">{{ t('compose.directoryHelp') }}</p>
        </div>
        <BaseButton v-if="!showCc" class="cc" variant="ghost" :label="t('compose.showCc')" :expanded="false" @click="showCc = true" />
        <template v-else>
          <div class="field"><label for="cc">{{ t('compose.cc') }}</label><RecipientInput id="cc" v-model="cc" :label="t('compose.cc')" /></div>
          <div class="field"><label for="bcc">{{ t('compose.bcc') }}</label><RecipientInput id="bcc" v-model="bcc" :label="t('compose.bcc')" /></div>
        </template>
        <TextField id="subject" v-model="subject" :label="t('compose.subject')" required :error="submitted && !subject.trim() ? t('compose.subjectRequired') : ''" />
        <p v-if="invalid" class="error-text" role="alert">{{ t('compose.required') }}</p>

        <div v-if="aiDraft" class="aibar" role="group" :aria-label="t('oscar.name')">
          <span class="face" aria-hidden="true"><OscarMark /></span>
          <div class="aitext">
            <p class="who">{{ t('oscar.name') }}<AiBadge /></p>
            <p>{{ say('draftReady') }}</p>
            <p v-if="aiFailed" class="error-text" role="alert">{{ t('compose.ai.failed') }}</p>
            <p v-else-if="aiBusy" class="muted" role="status">{{ t('compose.ai.busy') }}</p>
            <p v-else-if="edited" class="muted" role="status">{{ t('compose.ai.edited') }}</p>
          </div>
          <SegmentedControl v-model="aiTone" :options="toneOptions" :label="t('compose.ai.toneLabel')" />
          <BaseButton icon="refresh" :label="t('compose.ai.regenerate')" :disabled="aiBusy" @click="regenerate" />
        </div>

        <TextareaField id="body" v-model="body" :label="t('compose.body')" :rows="12" @input="edited = true" />
        <CheckboxField v-if="original" v-model="quoteOn" :label="t('compose.quote')" />
      </section>
    </div>

    <aside class="side stack">
      <section v-if="aiDraft && originalSlots.length" class="card pad-sm flat slots" :aria-label="t('compose.ai.slots')">
        <h2>{{ t('compose.ai.slots') }}</h2>
        <p class="hint">{{ t('compose.ai.slotsHelp', { n: slotStarts.length }) }}</p>
        <ul>
          <li v-for="x in originalSlots" :key="x.start">
            <CheckboxField :model-value="slotStarts.includes(x.start)" :label="slotLabel(x)" @update:model-value="(v: boolean) => toggleSlot(x.start, v)" />
            <span class="free">{{ t('compose.ai.free') }}</span>
          </li>
        </ul>
        <p class="hint">{{ t('agenda.freeBusy') }}</p>
      </section>

      <fieldset class="card pad-sm flat follow">
        <legend class="sr-only">{{ t('compose.follow') }}</legend>
        <div class="check"><CheckboxField v-model="follow" :label="t('compose.follow')"><strong>{{ t('compose.follow') }}</strong></CheckboxField> <AiBadge v-if="aiPicked && follow" /></div>
        <p v-if="aiPicked && follow" class="hint">{{ say('followAi') }}</p>
        <template v-if="follow">
          <SelectField id="delay" v-model="delayHours" :label="t('compose.followDelay')" :options="delayOptions" />
          <p class="hint" aria-live="polite">{{ t('compose.followInfo', { date: fmt.dateLong(dueDate) }) }}</p>
        </template>
      </fieldset>
      <div class="actions">
        <BaseButton variant="primary" type="submit" icon="send" :disabled="sending" :label="t('compose.send')" />
        <BaseButton icon="paperclip" :label="t('compose.attach')" @click="fileInput?.click()" />
        <input ref="fileInput" type="file" multiple class="sr-only" tabindex="-1" :aria-label="t('compose.attach')" @change="pickFiles" />
        <BaseButton :label="t('compose.saveDraft')" @click="saveDraft" />
        <BaseButton variant="ghost" :label="t('common.cancel')" @click="router.back()" />
      </div>
      <section v-if="files.length || attachError" class="card pad-sm flat" :aria-label="t('compose.attached')">
        <h2 class="ah">{{ t('compose.attached') }} · {{ sizeLabel(totalBytes) }}</h2>
        <ul class="flist"><li v-for="(f, i) in files" :key="f.name + i"><span>{{ f.name }} · {{ sizeLabel(f.size) }}</span><BaseButton variant="ghost" icon="x" icon-only :label="t('compose.removeFile', { name: f.name })" @click="files = files.filter((_, n) => n !== i)" /></li></ul>
        <p v-if="attachError" class="error-text" role="alert">{{ attachError }}</p>
      </section>
      <p class="hint">{{ say('neverSend') }}</p>
    </aside>
  </form>
</template>

<style scoped>
.form { display: grid; gap: var(--space-5); grid-template-columns: minmax(0, 1fr); align-items: start; }
@media (min-width: 1000px) { .form { grid-template-columns: minmax(0, 1fr) 360px; } .side { position: sticky; top: 0; } }
.cc { justify-self: start; }
.origin { display: grid; gap: var(--space-1); background: var(--c-surface-2); }
.small { font-size: var(--fs-sm); }
.osub { font-weight: 800; }
.otext { color: var(--c-text-muted); overflow-wrap: anywhere; }
.compose-card { padding: var(--space-4) var(--space-5); }
.aibar { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: var(--space-3) var(--space-3); align-items: center; padding: var(--space-3) var(--space-4); border-radius: var(--radius-lg); background: var(--c-ai-soft); border: var(--bw) solid var(--c-ai); color: var(--c-text); }
.aibar > :nth-child(3), .aibar > :nth-child(4) { grid-column: 2; justify-self: start; }
@media (min-width: 1300px) { .aibar { grid-template-columns: auto minmax(0, 1fr) auto auto; } .aibar > :nth-child(3), .aibar > :nth-child(4) { grid-column: auto; } }
.face { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--c-surface); color: var(--c-ai); border: var(--bw) solid var(--c-ai); }
.who { display: flex; align-items: center; gap: var(--space-2); font-weight: 800; color: var(--c-ai); }
.slots h2 { font-size: var(--fs-md); }
.slots ul { list-style: none; margin: var(--space-2) 0; padding: 0; display: grid; gap: var(--space-1); }
.slots li { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.free { font-size: var(--fs-sm); font-weight: 700; color: var(--c-success); white-space: nowrap; }
.ah { font-size: var(--fs-md); }
.flist { list-style: none; margin: var(--space-2) 0 0; padding: 0; display: grid; gap: var(--space-1); }
.flist li { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); overflow-wrap: anywhere; }
.follow { display: grid; gap: var(--space-3); margin: 0; }
.check { display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
