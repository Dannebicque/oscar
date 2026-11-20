<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'
defineProps<{ icon?: string; title?: string; text: string }>()
</script>

<template>
  <div class="empty">
    <BaseIcon :name="icon ?? 'checkCircle'" :size="28" />
    <h2 v-if="title">{{ title }}</h2>
    <p>{{ text }}</p>
    <slot />
  </div>
</template>

<style scoped>
.empty { display: grid; justify-items: center; gap: var(--space-2); text-align: center; padding: var(--space-6); color: var(--c-text-muted); border: 1px dashed var(--c-border); border-radius: var(--radius-md); }
h2 { color: var(--c-text); font-size: var(--fs-md); }
</style>
