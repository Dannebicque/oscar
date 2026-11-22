<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { useSettingsStore } from '../../stores/settings'
import BaseButton from './BaseButton.vue'
import Callout from './Callout.vue'

/** Mode congés : rappelle que les relances sont en pause et jusqu'à quand. Rien n'est fermé ni supprimé. */
const { t } = useI18n()
const fmt = useFormat()
const settings = useSettingsStore()
const active = computed(() => settings.vacationActive(clock.now()))
</script>

<template>
  <Callout v-if="active" tone="info" icon="clock" :level="2" :title="t('vacation.bannerTitle')">
    <p>{{ t('vacation.banner', { date: fmt.dateLong(settings.s.vacationTo) }) }}</p>
    <template #actions>
      <BaseButton :label="t('vacation.stop')" @click="settings.stopVacation()" />
      <RouterLink class="btn" to="/reglages#set-relances">{{ t('vacation.settings') }}</RouterLink>
    </template>
  </Callout>
</template>
