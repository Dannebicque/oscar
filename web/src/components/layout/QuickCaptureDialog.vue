<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOscar } from '../../oscar/useOscar'
import { useTasksStore } from '../../stores/tasks'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import TextField from '../ui/TextField.vue'

/** Capture rapide (N-03) : une tâche en deux gestes, depuis n'importe quel écran. Raccourci « q », ou le raccourci global du bureau. */
const { t } = useI18n()
const ui = useUiStore()
const tasks = useTasksStore()
const { say } = useOscar()
const title = ref('')
const busy = ref(false)
watch(() => ui.quickCaptureOpen, (o) => { if (o) title.value = '' })

async function save() {
  const v = title.value.trim()
  if (!v) return
  busy.value = true
  try {
    await tasks.add({ title: v })
    ui.quickCaptureOpen = false
    ui.notify(say('taskAdded', { title: v }))
  } catch { ui.notify(t('tasks.saveFailed'), { tone: 'error' }) } finally { busy.value = false }
}
</script>

<template>
  <BaseDialog :open="ui.quickCaptureOpen" :title="t('quick.title')" :description="t('quick.help')" @close="ui.quickCaptureOpen = false">
    <form id="quick-form" @submit.prevent="save">
      <TextField id="quick-title" v-model="title" :label="t('tasks.fTitle')" :placeholder="t('home.quickPlaceholder')" required />
    </form>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="ui.quickCaptureOpen = false" />
      <BaseButton variant="primary" type="submit" form="quick-form" icon="plus" :disabled="busy || !title.trim()" :label="t('common.add')" />
    </template>
  </BaseDialog>
</template>
