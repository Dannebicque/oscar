<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAccountsStore } from '../../stores/accounts'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SwitchField from '../ui/SwitchField.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Comptes de messagerie, agendas CalDAV et boîtes partagées (F-10, F-20, F-40). Les mots de passe ne reviennent jamais de l'API. */
const { t } = useI18n()
const accounts = useAccountsStore()
const ui = useUiStore()
onMounted(() => accounts.load())

type Dialog = null | 'mail' | 'calendar'
const dialog = ref<Dialog>(null)
const busy = ref(false)
const testing = ref<string | null>(null)
const confirmRemove = ref<{ kind: 'mail' | 'calendar'; id: string; label: string } | null>(null)

const mailForm = reactive({ label: '', address: '', username: '', password: '', imapHost: '', imapPort: 993, imapEncryption: 'ssl' as 'none' | 'ssl' | 'tls', smtpHost: '', smtpPort: 587, smtpEncryption: 'tls' as 'none' | 'ssl' | 'tls', aiAllowed: true })
const calForm = reactive({ label: '', url: '', username: '', password: '', personal: false })

async function addMail() {
  busy.value = true
  try {
    await accounts.addMail({ ...mailForm })
    ui.notify(t('settings.accounts.added', { name: mailForm.label }))
    dialog.value = null
    Object.assign(mailForm, { label: '', address: '', username: '', password: '' })
  } catch { ui.notify(t('settings.accounts.addFailed'), { tone: 'error' }) } finally { busy.value = false }
}
async function addCalendar() {
  busy.value = true
  try {
    await accounts.addCalendar({ ...calForm })
    ui.notify(t('settings.accounts.added', { name: calForm.label }))
    dialog.value = null
    Object.assign(calForm, { label: '', url: '', username: '', password: '', personal: false })
  } catch { ui.notify(t('settings.accounts.addFailed'), { tone: 'error' }) } finally { busy.value = false }
}
async function test(id: string) {
  testing.value = id
  try {
    const r = await accounts.testMail(id)
    ui.notify(r.ok ? t('settings.accounts.testOk') : t('settings.accounts.testFailed'), { tone: r.ok ? 'success' : 'error' })
  } catch { ui.notify(t('settings.accounts.testFailed'), { tone: 'error' }) } finally { testing.value = null }
}
async function remove() {
  const r = confirmRemove.value
  if (!r) return
  confirmRemove.value = null
  try {
    if (r.kind === 'mail') await accounts.removeMail(r.id)
    else await accounts.removeCalendar(r.id)
    ui.notify(t('settings.accounts.removed', { name: r.label }), { tone: 'info' })
  } catch { ui.notify(t('settings.accounts.removeFailed'), { tone: 'error' }) }
}
const ENC = ['ssl', 'tls', 'none'] as const
</script>

