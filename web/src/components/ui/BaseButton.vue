<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'

withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  icon?: string
  /** Bouton à icône seule : `label` devient son nom accessible. */
  iconOnly?: boolean
  label?: string
  type?: 'button' | 'submit'
  pressed?: boolean
  expanded?: boolean
  disabled?: boolean
}>(), { variant: 'secondary', type: 'button' })
</script>

<template>
  <button
    :type="type" class="btn" :class="[variant, { icon: iconOnly }]" :disabled="disabled"
    :aria-label="iconOnly ? label : undefined" :aria-pressed="pressed" :aria-expanded="expanded"
  >
    <BaseIcon v-if="icon" :name="icon" :size="18" />
    <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
  </button>
</template>

<style scoped>
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); min-height: var(--target); min-width: var(--target);
  padding: 0 var(--space-4); border-radius: var(--radius-sm); border: 1px solid var(--c-border); background: var(--c-surface);
  color: var(--c-text); font-weight: 600; transition: background var(--dur) var(--ease), border-color var(--dur) var(--ease);
}
.btn:hover:not(:disabled) { background: var(--c-surface-2); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }
.btn.icon { padding: 0; }
.primary { background: var(--c-primary); border-color: var(--c-primary); color: var(--c-on-primary); }
.primary:hover:not(:disabled) { background: var(--c-primary-hover); }
.ghost { background: transparent; border-color: transparent; }
.danger { color: var(--c-danger); border-color: var(--c-danger); }
.btn[aria-pressed='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-color: var(--c-primary); }
</style>
