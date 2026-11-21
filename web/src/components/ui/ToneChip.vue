<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'

/** Pastille icône + texte : l'état n'est jamais porté par la couleur seule (U-08). Aspect : classe partagée .chip. */
defineProps<{ tone: 'info' | 'warn' | 'danger' | 'success' | 'neutral' | 'ai' | 'cal2'; icon: string; label: string }>()
</script>

<template>
  <span class="chip" :data-tone="tone"><BaseIcon :name="icon" :size="14" />{{ label }}</span>
</template>
