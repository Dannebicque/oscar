<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, api, session } from '../../api'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import PasswordField from '../ui/PasswordField.vue'
import TextField from '../ui/TextField.vue'

/** Mon compte (N-05, N-06) : identité, mot de passe (comptes locaux), export de mes données, suppression définitive. */
const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const current = ref('')
const next = ref('')
const pwdError = ref<{ current?: string; password?: string }>({})
const busy = ref(false)
const confirmOpen = ref(false)
const secret = ref('')
const delError = ref('')
const exporting = ref(false)

async function changePassword() {
  pwdError.value = {}
  if (next.value.length < 10) { pwdError.value.password = t('auth.passwordShort'); return }
  busy.value = true
  try {
    await api.auth.changePassword(current.value, next.value)
    current.value = ''
    next.value = ''
    ui.notify(t('account.passwordChanged'))
  } catch (e) {
    const f = e instanceof ApiError ? String((e.data as { field?: string }).field ?? '') : ''
    if (e instanceof ApiError && (f === 'current' || f === 'password')) pwdError.value[f] = e.message
    else ui.notify(t('common.actionFailed'), { tone: 'error' })
  } finally { busy.value = false }
}
async function exportData() {
  exporting.value = true
  try { await auth.exportData(); ui.notify(t('account.exported')) } catch { ui.notify(t('common.actionFailed'), { tone: 'error' }) } finally { exporting.value = false }
}
async function remove() {
  delError.value = ''
  busy.value = true
  try {
    await auth.deleteAccount(session.hasPassword ? { password: secret.value } : { confirm: secret.value })
    confirmOpen.value = false
    router.replace({ name: 'login' })
  } catch (e) { delError.value = e instanceof ApiError ? e.message : t('common.actionFailed') } finally { busy.value = false }
}
const confirmLabel = computed(() => (session.hasPassword ? t('auth.password') : t('account.confirmUsername', { username: session.username })))
</script>

<template>
  <div class="stack">
    <BaseCard class="stack">
      <h3>{{ t('account.identity') }}</h3>
      <dl class="id"><dt>{{ t('auth.name') }}</dt><dd>{{ session.name }}</dd><dt>{{ t('auth.email') }}</dt><dd>{{ session.email ?? session.username }}</dd></dl>
      <p class="hint">{{ session.hasPassword ? t('account.localHelp') : t('account.casHelp') }}</p>
    </BaseCard>

    <BaseCard v-if="session.hasPassword" class="stack">
      <h3>{{ t('account.changePassword') }}</h3>
      <form class="stack" novalidate @submit.prevent="changePassword">
        <PasswordField id="pw-current" v-model="current" :label="t('account.currentPassword')" autocomplete="current-password" required :error="pwdError.current" />
        <PasswordField id="pw-new" v-model="next" :label="t('auth.newPassword')" autocomplete="new-password" required :hint="t('auth.passwordHint')" :error="pwdError.password" />
        <div><BaseButton type="submit" variant="primary" :disabled="busy || !current || !next" :label="t('account.changePasswordSubmit')" /></div>
        <p class="hint">{{ t('account.otherDevices') }}</p>
      </form>
    </BaseCard>

    <BaseCard class="stack">
      <h3>{{ t('account.data') }}</h3>
      <p>{{ t('account.exportHelp') }}</p>
      <div><BaseButton icon="download" :disabled="exporting" :label="t('account.export')" @click="exportData" /></div>
    </BaseCard>

    <BaseCard class="stack danger">
      <h3>{{ t('account.delete') }}</h3>
      <p>{{ t('account.deleteHelp') }}</p>
      <div><BaseButton variant="danger" icon="trash" :label="t('account.delete')" @click="confirmOpen = true; secret = ''; delError = ''" /></div>
    </BaseCard>

    <BaseDialog :open="confirmOpen" :title="t('account.deleteTitle')" :description="t('account.deleteText')" @close="confirmOpen = false">
      <form id="delete-form" class="stack" novalidate @submit.prevent="remove">
        <PasswordField v-if="session.hasPassword" id="del-secret" v-model="secret" :label="confirmLabel" autocomplete="current-password" required />
        <TextField v-else id="del-secret" v-model="secret" :label="confirmLabel" required />
        <p v-if="delError" class="error-text" role="alert">{{ delError }}</p>
      </form>
      <template #footer>
        <BaseButton :label="t('common.cancel')" @click="confirmOpen = false" />
        <BaseButton variant="danger" icon="trash" type="submit" form="delete-form" :disabled="busy || !secret" :label="t('account.deleteConfirm')" />
      </template>
    </BaseDialog>
  </div>
</template>

<style scoped>
.id { display: grid; grid-template-columns: auto 1fr; gap: var(--space-1) var(--space-4); margin: 0; }
.id dt { color: var(--c-text-muted); } .id dd { margin: 0; font-weight: 700; }
.danger { border-color: var(--c-danger); }
h3 { font-size: var(--fs-md); }
</style>
