<script setup lang="ts" generic="T extends string">
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui'

/** Choix exclusif entre quelques vues ou filtres, sur Reka UI : flèches pour naviguer, un seul arrêt de tabulation. */
defineProps<{ modelValue: T; options: { value: T; label: string; count?: number; icon?: string }[]; label: string }>()
const emit = defineEmits<{ 'update:modelValue': [v: T] }>()
/** Reka permet de décocher le choix actif ; ici un choix reste toujours actif. */
const pick = (v: unknown) => { if (typeof v === 'string' && v) emit('update:modelValue', v as T) }
</script>

<template>
  <ToggleGroupRoot class="seg" type="single" :model-value="modelValue" :aria-label="label" @update:model-value="pick">
    <ToggleGroupItem v-for="o in options" :key="o.value" :value="o.value" class="seg-item">
      {{ o.label }}<span v-if="o.count !== undefined" class="count"> · {{ o.count }}</span>
    </ToggleGroupItem>
  </ToggleGroupRoot>
</template>

<style scoped>
.seg { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; background: var(--c-surface-2); border: 1px solid var(--c-border); border-radius: var(--radius-md); }
.seg-item { min-height: var(--target); padding: 0 var(--space-4); border: 1px solid transparent; border-radius: var(--radius-sm); background: transparent; font-weight: 600; color: var(--c-text-muted); cursor: pointer; }
.seg-item[data-state='on'] { background: var(--c-surface); color: var(--c-text); border-color: var(--c-border); box-shadow: var(--shadow-1); }
.count { font-weight: 400; }
</style>
