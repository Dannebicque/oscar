<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, api } from '../api'
import OscarSays from '../components/ui/OscarSays.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import CheckboxField from '../components/ui/CheckboxField.vue'
import SelectField from '../components/ui/SelectField.vue'
import TextField from '../components/ui/TextField.vue'
import TextareaField from '../components/ui/TextareaField.vue'
import RecipientInput from '../components/ui/RecipientInput.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { addDays, isoDay } from '../domain/dates'
import { searchDirectory } from '../domain/directory'
import { useOscar } from '../oscar/useOscar'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const settings = useSettingsStore()
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
const slotStarts = computed(() => String(route.query.slots ?? '').split(',').filter(Boolean))
const toneOptions = computed(() => (['warm', 'short'] as const).map((v) => ({ value: v, label: t(`compose.ai.tone.${v}`) })))

onMounted(async () => {
  if (!mails.items.length) await mails.load()
  const original = route.query.reply ? mails.byId(String(route.query.reply)) : undefined
  if (original) {
    to.value = [original.from.address]
    subject.value = /^re\s*:/i.test(original.subject) ? original.subject : `Re : ${original.subject}`
    body.value = `\n\n${t('compose.signature')}\n\n> ${original.paragraphs.join('\n> ')}`
    if (route.query.ai && settings.s.aiMode !== 'off') { aiDraft.value = true; follow.value = true; aiPicked.value = true; await regenerate() }
  } else {
    // Oscar précoche « Suivre la réponse » quand le message appelle visiblement une réponse : modifiable.
    follow.value = true
    aiPicked.value = true
  }
})

async function regenerate() {
  const original = route.query.reply ? mails.byId(String(route.query.reply)) : undefined
  if (!original) return
  aiBusy.value = true
  aiFailed.value = false
  try {
    const known = original.hints.availability?.slots ?? []
    const lines = slotStarts.value.map((st) => known.find((x) => x.start === st)).filter((x): x is { start: string; end: string } => !!x)
      .map((x) => `${fmt.dateLong(x.start)}, ${fmt.time(x.start)} – ${fmt.time(x.end)}`)
    const draft = await api.mails.replyDraft(original.id, { tone: aiTone.value, slots: lines })
    body.value = `${draft}\n\n> ${original.paragraphs.join('\n> ')}`
  } catch { aiFailed.value = true } finally { aiBusy.value = false }
}
const dueDate = computed(() => isoDay(addDays(clock.now(), Math.ceil(delayHours.value / 24))))
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
      to: to.value, cc: cc.value, bcc: bcc.value, subject: subject.value.trim(), body: body.value,
      followHours: follow.value ? delayHours.value : undefined, followSuggestedByAi: follow.value && aiPicked.value,
      inReplyToMailId: route.query.reply ? String(route.query.reply) : undefined,
    })
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
function saveDraft() {
  ui.notify(t('compose.draftSaved'), { tone: 'info' })
}
</script>

<template>
  <PageHeader :title="t('compose.title')" />
  <form class="form" novalidate @submit.prevent="send">
    <div class="main stack">
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
      <OscarSays v-if="aiDraft" situation="draftReady" suggestion :level="3">
        <template #more>
          <p v-if="aiFailed" class="error-text" role="alert">{{ t('compose.ai.failed') }}</p>
          <p v-else-if="aiBusy" class="muted" role="status">{{ t('compose.ai.busy') }}</p>
        </template>
        <template #actions>
          <div class="tonebox"><SelectField id="ai-tone" v-model="aiTone" :label="t('compose.ai.toneLabel')" :options="toneOptions" /></div>
          <BaseButton icon="refresh" :label="t('compose.ai.regenerate')" :disabled="aiBusy" @click="regenerate" />
        </template>
      </OscarSays>
      <TextareaField id="body" v-model="body" :label="t('compose.body')" :rows="14" />
    </div>

    <aside class="side stack">
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
        <BaseButton icon="paperclip" :label="t('compose.attach')" @click="ui.notify(t('compose.attach'), { tone: 'info' })" />
        <BaseButton :label="t('compose.saveDraft')" @click="saveDraft" />
        <BaseButton variant="ghost" :label="t('common.cancel')" @click="router.back()" />
      </div>
      <p class="hint">{{ say('neverSend') }}</p>
    </aside>
  </form>
</template>

<style scoped>
.form { display: grid; gap: var(--space-5); grid-template-columns: minmax(0, 1fr); align-items: start; }
@media (min-width: 1000px) { .form { grid-template-columns: minmax(0, 1fr) 340px; } .side { position: sticky; top: 0; } }
.cc { justify-self: start; }
.tonebox { min-width: 160px; }
.follow { display: grid; gap: var(--space-3); margin: 0; }
.check { display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
