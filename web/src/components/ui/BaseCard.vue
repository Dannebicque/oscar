<script setup lang="ts">
/** Carte : un seul composant pour tous les blocs de contenu. L'aspect est celui de la classe partagée .card. */
withDefaults(defineProps<{ as?: string; flat?: boolean; pad?: 'md' | 'sm' | 'none'; clickable?: boolean }>(), { as: 'section', pad: 'md' })
</script>

<template>
  <component :is="as" class="card" :class="{ flat, 'pad-sm': pad === 'sm', 'pad-0': pad === 'none', clickable }"><slot /></component>
</template>
