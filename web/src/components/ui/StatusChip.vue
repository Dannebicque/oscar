<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Status } from '../../domain/types'
import ToneChip from './ToneChip.vue'

defineProps<{ status: Status }>()
const { t } = useI18n()
const META: Record<Status, { tone: 'info' | 'warn' | 'neutral' | 'success'; icon: string }> = {
  new: { tone: 'info', icon: 'star' },
  inProgress: { tone: 'warn', icon: 'edit' },
  waiting: { tone: 'neutral', icon: 'hourglass' },
  done: { tone: 'success', icon: 'checkCircle' },
}
</script>

<template><ToneChip :tone="META[status].tone" :icon="META[status].icon" :label="t(`states.${status}`)" /></template>
