<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import BaseButton from './BaseButton.vue'
import Callout from './Callout.vue'

/** Ton : une erreur d'abord claire, sans humour, avec une action (§5, F-79). */
defineEmits<{ retry: [] }>()
const { t } = useI18n()
</script>

<template>
  <Callout :level="2" tone="danger" icon="alert" :title="t('common.errorTitle')" role="alert">
    <p>{{ t('common.errorHelp') }}</p>
    <template #actions><BaseButton variant="primary" icon="refresh" :label="t('common.retry')" @click="$emit('retry')" /></template>
  </Callout>
</template>
