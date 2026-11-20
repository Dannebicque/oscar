<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '../components/ui/BaseButton.vue'
import OscarMark from '../components/OscarMark.vue'
import { useAuthStore } from '../stores/auth'

/** Connexion à l'application : CAS de l'établissement ; en local, une connexion de développement. */
const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const username = ref('')
const error = ref('')
const busy = ref(false)
const api = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'
const devLogin = import.meta.env.DEV || import.meta.env.VITE_DEV_LOGIN === 'true'

onMounted(async () => {
  // Retour du CAS : le serveur nous renvoie ici avec #code=… (à usage unique), échangé contre un jeton.
  const hash = new URLSearchParams(location.hash.slice(1))
  history.replaceState(null, '', location.pathname)
  if (hash.get('error')) error.value = t('login.casRefused')
  const code = hash.get('code')
  if (code) await finish(() => auth.finishCas(code))
})

async function finish(fn: () => Promise<void>) {
  busy.value = true
  error.value = ''
  try {
    await fn()
    router.replace(String(route.query.next ?? '/'))
  } catch {
    error.value = t('login.failed')
  } finally {
    busy.value = false
  }
}

const casUrl = () => `${api}/api/auth/cas/login?return=${encodeURIComponent(`${location.origin}/connexion`)}`
</script>

<template>
  <main class="login" id="main">
    <div class="card">
      <span class="logo"><OscarMark /></span>
      <h1>{{ t('login.title') }}</h1>
      <p class="muted">{{ t('app.tagline') }}</p>
      <p v-if="error" class="error-text" role="alert">{{ error }}</p>
      <a class="cas" :href="casUrl()">{{ t('login.cas') }}</a>
      <form v-if="devLogin" class="dev" @submit.prevent="finish(() => auth.loginDev(username))">
        <h2>{{ t('login.dev') }}</h2>
        <div class="field">
          <label for="u">{{ t('login.username') }}</label>
          <input id="u" v-model="username" class="input" autocomplete="username" required pattern="[A-Za-z0-9._\-]{1,60}" />
        </div>
        <BaseButton type="submit" variant="primary" :disabled="busy || !username" :label="t('login.devSubmit')" />
        <p class="hint">{{ t('login.devHelp') }}</p>
      </form>
    </div>
  </main>
</template>

<style scoped>
.login { min-height: 100vh; display: grid; place-items: center; padding: var(--space-4); }
.card { width: min(420px, 100%); display: grid; gap: var(--space-4); padding: var(--space-6); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-lg); justify-items: start; }
.logo { width: 44px; height: 44px; border-radius: 12px; background: var(--c-primary); color: var(--c-on-primary); display: inline-flex; align-items: center; justify-content: center; }
.cas { display: inline-flex; align-items: center; justify-content: center; min-height: var(--target); padding: 0 var(--space-5); border-radius: var(--radius-sm); background: var(--c-primary); color: var(--c-on-primary); font-weight: 700; text-decoration: none; }
.dev { display: grid; gap: var(--space-3); width: 100%; border-top: 1px solid var(--c-border); padding-top: var(--space-4); }
h2 { font-size: var(--fs-md); }
</style>
