<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Task } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SelectField from '../ui/SelectField.vue'
import TagsField from '../ui/TagsField.vue'
import TextField from '../ui/TextField.vue'
import TextareaField from '../ui/TextareaField.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Création et modification d'une tâche (F-30) : titre, description, échéance, priorité à 4 niveaux, projet, étiquettes. */
const props = defineProps<{ open: boolean; task: Task | null; projects: string[] }>()
const emit = defineEmits<{ close: []; save: [t: Partial<Task> & { title: string }]; remove: [t: Task] }>()
const { t } = useI18n()

const blank = () => ({ title: '', description: '', dueAt: '', priority: 3 as 1 | 2 | 3 | 4, project: '', tags: [] as string[] })
const form = ref(blank())
const submitted = ref(false)
watch(() => [props.open, props.task] as const, ([o, task]) => {
  if (!o) return
  submitted.value = false
  form.value = task ? { title: task.title, description: task.description, dueAt: task.dueAt ?? '', priority: task.priority, project: task.project ?? '', tags: [...task.tags] } : blank()
})
const priorities = computed(() => ([1, 2, 3, 4] as const).map((p) => ({ value: p, label: t(`tasks.priority.${p}`) })))
const titleError = computed(() => (submitted.value && !form.value.title.trim() ? t('tasks.titleRequired') : ''))

function submit() {
  submitted.value = true
  if (!form.value.title.trim()) return
  emit('save', {
    title: form.value.title.trim(), description: form.value.description.trim(), dueAt: form.value.dueAt || null,
    priority: form.value.priority, project: form.value.project.trim() || null, tags: [...form.value.tags], // copie simple : un proxy réactif ne se clone pas
  })
}
</script>

<template>
  <BaseDialog :open="open" :title="task ? t('tasks.editTask') : t('tasks.newTask')" @close="emit('close')">
    <form id="task-form" class="form" novalidate @submit.prevent="submit">
      <TextField v-model="form.title" :label="t('tasks.fTitle')" required :error="titleError" />
      <TextareaField v-model="form.description" :label="t('tasks.fDescription')" :rows="3" />
      <div class="two">
        <TextField v-model="form.dueAt" type="date" :label="t('tasks.fDue')" />
        <SelectField v-model="form.priority" :label="t('tasks.fPriority')" :options="priorities" />
      </div>
      <TextField v-model="form.project" :label="t('tasks.fProject')" list="task-projects" />
      <datalist id="task-projects"><option v-for="p in projects" :key="p" :value="p" /></datalist>
      <TagsField v-model="form.tags" :label="t('tasks.fTags')" :hint="t('tasks.tagsHint')" />
      <p v-if="task" class="cluster">
        <ToneChip :tone="task.origin === 'ai' ? 'ai' : 'neutral'" :icon="task.origin === 'ai' ? 'sparkles' : task.origin === 'mail' ? 'mail' : task.origin === 'event' ? 'calendar' : 'edit'" :label="t(`tasks.origin.${task.origin}`)" />
        <span v-if="task.source" class="muted">{{ t('tasks.source', { source: task.source }) }}</span>
      </p>
    </form>
    <template #footer>
      <BaseButton v-if="task" variant="danger" icon="trash" :label="t('tasks.delete')" class="del" @click="emit('remove', task)" />
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
      <BaseButton variant="primary" type="submit" form="task-form" :label="t('common.save')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.form { display: grid; gap: var(--space-4); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
.del { margin-right: auto; }
@media (max-width: 520px) { .two { grid-template-columns: 1fr; } }
</style>
