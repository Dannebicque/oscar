<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ToneChip from './ToneChip.vue'

/**
 * Tout ce qu'Oscar déduit, prépare ou propose est violet et porte cette pastille. La mention « IA » seulement quand un modèle de langage
 * a réellement produit le contenu (`ai`) : des règles de calcul ne sont pas de l'IA.
 */
defineProps<{ ai?: boolean }>()
const { t } = useI18n()
</script>

<template><ToneChip tone="ai" :icon="ai ? 'sparkles' : 'bulb'" :label="t(ai ? 'common.aiBadge' : 'common.autoBadge')" /></template>
