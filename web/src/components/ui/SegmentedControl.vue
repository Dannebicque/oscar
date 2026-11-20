<script setup lang="ts" generic="T extends string">
/** Groupe de boutons à bascule exclusive (aria-pressed), pas un faux onglet : il filtre ou change d'affichage. */
defineProps<{ modelValue: T; options: { value: T; label: string; count?: number; icon?: string }[]; label: string }>()
defineEmits<{ 'update:modelValue': [v: T] }>()
</script>

<template>
  <div class="seg" role="group" :aria-label="label">
    <button
      v-for="o in options" :key="o.value" type="button" :aria-pressed="o.value === modelValue" @click="$emit('update:modelValue', o.value)"
    >
      {{ o.label }}<span v-if="o.count !== undefined" class="count"> · {{ o.count }}</span>
    </button>
  </div>
</template>

<style scoped>
.seg { display: inline-flex; flex-wrap: wrap; gap: 2px; padding: 3px; background: var(--c-surface-2); border: 1px solid var(--c-border); border-radius: var(--radius-md); }
button { min-height: var(--target); padding: 0 var(--space-4); border: 1px solid transparent; border-radius: var(--radius-sm); background: transparent; font-weight: 600; color: var(--c-text-muted); }
button[aria-pressed='true'] { background: var(--c-surface); color: var(--c-text); border-color: var(--c-border); box-shadow: var(--shadow-1); }
.count { font-weight: 400; }
</style>
