<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '../../stores/settings'
import BaseCard from '../ui/BaseCard.vue'

/** Comment être prévenu (F-75) : un canal par type d'événement. Défauts : application et bureau ; l'e-mail pour une connexion en échec persistante. */
const { t } = useI18n()
const settings = useSettingsStore()
const TYPES = ['replyReceived', 'relanceDue', 'invitation', 'meetingSoon', 'connectionFailed'] as const
const CHANNELS = ['app', 'desktop', 'email'] as const
const DEFAULTS: Record<string, Record<string, boolean>> = {
  replyReceived: { app: true, desktop: true, email: false }, relanceDue: { app: true, desktop: true, email: false },
  invitation: { app: true, desktop: true, email: false }, meetingSoon: { app: true, desktop: true, email: false }, connectionFailed: { app: true, desktop: true, email: true },
}
const on = (type: string, ch: string) => settings.s.notifyChannels[type]?.[ch] ?? DEFAULTS[type][ch]
function set(type: string, ch: string, v: boolean) {
  settings.s.notifyChannels = { ...settings.s.notifyChannels, [type]: { ...settings.s.notifyChannels[type], [ch]: v } }
}
</script>

<template>
  <BaseCard class="channels" aria-labelledby="ch-h">
    <h2 id="ch-h">{{ t('alerts.channelsTitle') }}</h2>
    <p class="muted">{{ t('alerts.channelsHelp') }}</p>
    <div class="scroll">
      <table>
        <caption class="sr-only">{{ t('alerts.channelsTitle') }}</caption>
        <thead>
          <tr><th scope="col">{{ t('alerts.event') }}</th><th v-for="c in CHANNELS" :key="c" scope="col">{{ t(`alerts.channel.${c}`) }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="ty in TYPES" :key="ty">
            <th scope="row">{{ t(`alerts.kinds.${ty}`) }}</th>
            <td v-for="c in CHANNELS" :key="c">
              <label class="cell"><input type="checkbox" :checked="on(ty, c)" :aria-label="`${t(`alerts.kinds.${ty}`)} : ${t(`alerts.channel.${c}`)}`" @change="set(ty, c, ($event.target as HTMLInputElement).checked)" /></label>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="hint">{{ t('alerts.channelsNote') }}</p>
  </BaseCard>
</template>

<style scoped>
.channels { display: grid; gap: var(--space-3); }
h2 { font-size: var(--fs-md); }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: var(--space-2) var(--space-3); text-align: center; border-bottom: var(--bw) solid var(--c-border); }
thead th { font-size: var(--fs-sm); color: var(--c-text-muted); }
tbody th { text-align: start; font-weight: 600; }
.cell { display: inline-flex; align-items: center; justify-content: center; min-width: var(--target); min-height: var(--target); }
.cell input { width: 22px; height: 22px; accent-color: var(--c-primary); }
</style>
