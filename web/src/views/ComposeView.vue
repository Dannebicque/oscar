<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import AiBadge from '../components/ui/AiBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { addDays, isoDay } from '../domain/dates'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useSuivisStore } from '../stores/suivis'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const suivis = useSuivisStore()
const settings = useSettingsStore()
const ui = useUiStore()

/** Annuaire LDAP simulé : nom, prénom, e-mail au minimum (F-18). */
const DIRECTORY = [
  'Sophie Laurent <sophie.laurent@univ-reims.fr>', 'Marc Dubois <marc.dubois@imprimerie.fr>', 'Claire Petit <claire.petit@entreprise.fr>',
  'Claire Roux <claire.roux@iut-troyes.fr>', 'Léa Martin <lea.martin@iut-troyes.fr>', 'Scolarité <scolarite@iut-troyes.fr>',
]

const to = ref('')
const cc = ref('')
const bcc = ref('')
const showCc = ref(false)
const subject = ref('')
const body = ref(`\n\n${t('compose.signature')}`)
const follow = ref(false)
const aiPicked = ref(false)
const delayHours = ref<number>(settings.s.relanceHours)
const submitted = ref(false)

onMounted(async () => {
  if (!mails.items.length) await mails.load()
  const original = route.query.reply ? mails.byId(String(route.query.reply)) : undefined
  if (original) {
    to.value = `${original.from.name} <${original.from.address}>`
    subject.value = /^re\s*:/i.test(original.subject) ? original.subject : `Re : ${original.subject}`
    body.value = `\n\n${t('compose.signature')}\n\n> ${original.paragraphs.join('\n> ')}`
  } else {
    // Oscar précoche « Suivre la réponse » quand le message appelle visiblement une réponse : modifiable.
    follow.value = true
    aiPicked.value = true
  }
})

const dueDate = computed(() => isoDay(addDays(clock.now(), Math.ceil(delayHours.value / 24))))
const invalid = computed(() => submitted.value && (!to.value.trim() || !subject.value.trim()))
const who = computed(() => to.value.replace(/<.*>/, '').trim() || '…')
const DELAYS = [{ v: 24, k: 'h24' }, { v: 48, k: 'h48' }, { v: 72, k: 'd3' }, { v: 168, k: 'd7' }]

async function send() {
  submitted.value = true
  if (!to.value.trim() || !subject.value.trim()) return
  await api.mails.send({ to: to.value, subject: subject.value, body: body.value, follow: follow.value })
  if (follow.value) {
    await suivis.create({ subject: subject.value.replace(/^re\s*:\s*/i, ''), contact: who.value, direction: 'waiting', dueAt: dueDate.value, relanceAt: dueDate.value, suggestedByAi: aiPicked.value })
    ui.notify(t('compose.sentFollow', { date: fmt.date(dueDate.value) }))
  } else {
    ui.notify(t('compose.sent', { to: who.value }))
  }
  router.push({ name: 'mails' })
}
function saveDraft() {
  ui.notify(t('compose.draftSaved'), { tone: 'info' })
}
</script>

<template>
  <PageHeader :title="t('compose.title')" />
  <form class="form" novalidate @submit.prevent="send">
    <div class="field">
      <label for="to">{{ t('compose.to') }}</label>
      <input id="to" v-model="to" class="input" list="directory" autocomplete="off" required :aria-invalid="submitted && !to.trim()" aria-describedby="dir-help" />
      <datalist id="directory"><option v-for="d in DIRECTORY" :key="d" :value="d" /></datalist>
      <p id="dir-help" class="hint">{{ t('compose.directoryHelp') }}</p>
    </div>
    <BaseButton v-if="!showCc" variant="ghost" :label="t('compose.showCc')" :expanded="false" @click="showCc = true" />
    <template v-else>
      <div class="field"><label for="cc">{{ t('compose.cc') }}</label><input id="cc" v-model="cc" class="input" list="directory" autocomplete="off" /></div>
      <div class="field"><label for="bcc">{{ t('compose.bcc') }}</label><input id="bcc" v-model="bcc" class="input" list="directory" autocomplete="off" /></div>
    </template>
    <div class="field">
      <label for="subject">{{ t('compose.subject') }}</label>
      <input id="subject" v-model="subject" class="input" required :aria-invalid="submitted && !subject.trim()" autocomplete="off" />
    </div>
    <p v-if="invalid" class="error-text" role="alert">{{ t('compose.required') }}</p>
    <div class="field">
      <label for="body">{{ t('compose.body') }}</label>
      <textarea id="body" v-model="body" class="input" rows="12" />
    </div>

    <fieldset class="follow">
      <legend class="sr-only">{{ t('compose.follow') }}</legend>
      <label class="check"><input v-model="follow" type="checkbox" /> <strong>{{ t('compose.follow') }}</strong> <AiBadge v-if="aiPicked && follow" /></label>
      <p v-if="aiPicked && follow" class="hint">{{ t('compose.followAi') }}</p>
      <template v-if="follow">
        <div class="field inline">
          <label for="delay">{{ t('compose.followDelay') }}</label>
          <select id="delay" v-model.number="delayHours" class="input">
            <option v-for="d in DELAYS" :key="d.v" :value="d.v">{{ t(`compose.delays.${d.k}`) }}</option>
          </select>
        </div>
        <p class="hint" aria-live="polite">{{ t('compose.followInfo', { date: fmt.dateLong(dueDate) }) }}</p>
      </template>
    </fieldset>

    <div class="actions">
      <BaseButton variant="primary" type="submit" icon="send" :label="t('compose.send')" />
      <BaseButton icon="paperclip" :label="t('compose.attach')" @click="ui.notify(t('compose.attach'), { tone: 'info' })" />
      <BaseButton :label="t('compose.saveDraft')" @click="saveDraft" />
      <BaseButton variant="ghost" :label="t('common.cancel')" @click="router.back()" />
    </div>
  </form>
</template>

<style scoped>
.form { display: grid; gap: var(--space-4); max-width: 760px; }
.follow { border: 1px solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-4); display: grid; gap: var(--space-2); margin: 0; background: var(--c-surface); }
.check { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); flex-wrap: wrap; }
.inline { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
.inline .input { width: auto; }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
