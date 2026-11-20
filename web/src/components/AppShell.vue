<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import OscarMark from './OscarMark.vue'
const { t } = useI18n()
</script>

<template>
  <a class="skip" href="#main">Aller au contenu</a>
  <div class="shell">
    <nav class="rail" :aria-label="t('nav.label')">
      <div class="logo" aria-hidden="true"><OscarMark /></div>
      <RouterLink to="/suivis" class="link">{{ t('nav.suivis') }}</RouterLink>
      <RouterLink to="/reglages" class="link bottom">{{ t('nav.settings') }}</RouterLink>
    </nav>
    <main id="main" tabindex="-1"><slot /></main>
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; }
.rail { width: 88px; background: #fff; border-right: 1px solid #e4e8ee; display: flex; flex-direction: column; align-items: stretch; padding: 12px 6px; gap: 4px; }
.logo { width: 36px; height: 36px; margin: 0 auto 12px; border-radius: 10px; background: var(--blue); display: flex; align-items: center; justify-content: center; }
.link { min-height: 60px; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 12px; color: #3b4553; text-decoration: none; border-radius: 12px; }
.link.router-link-active { background: #e6eeff; color: var(--blue-dark); font-weight: 700; }
.bottom { margin-top: auto; }
main { flex: 1; padding: 24px 32px; max-width: 960px; }
</style>
