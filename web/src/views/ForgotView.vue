<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import TextField from '../components/ui/TextField.vue'
import { api } from '../api'

/** Mot de passe oublié : la réponse est la même que l'adresse ait un compte ou non (on ne révèle rien). */
const { t } = useI18n()
const email = ref('')
const sent = ref(false)
const busy = ref(false)
const error = ref('')
const devLink = ref('')

async function submit() {
  busy.value = true
  error.value = ''
  try {
    devLink.value = (await api.auth.forgot(email.value.trim())).devLink ?? ''
    sent.value = true
  } catch { error.value = t('login.failed') } finally { busy.value = false }
}
</script>

<template>
  <AuthLayout :title="t('auth.forgotTitle')" :subtitle="sent ? undefined : t('auth.forgotSubtitle')">
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <div v-if="sent" class="sent" role="status">
      <p><strong>{{ t('auth.forgotSent') }}</strong></p>
      <p class="muted">{{ t('auth.forgotSentHelp', { email }) }}</p>
      <p v-if="devLink" class="hint">{{ t('auth.devLink') }} <a :href="devLink">{{ t('auth.devLinkOpen') }}</a></p>
    </div>
    <form v-else class="stack" novalidate @submit.prevent="submit">
      <TextField id="email" v-model="email" type="email" :label="t('auth.email')" autocomplete="email" required />
      <BaseButton type="submit" variant="primary" class="full" :disabled="busy || !email" :label="t('auth.forgotSubmit')" />
    </form>
    <template #alt>
      <p><RouterLink :to="{ name: 'login' }">{{ t('auth.backToLogin') }}</RouterLink></p>
    </template>
  </AuthLayout>
</template>

<style scoped>
.stack { display: grid; gap: var(--space-4); }
.full { width: 100%; justify-content: center; }
.sent { display: grid; gap: var(--space-2); padding: var(--space-4); border: var(--bw) solid var(--c-success); border-radius: var(--radius-md); background: var(--c-success-soft); color: var(--c-text); }
.sent p { margin: 0; }
</style>
