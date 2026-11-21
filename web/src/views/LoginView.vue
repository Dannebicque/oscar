<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PasswordField from '../components/ui/PasswordField.vue'
import TextField from '../components/ui/TextField.vue'
import { ApiError, api } from '../api'
import type { AuthConfig } from '../api/types'
import { useAuthStore } from '../stores/auth'

/** Connexion : adresse et mot de passe, ou le CAS de l'établissement ; en local, une connexion de développement. */
const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const username = ref('')
const error = ref('')
const busy = ref(false)
const config = ref<AuthConfig>({ password: true, registration: true, cas: true, dev: false })
const DEMOS = [{ id: 'david', name: 'David' }, { id: 'claire', name: 'Claire' }, { id: 'marc', name: 'Marc' }, { id: 'lea', name: 'Léa' }]
const base = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

onMounted(async () => {
  config.value = await api.auth.config().catch(() => config.value)
  // Retour du CAS : le serveur nous renvoie ici avec #code=… (à usage unique), échangé contre un jeton.
  const hash = new URLSearchParams(location.hash.slice(1))
  history.replaceState(null, '', location.pathname + location.search)
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
  } catch (e) {
    error.value = e instanceof ApiError && (e.status === 401 || e.status === 429) ? e.message : t('login.failed')
  } finally {
    busy.value = false
  }
}

const casUrl = computed(() => `${base}/api/auth/cas/login?return=${encodeURIComponent(`${location.origin}/connexion`)}`)
</script>

<template>
  <AuthLayout :title="t('login.title')" :subtitle="t('login.subtitle')">
    <p v-if="error" class="error-text box" role="alert">{{ error }}</p>

    <form v-if="config.password" class="stack" novalidate @submit.prevent="finish(() => auth.login(email, password))">
      <TextField id="email" v-model="email" type="email" :label="t('auth.email')" autocomplete="username" required />
      <PasswordField id="password" v-model="password" :label="t('auth.password')" autocomplete="current-password" required />
      <p class="forgot"><RouterLink :to="{ name: 'forgot' }">{{ t('auth.forgotLink') }}</RouterLink></p>
      <BaseButton type="submit" variant="primary" class="full" :disabled="busy || !email || !password" :label="t('login.submit')" />
    </form>

    <template v-if="config.cas">
      <p v-if="config.password" class="or" role="separator"><span>{{ t('auth.or') }}</span></p>
      <a class="btn full" :href="casUrl">{{ t('login.cas') }}</a>
    </template>

    <section v-if="config.dev" class="demo" :aria-label="t('login.demoTitle')">
      <h2>{{ t('login.demoTitle') }}</h2>
      <p class="hint">{{ t('login.demoHelp') }}</p>
      <ul>
        <li v-for="d in DEMOS" :key="d.id">
          <button type="button" class="who" :disabled="busy" @click="finish(() => auth.loginDev(d.id))">
            <span class="av" aria-hidden="true">{{ d.name[0] }}</span>
            <span class="txt"><strong>{{ d.name }}</strong><small>{{ t(`login.demo.${d.id}`) }}</small></span>
          </button>
        </li>
      </ul>
      <details class="dev">
        <summary>{{ t('login.dev') }}</summary>
        <form class="stack" @submit.prevent="finish(() => auth.loginDev(username))">
          <TextField id="u" v-model="username" :label="t('login.username')" autocomplete="username" required />
          <BaseButton type="submit" :disabled="busy || !username" :label="t('login.devSubmit')" />
          <p class="hint">{{ t('login.devHelp') }}</p>
        </form>
      </details>
    </section>

    <template v-if="config.registration" #alt>
      <p>{{ t('auth.noAccount') }} <RouterLink :to="{ name: 'register' }">{{ t('auth.createLink') }}</RouterLink></p>
    </template>
  </AuthLayout>
</template>

<style scoped>
.stack { display: grid; gap: var(--space-4); }
.box { padding: var(--space-3) var(--space-4); border: var(--bw) solid var(--c-danger); border-radius: var(--radius-md); background: var(--c-danger-soft); }
.forgot { margin: calc(var(--space-2) * -1) 0 0; font-size: var(--fs-sm); text-align: end; }
.full { width: 100%; justify-content: center; text-decoration: none; }
.or { display: flex; align-items: center; gap: var(--space-3); margin: 0; color: var(--c-text-muted); font-size: var(--fs-sm); }
.or::before, .or::after { content: ''; flex: 1; border-top: var(--bw) solid var(--c-border); }
.demo { display: grid; gap: var(--space-2); }
.demo h2 { font-size: var(--fs-md); margin: 0; }
.demo ul { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-2); }
.who { display: flex; align-items: center; gap: var(--space-3); width: 100%; min-height: calc(var(--target) + 8px); padding: var(--space-2) var(--space-3); border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-md); background: var(--c-surface); color: var(--c-text); font: inherit; text-align: start; cursor: pointer; }
.who:hover:not(:disabled) { background: var(--c-surface-2); }
.av { flex: none; width: 32px; height: 32px; border-radius: 50%; background: var(--c-primary-soft); color: var(--c-on-primary-soft); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
.txt { display: grid; min-width: 0; } .txt small { color: var(--c-text-muted); overflow-wrap: anywhere; }
.dev { border: var(--bw) dashed var(--c-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); }
.dev summary { cursor: pointer; font-weight: 600; min-height: var(--target); display: flex; align-items: center; }
.dev form { padding-top: var(--space-2); }
</style>
