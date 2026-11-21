<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'

/** Bandeau de message avec icône, titre, texte et actions (info, attention, danger, succès, IA). Aspect : .callout. */
/** `level` : niveau du titre (2 sous le titre de page, 3 dans un article qui a déjà son h2) : l'ordre des titres reste logique. */
withDefaults(defineProps<{ tone?: 'info' | 'warn' | 'danger' | 'success' | 'ai' | 'neutral'; icon?: string; title?: string; level?: 2 | 3 }>(), { tone: 'info', level: 3 })
</script>

<template>
  <section class="callout" :data-tone="tone">
    <BaseIcon v-if="icon" :name="icon" :size="22" />
    <div class="callout-body">
      <component :is="`h${level}`" v-if="title" class="callout-title">{{ title }}</component>
      <slot />
    </div>
    <div v-if="$slots.actions" class="callout-actions"><slot name="actions" /></div>
  </section>
</template>
