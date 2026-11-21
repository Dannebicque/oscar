<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAccountsStore } from '../../stores/accounts'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SelectField from '../ui/SelectField.vue'
import TextField from '../ui/TextField.vue'
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
const encOptions = computed(() => ENC.map((e) => ({ value: e, label: t(`settings.accounts.enc.${e}`) })))
</script>

<template>
  <div class="accounts">
    <BaseCard class="stack">
      <div class="head"><h3>{{ t('settings.accounts.mailAccounts') }}</h3><BaseButton icon="plus" :label="t('settings.accounts.addMail')" @click="dialog = 'mail'" /></div>
      <p v-if="accounts.mail.length === 0" class="muted">{{ t('settings.accounts.noMail') }}</p>
      <ul class="list">
        <li v-for="a in accounts.mail" :key="a.id" class="row acc">
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
    </BaseCard>

    <BaseCard class="stack">
      <div class="head"><h3>{{ t('settings.accounts.calendars') }}</h3><BaseButton icon="plus" :label="t('settings.accounts.addCalendar')" @click="dialog = 'calendar'" /></div>
      <p v-if="accounts.calendar.length === 0" class="muted">{{ t('settings.accounts.noCalendar') }}</p>
      <ul class="list">
        <li v-for="c in accounts.calendar" :key="c.id" class="row acc">
          <div class="main">
            <strong>{{ c.label }} · {{ c.kind === 'google' ? 'Google Agenda' : 'CalDAV' }}</strong>
            <span class="muted">{{ c.personal ? t('agenda.private') : t('settings.accounts.work') }}<template v-if="c.url"> · {{ c.url }}</template></span>
            <ToneChip v-if="c.lastError" tone="danger" icon="alert" :label="t('status.error')" />
          </div>
          <div class="acts"><BaseButton variant="danger" icon="trash" :label="t('common.remove')" @click="confirmRemove = { kind: 'calendar', id: c.id, label: c.label }" /></div>
        </li>
      </ul>
    </BaseCard>

    <BaseCard class="stack">
      <h3>{{ t('settings.accounts.sharedBoxes') }}</h3>
      <p v-if="accounts.shared.length === 0" class="muted">{{ t('settings.accounts.noShared') }}</p>
      <ul class="list">
        <li v-for="b in accounts.shared" :key="b.id" class="row acc">
          <div class="main">
            <strong>{{ b.name }} · {{ b.address }}</strong>
            <ToneChip tone="info" icon="users" :label="b.role === 'admin' ? t('settings.accounts.roleAdmin') : t('settings.accounts.roleMember')" />
            <span class="muted">{{ b.members.map((m) => m.displayName).join(', ') }}</span>
          </div>
        </li>
      </ul>
      <p class="hint">{{ t('settings.accounts.sharedHelp') }}</p>
    </BaseCard>
  </div>

  <BaseDialog :open="dialog === 'mail'" :title="t('settings.accounts.addMail')" @close="dialog = null">
    <form id="f-mail" class="form" @submit.prevent="addMail">
      <TextField id="m-label" v-model="mailForm.label" :label="t('settings.accounts.fLabel')" required />
      <TextField id="m-addr" v-model="mailForm.address" :label="t('settings.accounts.fAddress')" type="email" required autocomplete="email" />
      <div class="two">
        <TextField id="m-user" v-model="mailForm.username" :label="t('settings.accounts.fUser')" required autocomplete="username" />
        <TextField id="m-pass" v-model="mailForm.password" :label="t('settings.accounts.fPassword')" type="password" required autocomplete="new-password" />
      </div>
      <p id="m-pass-h" class="hint">{{ t('settings.accounts.passwordHelp') }}</p>
      <fieldset class="srv">
        <legend>IMAP</legend>
        <TextField id="m-ih" v-model="mailForm.imapHost" :label="t('settings.accounts.fHost')" required />
        <TextField id="m-ip" v-model.number="mailForm.imapPort" :label="t('settings.accounts.fPort')" type="number" required :min="1" :max="65535" />
        <SelectField id="m-ie" v-model="mailForm.imapEncryption" :label="t('settings.accounts.fEncryption')" :options="encOptions" />
      </fieldset>
      <fieldset class="srv">
        <legend>SMTP</legend>
        <TextField id="m-sh" v-model="mailForm.smtpHost" :label="t('settings.accounts.fHost')" required />
        <TextField id="m-sp" v-model.number="mailForm.smtpPort" :label="t('settings.accounts.fPort')" type="number" required :min="1" :max="65535" />
        <SelectField id="m-se" v-model="mailForm.smtpEncryption" :label="t('settings.accounts.fEncryption')" :options="encOptions" />
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
      <TextField id="c-label" v-model="calForm.label" :label="t('settings.accounts.fLabel')" required />
      <TextField id="c-url" v-model="calForm.url" :label="t('settings.accounts.fCalUrl')" type="url" required />
      <div class="two">
        <TextField id="c-user" v-model="calForm.username" :label="t('settings.accounts.fUser')" autocomplete="username" />
        <TextField id="c-pass" v-model="calForm.password" :label="t('settings.accounts.fPassword')" type="password" autocomplete="new-password" />
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
.accounts { display: grid; gap: var(--space-4); width: 100%; }
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
h3 { font-size: var(--fs-md); }
.list { display: grid; gap: var(--space-2); }
.main { display: grid; gap: 2px; min-width: 0; justify-items: start; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.form { display: grid; gap: var(--space-3); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
.srv { border: 1px solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-3); display: grid; grid-template-columns: 2fr 1fr 1fr; gap: var(--space-3); }
.srv legend { font-weight: 700; padding: 0 var(--space-2); }
@media (max-width: 640px) { .two, .srv { grid-template-columns: 1fr; } }
</style>
