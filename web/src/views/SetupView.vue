<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ApiError, api } from '../api'
import AuthLayout from '../components/auth/AuthLayout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PasswordField from '../components/ui/PasswordField.vue'
import RadioGroup from '../components/ui/RadioGroup.vue'
import TextField from '../components/ui/TextField.vue'
import { useAuthStore } from '../stores/auth'
import { useInstanceStore } from '../stores/instance'

/**
 * Assistant de premier lancement : un code d'installation (affiché dans les journaux du serveur), l'administrateur, puis les réglages de base.
 * Il ne s'ouvre que tant qu'aucun administrateur n'existe.
 */
const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const instance = useInstanceStore()

const step = ref<1 | 2 | 3>(1)
const code = ref('')
const name = ref('')
const email = ref('')
const password = ref('')
const again = ref('')
const instanceName = ref('Oscar')
const registration = ref<'open' | 'closed'>('closed')
const supportEmail = ref('')
const errors = ref<Record<string, string>>({})
const general = ref('')
const busy = ref(false)

const regOptions = computed(() => (['closed', 'open'] as const).map((v) => ({ value: v, label: t(`setup.registration.${v}`) })))
const STEPS = [1, 2, 3] as const

function next() {
  errors.value = {}
  if (step.value === 1) {
    if (!code.value.trim()) { errors.value.code = t('setup.codeRequired'); return }
    step.value = 2
  } else if (step.value === 2) {
    if (!name.value.trim()) errors.value.name = t('auth.name')
    if (!/^\S+@\S+\.\S+$/.test(email.value.trim())) errors.value.email = t('setup.emailInvalid')
    if (password.value.length < 10) errors.value.password = t('auth.passwordShort')
    else if (password.value !== again.value) errors.value.again = t('setup.passwordsDiffer')
    if (Object.keys(errors.value).length === 0) step.value = 3
  }
}

async function finish() {
  errors.value = {}
  general.value = ''
  busy.value = true
  try {
    const token = await api.instance.setup({
      code: code.value.trim(), name: name.value.trim(), email: email.value.trim(), password: password.value,
      config: { instanceName: instanceName.value.trim() || 'Oscar', registration: registration.value, supportEmail: supportEmail.value.trim() },
    })
    await auth.adopt(token)
    await instance.load()
    router.replace({ name: 'admin' })
  } catch (e) {
    const data = (e instanceof ApiError ? e.data : {}) as { field?: string; fields?: Record<string, string> }
    if (e instanceof ApiError && data.field === 'code') { errors.value.code = e.message; step.value = 1 }
    else if (e instanceof ApiError && data.field) { errors.value[data.field] = e.message; step.value = 2 }
    else if (e instanceof ApiError && data.fields) errors.value = { ...data.fields }
    else general.value = e instanceof ApiError ? e.message : t('login.failed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthLayout :title="t('setup.title')" :subtitle="t('setup.subtitle')">
    <ol class="steps" :aria-label="t('setup.stepsLabel')">
      <li v-for="s in STEPS" :key="s" :aria-current="step === s ? 'step' : undefined" :class="{ done: step > s }"><span class="n">{{ s }}</span> {{ t(`setup.step${s}`) }}</li>
    </ol>
    <p v-if="general" class="error-text box" role="alert">{{ general }}</p>

    <form v-if="step === 1" class="stack" novalidate @submit.prevent="next">
      <p>{{ t('setup.codeHelp') }}</p>
      <pre class="cmd">docker compose -f compose.prod.yaml logs app | grep -A2 "code d’installation"</pre>
      <TextField id="code" v-model="code" :label="t('setup.code')" autocomplete="off" required :error="errors.code" />
      <BaseButton type="submit" variant="primary" class="full" :label="t('setup.next')" />
    </form>

    <form v-else-if="step === 2" class="stack" novalidate @submit.prevent="next">
      <p>{{ t('setup.adminHelp') }}</p>
      <TextField id="name" v-model="name" :label="t('auth.name')" autocomplete="name" required :error="errors.name" />
      <TextField id="email" v-model="email" type="email" :label="t('auth.email')" autocomplete="email" required :error="errors.email" />
      <PasswordField id="password" v-model="password" :label="t('auth.password')" autocomplete="new-password" required :hint="t('auth.passwordHint')" :error="errors.password" />
      <PasswordField id="again" v-model="again" :label="t('setup.again')" autocomplete="new-password" required :error="errors.again" />
      <div class="actions"><BaseButton :label="t('setup.back')" @click="step = 1" /><BaseButton type="submit" variant="primary" :label="t('setup.next')" /></div>
    </form>

    <form v-else class="stack" novalidate @submit.prevent="finish">
      <p>{{ t('setup.configHelp') }}</p>
      <TextField id="iname" v-model="instanceName" :label="t('setup.instanceName')" :hint="t('setup.instanceNameHint')" :error="errors.instanceName" />
      <RadioGroup v-model="registration" name="reg" :legend="t('setup.registrationLegend')" :options="regOptions" />
      <p class="hint">{{ t('setup.registrationHint') }}</p>
      <TextField id="support" v-model="supportEmail" type="email" :label="t('setup.supportEmail')" :hint="t('setup.supportHint')" :error="errors.supportEmail" />
      <div class="actions"><BaseButton :label="t('setup.back')" @click="step = 2" /><BaseButton type="submit" variant="primary" :disabled="busy" :label="t('setup.finish')" /></div>
      <p class="hint">{{ t('setup.promise') }}</p>
    </form>
  </AuthLayout>
</template>

<style scoped>
.stack { display: grid; gap: var(--space-4); }
.steps { list-style: none; margin: 0 0 var(--space-4); padding: 0; display: flex; gap: var(--space-3); flex-wrap: wrap; font-size: var(--fs-sm); }
.steps li { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--c-text-muted); }
.steps li[aria-current='step'] { color: var(--c-text); font-weight: 800; }
.steps .n { display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 50%; border: var(--bw) solid var(--c-border-strong); }
.steps li[aria-current='step'] .n { background: var(--c-primary); color: var(--c-on-primary); border-color: var(--c-primary); }
.steps li.done .n { background: var(--c-success); color: var(--c-surface); border-color: var(--c-success); }
.cmd { margin: 0; padding: var(--space-3); background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); overflow-x: auto; font-size: var(--fs-sm); }
.box { padding: var(--space-3) var(--space-4); border: var(--bw) solid var(--c-danger); border-radius: var(--radius-md); background: var(--c-danger-soft); }
.full { width: 100%; justify-content: center; }
.actions { display: flex; gap: var(--space-2); justify-content: space-between; flex-wrap: wrap; }
</style>
