<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../../api'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import PasswordField from '../ui/PasswordField.vue'
import SelectField from '../ui/SelectField.vue'
import TextField from '../ui/TextField.vue'

/**
 * Configuration du fournisseur d'IA externe (§5) : service, adresse, modèle, clé. La clé est chiffrée sur le serveur et ne s'affiche plus.
 * L'activation dit clairement ce qui part, et qui le traite.
 */
const { t } = useI18n()
const ui = useUiStore()

const PRESETS = {
  openai: { kind: 'openai', baseUrl: 'https://api.openai.com/v1', model: 'gpt-4o-mini' },
  mistral: { kind: 'openai', baseUrl: 'https://api.mistral.ai/v1', model: 'mistral-small-latest' },
  anthropic: { kind: 'anthropic', baseUrl: 'https://api.anthropic.com', model: 'claude-haiku-5-5' },
  ollama: { kind: 'openai', baseUrl: 'http://localhost:11434/v1', model: 'llama3.1' },
  custom: { kind: 'openai', baseUrl: '', model: '' },
} as const
type PresetId = keyof typeof PRESETS

const form = reactive({ preset: 'openai' as PresetId, kind: 'openai' as 'openai' | 'anthropic', baseUrl: '', model: '', apiKey: '' })
const hasKey = ref(false)
const configured = ref(false)
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const testing = ref(false)
const result = ref<{ ok: boolean; message: string } | null>(null)
const dirty = ref(false)

const presetOptions = computed(() => (Object.keys(PRESETS) as PresetId[]).map((p) => ({ value: p, label: t(`settings.ai.presets.${p}`) })))
const host = computed(() => { try { return new URL(form.baseUrl).host } catch { return '' } })

function detect(kind: string, url: string): PresetId {
  const hit = (Object.keys(PRESETS) as PresetId[]).find((p) => p !== 'custom' && PRESETS[p].baseUrl === url.replace(/\/$/, '') && PRESETS[p].kind === kind)
  return hit ?? 'custom'
}
onMounted(async () => {
  try {
    const c = await api.settings.aiConfig()
    hasKey.value = c.hasKey
    configured.value = c.configured
    if (c.configured) Object.assign(form, { kind: c.kind, baseUrl: c.baseUrl, model: c.model, preset: detect(c.kind, c.baseUrl) })
    else Object.assign(form, { preset: 'openai', ...PRESETS.openai })
  } catch { /* le formulaire reste vide : l'utilisateur peut le remplir */ }
})
function pick(p: PresetId) {
  form.preset = p
  Object.assign(form, { kind: PRESETS[p].kind, baseUrl: PRESETS[p].baseUrl, model: PRESETS[p].model })
  dirty.value = true
  result.value = null
}
async function save(removeKey = false) {
  errors.value = {}
  busy.value = true
  try {
    const c = await api.settings.saveAiConfig({ kind: form.kind, baseUrl: form.baseUrl.trim(), model: form.model.trim(), ...(removeKey ? { apiKey: '' } : form.apiKey ? { apiKey: form.apiKey } : {}) })
    hasKey.value = c.hasKey
    configured.value = c.configured
    form.apiKey = ''
    dirty.value = false
    ui.notify(removeKey ? t('settings.ai.keyRemoved') : t('settings.ai.saved'))
  } catch (e) {
    const f = e instanceof ApiError ? String((e.data as { field?: string }).field ?? '') : ''
    if (e instanceof ApiError && f) errors.value[f] = e.message
    else ui.notify(t('common.actionFailed'), { tone: 'error' })
  } finally { busy.value = false }
}
async function test() {
  testing.value = true
  result.value = null
  try { result.value = await api.settings.testAiConfig() } catch { result.value = { ok: false, message: t('common.actionFailed') } } finally { testing.value = false }
}
const markDirty = () => { dirty.value = true; result.value = null }
</script>

