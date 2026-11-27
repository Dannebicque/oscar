<script setup lang="ts">
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import BaseIcon from './BaseIcon.vue'

/** Case à cocher sur Reka UI (role="checkbox", Espace, état annoncé) ; la zone cliquable fait au moins 44 px. */
defineProps<{ modelValue: boolean; label: string; id?: string; disabled?: boolean; hideLabel?: boolean }>()
defineEmits<{ 'update:modelValue': [v: boolean] }>()
const uid = `cb-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <span class="cb-wrap">
    <CheckboxRoot :id="id ?? uid" class="cb-root" :model-value="modelValue" :disabled="disabled" :aria-label="hideLabel ? label : undefined" @update:model-value="(v: boolean | 'indeterminate') => $emit('update:modelValue', v === true)">
      <CheckboxIndicator class="cb-ind"><BaseIcon name="check" :size="16" /></CheckboxIndicator>
    </CheckboxRoot>
    <label v-if="!hideLabel" :for="id ?? uid" class="cb-label"><slot>{{ label }}</slot></label>
  </span>
</template>

<style scoped>
.cb-wrap { display: inline-flex; align-items: center; gap: var(--space-2); min-height: var(--target); }
.cb-root::after { content: ''; position: absolute; inset: -12px; }
.cb-root { position: relative; flex: none; width: 24px; height: 24px; margin: 10px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 2px solid var(--c-text-muted); border-radius: 6px; background: var(--c-surface); color: var(--c-on-primary); cursor: pointer; }
.cb-root[data-state='checked'] { background: var(--c-primary); border-color: var(--c-primary); }
.cb-root[data-disabled] { opacity: 0.5; cursor: not-allowed; }
.cb-label { cursor: pointer; }
</style>
