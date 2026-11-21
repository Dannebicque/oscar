<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import OscarMark from '../components/OscarMark.vue'
import PasswordField from '../components/ui/PasswordField.vue'
import SelectField from '../components/ui/SelectField.vue'
import TextField from '../components/ui/TextField.vue'
import { api } from '../api'
import type { ProbeResult } from '../api/types'
import { guessServers, isAddress, type Encryption } from '../domain/mailPresets'
import { useAccountsStore } from '../stores/accounts'
import { useSystemStore } from '../stores/system'
import { useUiStore } from '../stores/ui'

/**
 * Assistant de première connexion (F-10) : adresse, identifiants, serveurs avec test, puis c'est prêt.
 * Les serveurs sont devinés d'après le domaine et se corrigent ; rien n'est enregistré tant que le test n'a pas réussi.
 */
const { t } = useI18n()
const router = useRouter()
const accounts = useAccountsStore()
const ui = useUiStore()
const system = useSystemStore()

type Step = 1 | 2 | 3 | 4
const step = ref<Step>(1)
const form = reactive({ address: '', label: '', username: '', password: '', imapHost: '', imapPort: 993, imapEncryption: 'ssl' as Encryption, smtpHost: '', smtpPort: 587, smtpEncryption: 'tls' as Encryption })
const hint = computed(() => guessServers(form.address))
const addressError = ref('')
const probe = ref<ProbeResult | null>(null)
const probing = ref(false)
const saving = ref(false)
const saveError = ref('')
const heading = ref<HTMLElement | null>(null)

const STEPS = ['address', 'password', 'servers', 'done'] as const
const encOptions = computed(() => (['ssl', 'tls', 'none'] as const).map((e) => ({ value: e, label: t(`settings.accounts.enc.${e}`) })))
const allGood = computed(() => !!probe.value?.imap.ok && !!probe.value?.smtp.ok)

async function go(n: Step) {
  step.value = n
  await nextTick()
  heading.value?.focus()
}
function toPassword() {
  if (!isAddress(form.address)) { addressError.value = t('wizard.addressInvalid'); return }
  addressError.value = ''
  const g = guessServers(form.address)
  Object.assign(form, g.servers)
  if (!form.username) form.username = form.address.trim()
  if (!form.label) form.label = form.address.split('@')[1]
  void go(2)
}
async function runProbe() {
  probing.value = true
  probe.value = null
  try {
    probe.value = await api.auth.probe({ imapHost: form.imapHost.trim(), imapPort: Number(form.imapPort), imapEncryption: form.imapEncryption, smtpHost: form.smtpHost.trim(), smtpPort: Number(form.smtpPort), smtpEncryption: form.smtpEncryption, username: form.username.trim(), password: form.password })
  } catch {
    probe.value = { imap: { ok: false, message: t('wizard.probeFailed') }, smtp: { ok: false, message: t('wizard.probeFailed') } }
  } finally { probing.value = false }
}
watch(() => [form.imapHost, form.imapPort, form.imapEncryption, form.smtpHost, form.smtpPort, form.smtpEncryption, form.username, form.password], () => { probe.value = null })

async function save() {
  saving.value = true
  saveError.value = ''
  try {
    await accounts.addMail({ label: form.label.trim() || form.address, address: form.address.trim(), username: form.username.trim(), password: form.password, imapHost: form.imapHost.trim(), imapPort: Number(form.imapPort), imapEncryption: form.imapEncryption, smtpHost: form.smtpHost.trim(), smtpPort: Number(form.smtpPort), smtpEncryption: form.smtpEncryption, aiAllowed: true })
    form.password = ''
    void system.syncNow().catch(() => undefined) // premiers mails sans attendre le planificateur
    await go(4)
  } catch { saveError.value = t('settings.accounts.addFailed') } finally { saving.value = false }
}
const finish = (to: string) => { ui.notify(t('wizard.readyToast')); router.replace(to) }
</script>

