<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../../api'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import CheckboxField from '../ui/CheckboxField.vue'
import PasswordField from '../ui/PasswordField.vue'
import SelectField from '../ui/SelectField.vue'
import TextField from '../ui/TextField.vue'

/**
 * Le modèle de langage de l'établissement. Les mails des utilisateurs en mode « IA de l'établissement » (le mode par défaut) y seront lus :
 * on le dit, on fait confirmer l'hébergement, et la clé ne se relit jamais.
 */
const { t } = useI18n()
const ui = useUiStore()
const form = reactive({ kind: 'openai' as 'openai' | 'anthropic', baseUrl: '', model: '', apiKey: '' })
const hosted = ref(false)
const hasKey = ref(false)
const configured = ref(false)
const dirty = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const result = ref<{ ok: boolean; message: string } | null>(null)
const kinds = computed(() => (['openai', 'anthropic'] as const).map((k) => ({ value: k, label: t(`aiServer.kinds.${k}`) })))

function show(s: { configured: boolean; kind: 'openai' | 'anthropic'; baseUrl: string; model: string; hasKey: boolean }) {
  Object.assign(form, { kind: s.kind, baseUrl: s.baseUrl, model: s.model, apiKey: '' })
  configured.value = s.configured
  hasKey.value = s.hasKey
  hosted.value = s.configured
  dirty.value = false
}
onMounted(async () => { try { show(await api.instance.aiServer()) } catch { ui.notify(t('common.actionFailed'), { tone: 'error' }) } })
const touch = () => { dirty.value = true; result.value = null }

async function save() {
  errors.value = {}
  busy.value = true
  try {
    show(await api.instance.saveAiServer({ kind: form.kind, baseUrl: form.baseUrl.trim(), model: form.model.trim(), hostedByUs: hosted.value, ...(form.apiKey ? { apiKey: form.apiKey } : {}) }))
    ui.notify(t('aiServer.saved'))
  } catch (e) {
    const field = e instanceof ApiError ? String((e.data as { field?: string }).field ?? '') : ''
    if (e instanceof ApiError && field) errors.value[field] = e.message
    else ui.notify(t('common.actionFailed'), { tone: 'error' })
  } finally { busy.value = false }
}
async function test() {
  busy.value = true
  result.value = null
  try { result.value = await api.instance.testAiServer() } catch { result.value = { ok: false, message: t('common.actionFailed') } } finally { busy.value = false }
}
async function remove() {
  busy.value = true
  try {
    await api.instance.removeAiServer()
    show({ configured: false, kind: 'openai', baseUrl: '', model: '', hasKey: false })
    result.value = null
    ui.notify(t('aiServer.removed'))
  } catch { ui.notify(t('common.actionFailed'), { tone: 'error' }) } finally { busy.value = false }
}
</script>

<template>
  <BaseCard class="stack" aria-labelledby="ais-h">
    <h2 id="ais-h">{{ t('aiServer.title') }}</h2>
    <p class="muted">{{ t(configured ? 'aiServer.on' : 'aiServer.off') }}</p>
    <p class="callout" data-tone="warn">{{ t('aiServer.warning') }}</p>
    <SelectField id="ais-kind" :model-value="form.kind" :label="t('aiServer.kind')" :options="kinds" @update:model-value="(v: 'openai' | 'anthropic') => { form.kind = v; touch() }" />
    <TextField id="ais-url" v-model="form.baseUrl" type="url" :label="t('aiServer.url')" :hint="t('aiServer.urlHint')" :error="errors.baseUrl" autocomplete="off" @update:model-value="touch" />
    <TextField id="ais-model" v-model="form.model" :label="t('aiServer.model')" :error="errors.model" autocomplete="off" @update:model-value="touch" />
    <PasswordField id="ais-key" v-model="form.apiKey" :label="t('aiServer.key')" autocomplete="off" :hint="t(hasKey ? 'aiServer.keyKept' : 'aiServer.keyOptional')" :error="errors.apiKey" @update:model-value="touch" />
    <CheckboxField id="ais-hosted" v-model="hosted" :label="t('aiServer.hosted')" @update:model-value="touch" />
    <p v-if="errors.hostedByUs" class="error-text" role="alert">{{ errors.hostedByUs }}</p>
    <div class="actions">
      <BaseButton variant="primary" icon="check" :disabled="busy || !form.baseUrl || !form.model" :label="t('common.save')" @click="save" />
      <BaseButton icon="refresh" :disabled="busy || !configured || dirty" :label="t('aiServer.test')" @click="test" />
      <BaseButton v-if="configured" variant="ghost" icon="trash" :disabled="busy" :label="t('aiServer.remove')" @click="remove" />
    </div>
    <p v-if="result" role="status"><BaseIcon :name="result.ok ? 'checkCircle' : 'alert'" :size="16" /> {{ result.message }}</p>
  </BaseCard>
</template>

<style scoped>
h2 { font-size: var(--fs-md); margin: 0; }
p { margin: 0; }
.actions { display: flex; flex-wrap: wrap; gap: var(--space-2); }
</style>
