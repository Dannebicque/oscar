<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Flag } from '../../domain/types'
import ToneChip from './ToneChip.vue'

defineProps<{ flag: Flag }>()
const { t } = useI18n()
const META: Record<Flag, { tone: 'info' | 'warn' | 'danger'; icon: string }> = {
  replyReceived: { tone: 'info', icon: 'mail' },
  late: { tone: 'danger', icon: 'alert' },
  relanceDue: { tone: 'warn', icon: 'clock' },
  dueToday: { tone: 'warn', icon: 'calendar' },
}
</script>

<template><ToneChip :tone="META[flag].tone" :icon="META[flag].icon" :label="t(`flags.${flag}`)" /></template>