<template>
  <div class="wizard">
    <header class="top">
      <p class="mark"><span class="logo"><OscarMark /></span><span class="word">oscar</span></p>
      <RouterLink class="later" to="/">{{ t('wizard.later') }}</RouterLink>
    </header>

    <main id="main" class="body">
      <ol class="steps" :aria-label="t('wizard.stepsLabel')">
        <li v-for="(s, i) in STEPS" :key="s" :aria-current="step === i + 1 ? 'step' : undefined" :class="{ done: step > i + 1, now: step === i + 1 }">
          <span class="dot" aria-hidden="true"><BaseIcon v-if="step > i + 1" name="check" :size="14" /><template v-else>{{ i + 1 }}</template></span>
          <span class="lbl">{{ t(`wizard.steps.${s}`) }}</span>
        </li>
      </ol>

      <section class="card step" :aria-labelledby="`h-${step}`">
        <h1 :id="`h-${step}`" ref="heading" tabindex="-1">{{ t(`wizard.h.${STEPS[step - 1]}`) }}</h1>

        <form v-if="step === 1" class="stack" novalidate @submit.prevent="toPassword">
          <p class="muted">{{ t('wizard.p.address') }}</p>
          <TextField id="w-address" v-model="form.address" type="email" :label="t('wizard.address')" autocomplete="email" required :error="addressError" />
          <p v-if="isAddress(form.address) && hint.known" class="note" role="status"><BaseIcon name="checkCircle" :size="16" /> {{ t(`wizard.known.${hint.provider}`) }}</p>
          <div class="nav"><BaseButton type="submit" variant="primary" :label="t('wizard.next')" /></div>
        </form>

        <form v-else-if="step === 2" class="stack" novalidate @submit.prevent="go(3)">
          <p class="muted">{{ t('wizard.p.password') }}</p>
          <p v-if="hint.appPassword" class="note warn"><BaseIcon name="alert" :size="16" /> {{ t('wizard.appPassword') }}</p>
          <TextField id="w-user" v-model="form.username" :label="t('settings.accounts.fUser')" autocomplete="username" required :hint="t('wizard.userHint')" />
          <PasswordField id="w-pass" v-model="form.password" :label="t('settings.accounts.fPassword')" autocomplete="current-password" required :hint="t('settings.accounts.passwordHelp')" />
          <TextField id="w-label" v-model="form.label" :label="t('settings.accounts.fLabel')" :hint="t('wizard.labelHint')" />
          <div class="nav"><BaseButton :label="t('common.back')" @click="go(1)" /><BaseButton type="submit" variant="primary" :disabled="!form.username || !form.password" :label="t('wizard.next')" /></div>
        </form>

        <div v-else-if="step === 3" class="stack">
          <p class="muted">{{ hint.known ? t('wizard.p.serversKnown') : t('wizard.p.serversGuess') }}</p>
          <details class="servers" :open="!hint.known || (probe !== null && !allGood)">
            <summary>{{ t('wizard.serversDetails') }}</summary>
            <fieldset class="grid">
              <legend>{{ t('wizard.incoming') }}</legend>
              <TextField id="w-imap" v-model="form.imapHost" :label="t('settings.accounts.fHost')" />
              <TextField id="w-imap-port" v-model="form.imapPort" type="number" :label="t('settings.accounts.fPort')" />
              <SelectField id="w-imap-enc" v-model="form.imapEncryption" :label="t('settings.accounts.fEncryption')" :options="encOptions" />
            </fieldset>
            <fieldset class="grid">
              <legend>{{ t('wizard.outgoing') }}</legend>
              <TextField id="w-smtp" v-model="form.smtpHost" :label="t('settings.accounts.fHost')" />
              <TextField id="w-smtp-port" v-model="form.smtpPort" type="number" :label="t('settings.accounts.fPort')" />
              <SelectField id="w-smtp-enc" v-model="form.smtpEncryption" :label="t('settings.accounts.fEncryption')" :options="encOptions" />
            </fieldset>
          </details>

          <div class="probe" aria-live="polite">
            <p v-if="probing" class="muted" role="status">{{ t('wizard.testing') }}</p>
            <ul v-else-if="probe">
              <li v-for="k in (['imap', 'smtp'] as const)" :key="k" :class="probe[k].ok ? 'ok' : 'ko'"><BaseIcon :name="probe[k].ok ? 'checkCircle' : 'alert'" :size="18" /><span>{{ probe[k].message }}</span></li>
            </ul>
            <p v-if="saveError" class="error-text" role="alert">{{ saveError }}</p>
          </div>
          <div class="nav">
            <BaseButton :label="t('common.back')" @click="go(2)" />
            <BaseButton v-if="!allGood" :variant="probe ? 'secondary' : 'primary'" icon="refresh" :disabled="probing || !form.imapHost || !form.smtpHost" :label="probe ? t('wizard.retest') : t('wizard.test')" @click="runProbe" />
            <BaseButton v-if="allGood" variant="primary" icon="check" :disabled="saving" :label="t('wizard.connect')" @click="save" />
          </div>
          <p class="hint">{{ t('wizard.privacy') }}</p>
        </div>

        <div v-else class="stack">
          <p class="ready"><BaseIcon name="checkCircle" :size="22" /> {{ t('wizard.p.done', { address: form.address }) }}</p>
          <ul class="next">
            <li>{{ t('wizard.next1') }}</li>
            <li>{{ t('wizard.next2') }}</li>
            <li>{{ t('wizard.next3') }}</li>
          </ul>
          <div class="nav">
            <BaseButton :label="t('wizard.addCalendar')" @click="finish('/reglages')" />
            <BaseButton variant="primary" icon="mail" :label="t('wizard.openMails')" @click="finish('/mails')" />
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.wizard { min-height: 100dvh; display: grid; grid-template-rows: auto 1fr; background: var(--c-bg); }
.top { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4) var(--space-5); border-bottom: var(--bw) solid var(--c-border); background: var(--c-surface); }
.mark { display: flex; align-items: center; gap: var(--space-2); margin: 0; margin-inline-end: auto; }
.logo { width: 32px; height: 32px; border-radius: var(--radius-md); background: var(--c-primary); color: var(--c-on-primary); display: inline-flex; align-items: center; justify-content: center; }
.word { font-weight: 800; font-size: 1.15rem; letter-spacing: -0.02em; }
.later { min-height: var(--target); display: inline-flex; align-items: center; }
.body { width: min(640px, 100%); margin: 0 auto; padding: var(--space-5) var(--space-4) var(--space-7, 48px); display: grid; gap: var(--space-5); align-content: start; }
.steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-2); counter-reset: s; }
.steps li { display: grid; justify-items: center; gap: var(--space-1); text-align: center; font-size: var(--fs-sm); color: var(--c-text-muted); position: relative; }
.steps li:not(:last-child)::after { content: ''; position: absolute; top: 14px; inset-inline-start: calc(50% + 18px); width: calc(100% - 36px); border-top: 2px solid var(--c-border); }
.steps li.done:not(:last-child)::after { border-color: var(--c-primary); }
.dot { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; background: var(--c-surface); border: 2px solid var(--c-border-strong); color: var(--c-text-muted); }
.now .dot { background: var(--c-primary); border-color: var(--c-primary); color: var(--c-on-primary); }
.now .lbl { color: var(--c-text); font-weight: 800; }
.done .dot { background: var(--c-primary-soft); border-color: var(--c-primary); color: var(--c-on-primary-soft); }
.step { padding: var(--space-5); display: grid; gap: var(--space-4); }
.step h1 { font-size: 1.5rem; letter-spacing: -0.02em; margin: 0; }
.stack { display: grid; gap: var(--space-4); }
.nav { display: flex; gap: var(--space-2); justify-content: flex-end; flex-wrap: wrap; }
.note { display: flex; gap: var(--space-2); align-items: center; margin: 0; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); background: var(--c-success-soft); color: var(--c-text); }
.note.warn { background: var(--c-warn-soft); align-items: flex-start; }
.servers { border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-4) var(--space-4); }
.servers summary { min-height: var(--target); display: flex; align-items: center; cursor: pointer; font-weight: 700; }
.grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: var(--space-3); border: 0; margin: var(--space-3) 0 0; padding: 0; }
.grid legend { font-weight: 800; margin-bottom: var(--space-2); grid-column: 1 / -1; }
.grid > :nth-child(4) { grid-column: 1 / -1; }
.probe ul { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.probe li { display: flex; gap: var(--space-2); align-items: flex-start; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); }
.probe li.ok { background: var(--c-success-soft); }
.probe li.ko { background: var(--c-danger-soft); }
.ready { display: flex; gap: var(--space-2); align-items: center; font-weight: 700; margin: 0; }
.next { margin: 0; padding-inline-start: var(--space-5); display: grid; gap: var(--space-1); color: var(--c-text-muted); }
@media (max-width: 520px) { .grid { grid-template-columns: minmax(0, 1fr); } .lbl { font-size: 0.7rem; } .step { padding: var(--space-4); } }
</style>
