<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import CheckboxField from '../ui/CheckboxField.vue'

/** Avant la corbeille : une confirmation claire, sans jamais parler de suppression définitive (le serveur garde le message). */
const props = defineProps<{ open: boolean; count: number; withSuivi: number }>()
const emit = defineEmits<{ confirm: [dontAsk: boolean]; close: [] }>()
const { t } = useI18n()
const dontAsk = ref(false)
watch(() => props.open, (o) => { if (o) dontAsk.value = false })
</script>

<template>
  <BaseDialog :open="open" :title="t('confirmTrash.title', { n: count }, count)" @close="emit('close')">
    <div class="ct">
      <p>{{ t('confirmTrash.text', { n: count }, count) }}</p>
      <p v-if="withSuivi" class="callout" data-tone="warn">{{ t('confirmTrash.suivi', { n: withSuivi }, withSuivi) }}</p>
      <CheckboxField v-model="dontAsk" :label="t('confirmTrash.dontAsk')">{{ t('confirmTrash.dontAsk') }}</CheckboxField>
    </div>
    <template #footer>
      <BaseButton variant="danger" icon="trash" :label="t('confirmTrash.confirm')" @click="emit('confirm', dontAsk)" />
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.ct { display: grid; gap: var(--space-3); }
</style>
