<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PasswordField from '../components/ui/PasswordField.vue'
import TextField from '../components/ui/TextField.vue'
import { ApiError } from '../api'
import { useAuthStore } from '../stores/auth'

/** Création d'un compte local : nom, adresse, mot de passe. On enchaîne sur l'assistant de connexion de la messagerie. */
const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const name = ref('')
const email = ref('')
const password = ref('')
const errors = ref<Record<string, string>>({})
const general = ref('')
const busy = ref(false)

const strength = computed(() => {
  const p = password.value
  const score = (p.length >= 10 ? 1 : 0) + (p.length >= 14 ? 1 : 0) + (/[A-Z]/.test(p) && /[a-z]/.test(p) ? 1 : 0) + (/\d/.test(p) || /[^A-Za-z0-9]/.test(p) ? 1 : 0)
  return p ? (['weak', 'weak', 'fair', 'good', 'strong'] as const)[score] : null
})

async function submit() {
  errors.value = {}
  general.value = ''
  if (password.value.length < 10) { errors.value.password = t('auth.passwordShort'); return }
  busy.value = true
  try {
    await auth.register(name.value.trim(), email.value.trim(), password.value)
    router.replace({ name: 'welcome' })
  } catch (e) {
    const field = e instanceof ApiError ? String((e.data as { field?: string } | undefined)?.field ?? '') : ''
    if (e instanceof ApiError && field) errors.value[field] = e.message
    else general.value = e instanceof ApiError && e.status === 403 ? e.message : t('login.failed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('auth.registerTitle')" :subtitle="t('auth.registerSubtitle')">
    <p v-if="general" class="error-text box" role="alert">{{ general }}</p>
    <form class="stack" novalidate @submit.prevent="submit">
      <TextField id="name" v-model="name" :label="t('auth.name')" autocomplete="name" required :error="errors.name" />
      <TextField id="email" v-model="email" type="email" :label="t('auth.email')" autocomplete="email" required :error="errors.email" />
      <div class="stack tight">
        <PasswordField id="password" v-model="password" :label="t('auth.password')" autocomplete="new-password" required :hint="t('auth.passwordHint')" :error="errors.password" />
        <p v-if="strength" class="strength" :data-level="strength" role="status">{{ t(`auth.strength.${strength}`) }}</p>
      </div>
      <BaseButton type="submit" variant="primary" class="full" :disabled="busy || !name || !email || !password" :label="t('auth.registerSubmit')" />
      <p class="hint">{{ t('auth.registerNote') }}</p>
    </form>
    <template #alt>
      <p>{{ t('auth.haveAccount') }} <RouterLink :to="{ name: 'login' }">{{ t('auth.loginLink') }}</RouterLink></p>
    </template>
  </AuthLayout>
</template>

<style scoped>
.stack { display: grid; gap: var(--space-4); }
.tight { gap: var(--space-2); }
.box { padding: var(--space-3) var(--space-4); border: var(--bw) solid var(--c-danger); border-radius: var(--radius-md); background: var(--c-danger-soft); }
.full { width: 100%; justify-content: center; }
.strength { margin: 0; font-size: var(--fs-sm); font-weight: 700; }
.strength[data-level='weak'] { color: var(--c-danger); }
.strength[data-level='fair'] { color: var(--c-warn); }
.strength[data-level='good'], .strength[data-level='strong'] { color: var(--c-success); }
</style>