<template>
  <div class="panel">
    <div class="card">
      <div class="head"><h3>{{ t('settings.accounts.mailAccounts') }}</h3><BaseButton icon="plus" :label="t('settings.accounts.addMail')" @click="dialog = 'mail'" /></div>
      <p v-if="accounts.mail.length === 0" class="muted">{{ t('settings.accounts.noMail') }}</p>
      <ul class="list">
        <li v-for="a in accounts.mail" :key="a.id" class="acc">
          <div class="main">
            <strong>{{ a.label }}</strong>
            <span class="muted">{{ a.address }} · IMAP {{ a.imapHost }}:{{ a.imapPort }} · SMTP {{ a.smtpHost }}:{{ a.smtpPort }}</span>
            <ToneChip v-if="a.lastError" tone="danger" icon="alert" :label="t('status.error')" />
            <ToneChip v-else-if="a.lastSyncAt" tone="success" icon="checkCircle" :label="t('status.ok')" />
          </div>
          <div class="acts">
            <BaseButton icon="refresh" :disabled="testing === a.id" :label="t('settings.accounts.test')" @click="test(a.id)" />
            <BaseButton variant="danger" icon="trash" :label="t('common.remove')" @click="confirmRemove = { kind: 'mail', id: a.id, label: a.label }" />
          </div>
        </li>
      </ul>
    </div>

    <div class="card">
      <div class="head"><h3>{{ t('settings.accounts.calendars') }}</h3><BaseButton icon="plus" :label="t('settings.accounts.addCalendar')" @click="dialog = 'calendar'" /></div>
      <p v-if="accounts.calendar.length === 0" class="muted">{{ t('settings.accounts.noCalendar') }}</p>
      <ul class="list">
        <li v-for="c in accounts.calendar" :key="c.id" class="acc">
          <div class="main">
            <strong>{{ c.label }} · {{ c.kind === 'google' ? 'Google Agenda' : 'CalDAV' }}</strong>
            <span class="muted">{{ c.personal ? t('agenda.private') : t('settings.accounts.work') }}<template v-if="c.url"> · {{ c.url }}</template></span>
            <ToneChip v-if="c.lastError" tone="danger" icon="alert" :label="t('status.error')" />
          </div>
          <div class="acts"><BaseButton variant="danger" icon="trash" :label="t('common.remove')" @click="confirmRemove = { kind: 'calendar', id: c.id, label: c.label }" /></div>
        </li>
      </ul>
    </div>

    <div class="card">
      <h3>{{ t('settings.accounts.sharedBoxes') }}</h3>
      <p v-if="accounts.shared.length === 0" class="muted">{{ t('settings.accounts.noShared') }}</p>
      <ul class="list">
        <li v-for="b in accounts.shared" :key="b.id" class="acc">
          <div class="main">
            <strong>{{ b.name }} · {{ b.address }}</strong>
            <ToneChip tone="info" icon="users" :label="b.role === 'admin' ? t('settings.accounts.roleAdmin') : t('settings.accounts.roleMember')" />
            <span class="muted">{{ b.members.map((m) => m.displayName).join(', ') }}</span>
          </div>
        </li>
      </ul>
      <p class="hint">{{ t('settings.accounts.sharedHelp') }}</p>
    </div>
  </div>

  <BaseDialog :open="dialog === 'mail'" :title="t('settings.accounts.addMail')" @close="dialog = null">
    <form id="f-mail" class="form" @submit.prevent="addMail">
      <div class="field"><label for="m-label">{{ t('settings.accounts.fLabel') }}</label><input id="m-label" v-model="mailForm.label" class="input" required /></div>
      <div class="field"><label for="m-addr">{{ t('settings.accounts.fAddress') }}</label><input id="m-addr" v-model="mailForm.address" type="email" class="input" required autocomplete="email" /></div>
      <div class="two">
        <div class="field"><label for="m-user">{{ t('settings.accounts.fUser') }}</label><input id="m-user" v-model="mailForm.username" class="input" required autocomplete="username" /></div>
        <div class="field"><label for="m-pass">{{ t('settings.accounts.fPassword') }}</label><input id="m-pass" v-model="mailForm.password" type="password" class="input" required autocomplete="new-password" aria-describedby="m-pass-h" /></div>
      </div>
      <p id="m-pass-h" class="hint">{{ t('settings.accounts.passwordHelp') }}</p>
      <fieldset class="srv">
        <legend>IMAP</legend>
        <div class="field"><label for="m-ih">{{ t('settings.accounts.fHost') }}</label><input id="m-ih" v-model="mailForm.imapHost" class="input" required /></div>
        <div class="field"><label for="m-ip">{{ t('settings.accounts.fPort') }}</label><input id="m-ip" v-model.number="mailForm.imapPort" type="number" min="1" max="65535" class="input" required /></div>
        <div class="field"><label for="m-ie">{{ t('settings.accounts.fEncryption') }}</label><select id="m-ie" v-model="mailForm.imapEncryption" class="input"><option v-for="e in ENC" :key="e" :value="e">{{ t(`settings.accounts.enc.${e}`) }}</option></select></div>
      </fieldset>
      <fieldset class="srv">
        <legend>SMTP</legend>
        <div class="field"><label for="m-sh">{{ t('settings.accounts.fHost') }}</label><input id="m-sh" v-model="mailForm.smtpHost" class="input" required /></div>
        <div class="field"><label for="m-sp">{{ t('settings.accounts.fPort') }}</label><input id="m-sp" v-model.number="mailForm.smtpPort" type="number" min="1" max="65535" class="input" required /></div>
        <div class="field"><label for="m-se">{{ t('settings.accounts.fEncryption') }}</label><select id="m-se" v-model="mailForm.smtpEncryption" class="input"><option v-for="e in ENC" :key="e" :value="e">{{ t(`settings.accounts.enc.${e}`) }}</option></select></div>
      </fieldset>
      <SwitchField v-model="mailForm.aiAllowed" :label="t('settings.accounts.aiAllowed')" :help="t('settings.accounts.aiAllowedHelp')" />
    </form>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="dialog = null" />
      <BaseButton variant="primary" type="submit" form="f-mail" :disabled="busy" :label="t('common.add')" />
    </template>
  </BaseDialog>

  <BaseDialog :open="dialog === 'calendar'" :title="t('settings.accounts.addCalendar')" @close="dialog = null">
    <form id="f-cal" class="form" @submit.prevent="addCalendar">
      <div class="field"><label for="c-label">{{ t('settings.accounts.fLabel') }}</label><input id="c-label" v-model="calForm.label" class="input" required /></div>
      <div class="field"><label for="c-url">{{ t('settings.accounts.fCalUrl') }}</label><input id="c-url" v-model="calForm.url" type="url" class="input" required /></div>
      <div class="two">
        <div class="field"><label for="c-user">{{ t('settings.accounts.fUser') }}</label><input id="c-user" v-model="calForm.username" class="input" autocomplete="username" /></div>
        <div class="field"><label for="c-pass">{{ t('settings.accounts.fPassword') }}</label><input id="c-pass" v-model="calForm.password" type="password" class="input" autocomplete="new-password" /></div>
      </div>
      <SwitchField v-model="calForm.personal" :label="t('settings.accounts.personalCal')" :help="t('settings.accounts.includePersonalHelp')" />
    </form>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="dialog = null" />
      <BaseButton variant="primary" type="submit" form="f-cal" :disabled="busy" :label="t('common.add')" />
    </template>
  </BaseDialog>

  <BaseDialog :open="confirmRemove !== null" :title="t('settings.accounts.removeTitle', { name: confirmRemove?.label ?? '' })" @close="confirmRemove = null">
    <p>{{ t('settings.accounts.removeText') }}</p>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="confirmRemove = null" />
      <BaseButton variant="danger" icon="trash" :label="t('common.remove')" @click="remove" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.panel { display: grid; gap: var(--space-4); width: 100%; }
.card { display: grid; gap: var(--space-3); padding: var(--space-4) var(--space-5); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-lg); }
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
h3 { font-size: var(--fs-md); }
.list { display: grid; gap: var(--space-2); }
.acc { display: flex; gap: var(--space-3); align-items: center; justify-content: space-between; flex-wrap: wrap; padding: var(--space-3); border: 1px solid var(--c-border); border-radius: var(--radius-md); }
.main { display: grid; gap: 2px; min-width: 0; justify-items: start; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.form { display: grid; gap: var(--space-3); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
.srv { border: 1px solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-3); display: grid; grid-template-columns: 2fr 1fr 1fr; gap: var(--space-3); }
.srv legend { font-weight: 700; padding: 0 var(--space-2); }
@media (max-width: 640px) { .two, .srv { grid-template-columns: 1fr; } }
</style>
