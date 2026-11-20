<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ToneChip from './ToneChip.vue'
const { t } = useI18n()
</script>

<template><ToneChip tone="ai" icon="sparkles" :label="t('common.aiBadge')" /></template>
