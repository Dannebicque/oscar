<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../../api'
import type { MessageTemplate } from '../../api/types'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import TextareaField from '../ui/TextareaField.vue'
import TextField from '../ui/TextField.vue'

/** Modèles de message (F-19) : des textes qu'on insère à la rédaction. {prenom} : prénom du destinataire ; {moi} : ton nom. */
const { t } = useI18n()
const ui = useUiStore()
const items = ref<MessageTemplate[]>([])
const form = reactive({ id: '' as string, name: '', body: '' })
const errors = ref<Record<string, string>>({})
const busy = ref(false)

onMounted(async () => { items.value = await api.settings.templates.list().catch(() => []) })
const reset = () => { Object.assign(form, { id: '', name: '', body: '' }); errors.value = {} }
function edit(x: MessageTemplate) { Object.assign(form, { id: x.id, name: x.name, body: x.body }); errors.value = {} }
async function save() {
  errors.value = {}
  busy.value = true
  try {
    if (form.id) {
      const n = await api.settings.templates.update(form.id, form.name, form.body)
      items.value = items.value.map((x) => (x.id === n.id ? n : x))
    } else {
      items.value = [...items.value, await api.settings.templates.create(form.name, form.body)]
    }
    ui.notify(t('settings.templates.saved'))
    reset()
  } catch (e) {
    const f = e instanceof ApiError ? String((e.data as { field?: string }).field ?? '') : ''
    if (e instanceof ApiError && f) errors.value[f] = e.message
    else ui.notify(t('common.actionFailed'), { tone: 'error' })
  } finally { busy.value = false }
}
async function remove(x: MessageTemplate) {
  await api.settings.templates.remove(x.id)
  items.value = items.value.filter((y) => y.id !== x.id)
  if (form.id === x.id) reset()
  ui.notify(t('settings.templates.removed'), { tone: 'info' })
}
</script>

<template>
  <BaseCard class="stack">
    <h3>{{ t('settings.templates.title') }}</h3>
    <p class="muted">{{ t('settings.templates.help') }}</p>
    <ul v-if="items.length" class="list">
      <li v-for="x in items" :key="x.id" class="item">
        <span class="txt"><strong>{{ x.name }}</strong><small class="muted">{{ x.body }}</small></span>
        <BaseButton icon="edit" :label="t('settings.templates.edit', { name: x.name })" icon-only @click="edit(x)" />
        <BaseButton variant="ghost" icon="trash" icon-only :label="t('settings.templates.remove', { name: x.name })" @click="remove(x)" />
      </li>
    </ul>
    <p v-else class="muted">{{ t('settings.templates.none') }}</p>
    <form class="stack" novalidate @submit.prevent="save">
      <TextField id="tpl-name" v-model="form.name" :label="t('settings.templates.name')" :error="errors.name" required />
      <TextareaField id="tpl-body" v-model="form.body" :label="t('settings.templates.body')" :rows="4" :error="errors.body" :hint="t('settings.templates.vars')" required />
      <div class="acts">
        <BaseButton type="submit" variant="primary" :icon="form.id ? 'check' : 'plus'" :disabled="busy" :label="form.id ? t('common.save') : t('settings.templates.add')" />
        <BaseButton v-if="form.id" :label="t('common.cancel')" @click="reset" />
      </div>
    </form>
  </BaseCard>
</template>

<style scoped>
h3 { font-size: var(--fs-md); }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.item { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.txt { flex: 1; min-width: 0; display: grid; } .txt small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
