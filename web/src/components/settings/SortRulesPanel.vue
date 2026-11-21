<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../../api'
import type { SortRule } from '../../api/types'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import SelectField from '../ui/SelectField.vue'
import SwitchField from '../ui/SwitchField.vue'
import TextField from '../ui/TextField.vue'

/** Règles de tri (F-15) : déterministes, sans IA. Un expéditeur, un domaine ou un mot de l'objet range le mail dans les archives ou la corbeille (jamais supprimé). */
const { t } = useI18n()
const ui = useUiStore()
const rules = ref<SortRule[]>([])
const form = reactive({ field: 'domain' as SortRule['field'], value: '', action: 'archive' as SortRule['action'] })
const error = ref('')
const fieldOptions = computed(() => (['domain', 'sender', 'subject'] as const).map((k) => ({ value: k, label: t(`settings.sort.field.${k}`) })))
const actionOptions = computed(() => (['archive', 'trash'] as const).map((k) => ({ value: k, label: t(`settings.sort.action.${k}`) })))
onMounted(async () => { rules.value = await api.settings.sortRules.list().catch(() => []) })

async function add() {
  error.value = ''
  try {
    rules.value = [...rules.value, await api.settings.sortRules.create(form.field, form.value, form.action)]
    form.value = ''
    ui.notify(t('settings.sort.added'))
  } catch (e) { error.value = e instanceof ApiError ? e.message : t('common.actionFailed') }
}
async function toggle(r: SortRule, v: boolean) {
  const n = await api.settings.sortRules.setEnabled(r.id, v)
  rules.value = rules.value.map((x) => (x.id === r.id ? n : x))
}
async function remove(r: SortRule) {
  await api.settings.sortRules.remove(r.id)
  rules.value = rules.value.filter((x) => x.id !== r.id)
  ui.notify(t('settings.sort.removed'), { tone: 'info' })
}
const label = (r: SortRule) => t(`settings.sort.when.${r.field}`, { value: r.value }) + ' → ' + t(`settings.sort.action.${r.action}`)
</script>

<template>
  <BaseCard class="stack">
    <h3>{{ t('settings.sort.title') }}</h3>
    <p class="muted">{{ t('settings.sort.help') }}</p>
    <ul v-if="rules.length" class="list">
      <li v-for="r in rules" :key="r.id" class="rule">
        <SwitchField :model-value="r.enabled" :label="label(r)" @update:model-value="(v: boolean) => toggle(r, v)" />
        <BaseButton variant="ghost" icon="trash" icon-only :label="t('settings.sort.remove', { value: r.value })" @click="remove(r)" />
      </li>
    </ul>
    <p v-else class="muted">{{ t('settings.sort.none') }}</p>
    <form class="add" novalidate @submit.prevent="add">
      <SelectField id="sort-field" v-model="form.field" :label="t('settings.sort.fieldLabel')" :options="fieldOptions" />
      <TextField id="sort-value" v-model="form.value" :label="t('settings.sort.valueLabel')" :error="error" required />
      <SelectField id="sort-action" v-model="form.action" :label="t('settings.sort.actionLabel')" :options="actionOptions" />
      <div class="go"><BaseButton type="submit" variant="primary" icon="plus" :disabled="!form.value" :label="t('settings.sort.add')" /></div>
    </form>
    <p class="hint">{{ t('settings.sort.never') }}</p>
  </BaseCard>
</template>

<style scoped>
h3 { font-size: var(--fs-md); }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.rule { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-1) var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.add { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) minmax(0, 1fr) auto; gap: var(--space-3); align-items: start; }
.go { padding-top: 28px; }
@media (max-width: 900px) { .add { grid-template-columns: minmax(0, 1fr); } .go { padding-top: 0; } }
</style>
