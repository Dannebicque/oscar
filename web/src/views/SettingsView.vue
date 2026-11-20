<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { LOCALES, setLocale, type Locale } from '../i18n'
const { t, locale } = useI18n()
const names: Record<Locale, string> = { fr: 'Français', en: 'English' }
function onChange(e: Event) {
  setLocale((e.target as HTMLSelectElement).value as Locale)
}
</script>

<template>
  <h1>{{ t('settings.title') }}</h1>
  <label for="lang">{{ t('settings.language') }}</label>
  <select id="lang" :value="locale" aria-describedby="lang-help" @change="onChange">
    <option v-for="l in LOCALES" :key="l" :value="l">{{ names[l] }}</option>
  </select>
  <p id="lang-help">{{ t('settings.languageHelp') }}</p>
</template>

<style scoped>
label { display: block; font-weight: 600; margin-bottom: 6px; }
</style>
