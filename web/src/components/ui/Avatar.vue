<script setup lang="ts">
import { computed } from 'vue'

/** Initiales dans un rond, couleur dérivée du nom (stable) : repère visuel standard des clients de messagerie. Décoratif. */
const props = defineProps<{ name: string; size?: number }>()
const initials = computed(() => props.name.replace(/[<>@].*$/, '').trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('') || '?')
const hue = computed(() => [...props.name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 7))
</script>

<template>
  <span class="avatar" aria-hidden="true" :style="{ width: `${size ?? 36}px`, height: `${size ?? 36}px`, fontSize: `${(size ?? 36) * 0.38}px`, '--av-h': hue }">{{ initials }}</span>
</template>

<style scoped>
/* Teinte propre à chaque personne, mais fond et texte restent issus du thème (donc lisibles dans tous les modes). */
.avatar { background: color-mix(in srgb, hsl(var(--av-h) 70% 50%) 22%, var(--c-surface)); color: var(--c-text); border: var(--bw) solid color-mix(in srgb, hsl(var(--av-h) 70% 50%) 55%, var(--c-surface)); }
</style>
