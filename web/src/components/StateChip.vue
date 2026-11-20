<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SuiviState } from '../domain/types'
defineProps<{ state: SuiviState }>()
const { t } = useI18n()
const COLORS: Record<SuiviState, [string, string]> = {
  toHandle: ['#e6eeff', '#1d44a8'],
  relanceDue: ['#fef0c7', '#93370d'],
  late: ['#fee4e2', '#b42318'],
  waiting: ['#eef1f5', '#3b4553'],
  done: ['#dcfae6', '#067647'],
}
</script>

<template>
  <!-- Le type est toujours dit en texte : la couleur ne porte jamais seule le sens. -->
  <span class="chip" :style="{ background: COLORS[state][0], color: COLORS[state][1] }">{{ t(`state.${state}`) }}</span>
</template>

<style scoped>
.chip { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
</style>