<template>
  <section class="card stack ext" :aria-label="t('settings.ai.externalTitle')">
    <h3>{{ t('settings.ai.externalTitle') }}</h3>
    <p v-if="!configured" class="notice warn" role="status"><BaseIcon name="alert" :size="16" /> {{ t('settings.ai.notConfigured') }}</p>

    <SelectField id="ai-preset" :model-value="form.preset" :label="t('settings.ai.service')" :options="presetOptions" @update:model-value="(v: PresetId) => pick(v)" />
    <TextField id="ai-url" v-model="form.baseUrl" type="url" :label="t('settings.ai.baseUrl')" :hint="t('settings.ai.baseUrlHelp')" :error="errors.baseUrl" autocomplete="off" @update:model-value="markDirty" />
    <TextField id="ai-model" v-model="form.model" :label="t('settings.ai.model')" :hint="t('settings.ai.modelHelp')" :error="errors.model" @update:model-value="markDirty" />
    <PasswordField id="ai-key" v-model="form.apiKey" :label="t('settings.ai.apiKey')" autocomplete="off" :hint="hasKey ? t('settings.ai.keyKept') : form.preset === 'ollama' ? t('settings.ai.keyOptional') : t('settings.ai.keyHelp')" :error="errors.apiKey" @update:model-value="markDirty" />

    <div class="actions">
      <BaseButton variant="primary" icon="check" :disabled="busy || !form.baseUrl || !form.model" :label="t('common.save')" @click="save(false)" />
      <BaseButton icon="refresh" :disabled="testing || !configured || dirty" :label="t('settings.ai.test')" @click="test" />
      <BaseButton v-if="hasKey" variant="ghost" icon="trash" :disabled="busy" :label="t('settings.ai.removeKey')" @click="save(true)" />
    </div>
    <p v-if="dirty && configured" class="hint">{{ t('settings.ai.saveBeforeTest') }}</p>
    <p v-if="testing" class="muted" role="status">{{ t('settings.ai.testing') }}</p>
    <p v-else-if="result" :class="result.ok ? 'ok' : 'ko'" role="status"><BaseIcon :name="result.ok ? 'checkCircle' : 'alert'" :size="16" /> {{ result.message }}</p>

    <div class="sends" role="group" :aria-label="t('settings.ai.willSendTitle')">
      <h4>{{ t('settings.ai.willSendTitle') }}</h4>
      <p v-if="host">{{ t('settings.ai.processedBy', { host }) }}</p>
      <ul>
        <li><BaseIcon name="check" :size="14" /> {{ t('settings.ai.sends1') }}</li>
        <li><BaseIcon name="check" :size="14" /> {{ t('settings.ai.sends2') }}</li>
      </ul>
      <p class="muted">{{ t('settings.ai.neverSends') }}</p>
      <ul class="never">
        <li><BaseIcon name="x" :size="14" /> {{ t('settings.ai.never1') }}</li>
        <li><BaseIcon name="x" :size="14" /> {{ t('settings.ai.never2') }}</li>
        <li><BaseIcon name="x" :size="14" /> {{ t('settings.ai.never3') }}</li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.actions { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.ok, .ko { display: flex; gap: var(--space-2); align-items: flex-start; margin: 0; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); color: var(--c-text); }
.ok { background: var(--c-success-soft); } .ko { background: var(--c-danger-soft); }
.notice { display: flex; gap: var(--space-2); align-items: flex-start; margin: 0; }
.sends { display: grid; gap: var(--space-2); padding: var(--space-3) var(--space-4); border: var(--bw) dashed var(--c-border-strong); border-radius: var(--radius-md); }
.sends h4 { margin: 0; font-size: var(--fs-md); } .sends p { margin: 0; } .sends ul { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-1); }
.sends li { display: flex; gap: var(--space-2); align-items: flex-start; }
.sends li :deep(svg) { flex: none; margin-top: 4px; }
.never li :deep(svg) { color: var(--c-danger); } .sends li:not(.never li) :deep(svg) { color: var(--c-success); }
h3 { font-size: var(--fs-md); }
</style>
