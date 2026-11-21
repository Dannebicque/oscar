<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { folderTree } from '../../domain/folders'
import { useMailsStore } from '../../stores/mails'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SelectField from '../ui/SelectField.vue'
import TextField from '../ui/TextField.vue'

/** Créer un dossier (ou un sous-dossier) ou renommer un dossier personnel. Les dossiers sont ceux du serveur de messagerie. */
const props = defineProps<{ open: boolean; mode: 'create' | 'rename'; /** chemin du dossier à renommer */ path?: string; /** parent proposé à la création */ parent?: string }>()
const emit = defineEmits<{ close: []; done: [path: string] }>()
const { t } = useI18n()
const mails = useMailsStore()
const ui = useUiStore()
const name = ref('')
const parent = ref('')
const error = ref('')
const busy = ref(false)
watch(() => props.open, (o) => {
  if (!o) return
  error.value = ''
  parent.value = props.parent ?? ''
  const f = mails.folders.find((x) => x.path === props.path)
  name.value = props.mode === 'rename' ? f?.name ?? '' : ''
})
const parents = computed(() => [{ value: '', label: t('folders.topLevel') }, ...folderTree(mails.folders).map((f) => ({ value: f.path, label: `${'— '.repeat(f.depth)}${f.name}` }))])

async function save() {
  const n = name.value.trim()
  if (!n) { error.value = t('folders.nameRequired'); return }
  busy.value = true
  error.value = ''
  try {
    if (props.mode === 'create') {
      await mails.createFolder(n, parent.value || undefined)
      ui.notify(t('folders.created', { name: n }))
      emit('done', parent.value ? `${parent.value}${mails.folders.find((x) => x.path === parent.value)?.delimiter ?? '/'}${n}` : n)
    } else if (props.path) {
      const r = await mails.renameFolder(props.path, n)
      ui.notify(t('folders.renamed', { name: n }))
      emit('done', r.path)
    }
  } catch (e) {
    error.value = e instanceof Error && e.message ? e.message : t('common.actionFailed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <BaseDialog :open="open" :title="t(mode === 'create' ? 'folders.newTitle' : 'folders.renameTitle')" @close="emit('close')">
    <form class="fd" novalidate @submit.prevent="save">
      <TextField id="folder-name" v-model="name" :label="t('folders.name')" :error="error" required />
      <SelectField v-if="mode === 'create'" id="folder-parent" v-model="parent" :label="t('folders.parent')" :options="parents" />
      <p class="hint">{{ t('folders.help') }}</p>
    </form>
    <template #footer>
      <BaseButton variant="primary" :disabled="busy" :label="t(mode === 'create' ? 'folders.create' : 'folders.rename')" @click="save" />
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.fd { display: grid; gap: var(--space-3); }
</style>
