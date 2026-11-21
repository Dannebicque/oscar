<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOutbox } from '../../composables/useOutbox'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Hors ligne (F-80, N-09) : on consulte ce qui a déjà été chargé, les messages écrits patientent. Annoncé, sans voler le focus. */
const { t } = useI18n()
const { online, queued, flush } = useOutbox()
const show = computed(() => !online.value || queued.value > 0)
</script>

<template>
  <div v-if="show" class="offline" role="status" aria-live="polite">
    <BaseIcon :name="online ? 'send' : 'alert'" :size="16" />
    <span>
      <template v-if="!online">{{ t('offline.banner') }}</template>
      <template v-else>{{ t('offline.back') }}</template>
      <template v-if="queued"> {{ t('offline.queue', { n: queued }, queued) }}</template>
    </span>
    <BaseButton v-if="online && queued" icon="send" :label="t('offline.sendNow')" @click="flush" />
  </div>
</template>

<style scoped>
.offline { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; padding: var(--space-2) var(--space-4); background: var(--c-warn-soft); color: var(--c-text); border-bottom: var(--bw) solid var(--c-warn); font-weight: 600; }
.offline span { flex: 1; min-width: 200px; }
</style>
