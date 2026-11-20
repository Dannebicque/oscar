<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import BaseButton from './BaseButton.vue'
import BaseIcon from './BaseIcon.vue'

/** Ton : une erreur d'abord claire, sans humour, avec une action (§5, F-79). */
defineEmits<{ retry: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="error" role="alert">
    <BaseIcon name="alert" :size="24" />
    <div>
      <h2>{{ t('common.errorTitle') }}</h2>
      <p>{{ t('common.errorHelp') }}</p>
    </div>
    <BaseButton variant="primary" icon="refresh" :label="t('common.retry')" @click="$emit('retry')" />
  </div>
</template>

<style scoped>
.error { display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap; padding: var(--space-5); border: 1px solid var(--c-danger); background: var(--c-danger-soft); color: var(--c-danger); border-radius: var(--radius-md); }
.error > div { flex: 1; min-width: 220px; color: var(--c-text); }
h2 { font-size: var(--fs-md); }
</style>
