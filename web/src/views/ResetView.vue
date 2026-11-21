<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PasswordField from '../components/ui/PasswordField.vue'
import { ApiError, api } from '../api'

/** Nouveau mot de passe. Le jeton vient du lien reçu par courriel (dans le fragment, jamais envoyé dans une adresse de requête). */
const { t } = useI18n()
const router = useRouter()
const token = ref('')
const password = ref('')
const confirm = ref('')
const error = ref('')
const done = ref(false)
const busy = ref(false)
onMounted(() => {
  token.value = new URLSearchParams(location.hash.slice(1)).get('token') ?? ''
  history.replaceState(null, '', location.pathname)
})

async function submit() {
  error.value = ''
  if (password.value.length < 10) { error.value = t('auth.passwordShort'); return }
  if (password.value !== confirm.value) { error.value = t('auth.passwordMismatch'); return }
  busy.value = true
  try {
    await api.auth.reset(token.value, password.value)
    done.value = true
    setTimeout(() => router.replace({ name: 'login' }), 2500)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('login.failed')
  } finally { busy.value = false }
}
</script>

<template>
  <AuthLayout :title="t('auth.resetTitle')" :subtitle="done || !token ? undefined : t('auth.resetSubtitle')">
    <div v-if="done" class="ok" role="status"><p>{{ t('auth.resetDone') }}</p></div>
    <template v-else-if="!token">
      <p class="error-text" role="alert">{{ t('auth.resetNoToken') }}</p>
      <RouterLink class="btn" :to="{ name: 'forgot' }">{{ t('auth.forgotSubmit') }}</RouterLink>
    </template>
    <form v-else class="stack" novalidate @submit.prevent="submit">
      <p v-if="error" class="error-text" role="alert">{{ error }}</p>
      <PasswordField id="password" v-model="password" :label="t('auth.newPassword')" autocomplete="new-password" required :hint="t('auth.passwordHint')" />
      <PasswordField id="confirm" v-model="confirm" :label="t('auth.confirmPassword')" autocomplete="new-password" required />
      <BaseButton type="submit" variant="primary" class="full" :disabled="busy || !password || !confirm" :label="t('auth.resetSubmit')" />
    </form>
    <template #alt>
      <p><RouterLink :to="{ name: 'login' }">{{ t('auth.backToLogin') }}</RouterLink></p>
    </template>
  </AuthLayout>
</template>

<style scoped>
.stack { display: grid; gap: var(--space-4); }
.full { width: 100%; justify-content: center; }
.ok { padding: var(--space-4); border: var(--bw) solid var(--c-success); border-radius: var(--radius-md); background: var(--c-success-soft); }
.ok p { margin: 0; }
</style>
