<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'

/** Pastille icône + texte : l'état n'est jamais porté par la couleur seule (U-08). */
defineProps<{ tone: 'info' | 'warn' | 'danger' | 'success' | 'neutral' | 'ai'; icon: string; label: string }>()
</script>

<template>
  <span class="chip" :class="tone"><BaseIcon :name="icon" :size="14" />{{ label }}</span>
</template>

<style scoped>
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: var(--radius-pill); font-size: var(--fs-sm); font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
.info { background: var(--c-primary-soft); color: var(--c-on-primary-soft); }
.warn { background: var(--c-warn-soft); color: var(--c-warn); }
.danger { background: var(--c-danger-soft); color: var(--c-danger); }
.success { background: var(--c-success-soft); color: var(--c-success); }
.neutral { background: var(--c-neutral-soft); color: var(--c-text-muted); }
.ai { background: var(--c-ai-soft); color: var(--c-ai); }
:root[data-theme='contrast'] .chip { border-color: currentColor; }
</style>
