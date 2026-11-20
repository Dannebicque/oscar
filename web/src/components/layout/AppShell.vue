<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useSystemStore } from '../../stores/system'
import BaseIcon from '../ui/BaseIcon.vue'
import ToastHost from '../ui/ToastHost.vue'
import OscarMark from '../OscarMark.vue'
import { MAIN_NAV } from './NavLinks'
import StatusBar from './StatusBar.vue'

const { t } = useI18n()
const route = useRoute()
const system = useSystemStore()
onMounted(() => system.load())

/** aria-current sur la rubrique active, y compris pour les sous-routes (/mails/:id). */
const isCurrent = (name: string) => route.name === name || (name === 'mails' && route.name === 'compose')
</script>

<template>
  <a class="skip" href="#main">{{ t('app.skip') }}</a>
  <a class="skip" href="#nav">{{ t('app.skipNav') }}</a>
  <div class="shell">
    <header class="topbar">
      <RouterLink to="/" class="brand" :aria-label="t('app.name')"><span class="logo"><OscarMark /></span><span class="word">oscar</span></RouterLink>
      <div class="top-actions">
        <RouterLink to="/alertes" class="icon-link" :aria-label="t('nav.alertsCount', { n: system.unreadCount }, system.unreadCount)">
          <BaseIcon name="bell" /><span v-if="system.unreadCount" class="badge" aria-hidden="true">{{ system.unreadCount }}</span>
        </RouterLink>
        <RouterLink to="/reglages" class="icon-link" :aria-label="t('nav.settings')"><BaseIcon name="settings" /></RouterLink>
      </div>
    </header>

    <nav id="nav" class="nav" :aria-label="t('nav.label')" tabindex="-1">
      <RouterLink to="/" class="brand rail-brand" :aria-label="t('app.name')"><span class="logo"><OscarMark /></span></RouterLink>
      <RouterLink
        v-for="n in MAIN_NAV" :key="n.name" :to="n.to" class="link" :class="{ center: n.name === 'suivis', current: isCurrent(n.name) }"
        :aria-current="isCurrent(n.name) ? 'page' : undefined"
      >
        <BaseIcon :name="n.icon" :size="22" /><span>{{ t(n.label) }}</span>
      </RouterLink>
      <RouterLink to="/alertes" class="link wide spacer" :class="{ current: route.name === 'alerts' }" :aria-current="route.name === 'alerts' ? 'page' : undefined" :aria-label="t('nav.alertsCount', { n: system.unreadCount }, system.unreadCount)">
        <BaseIcon name="bell" :size="22" /><span>{{ t('nav.alerts') }}</span><span v-if="system.unreadCount" class="badge" aria-hidden="true">{{ system.unreadCount }}</span>
      </RouterLink>
      <RouterLink to="/reglages" class="link wide" :class="{ current: route.name === 'settings' || route.name === 'journal' }" :aria-current="route.name === 'settings' ? 'page' : undefined">
        <BaseIcon name="settings" :size="22" /><span>{{ t('nav.settings') }}</span>
      </RouterLink>
    </nav>

    <main id="main" tabindex="-1"><RouterView /></main>
    <StatusBar />
  </div>
  <ToastHost />
</template>

<style scoped>
.skip { position: absolute; left: var(--space-2); top: -80px; z-index: 100; background: var(--c-surface); color: var(--c-text); border: 2px solid var(--c-focus); padding: var(--space-3) var(--space-4); border-radius: var(--radius-sm); }
.skip:focus { top: var(--space-2); }
.skip + .skip:focus { left: 200px; }

.shell { display: grid; grid-template-columns: var(--rail-w) minmax(0, 1fr); grid-template-rows: 1fr auto; grid-template-areas: 'nav main' 'status status'; min-height: 100vh; }
.topbar { display: none; }
.nav { grid-area: nav; position: sticky; top: 0; align-self: start; height: calc(100vh - 44px); display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-3) var(--space-2); background: var(--c-surface); border-right: 1px solid var(--c-border); }
main { grid-area: main; padding: var(--space-5) var(--space-6); min-width: 0; max-width: calc(var(--content-w) + 2 * var(--space-6)); width: 100%; margin: 0 auto; }
.brand { display: flex; align-items: center; gap: var(--space-2); text-decoration: none; color: var(--c-text); font-weight: 800; }
.rail-brand { justify-content: center; margin-bottom: var(--space-3); }
.logo { width: 36px; height: 36px; border-radius: 10px; background: var(--c-primary); display: inline-flex; align-items: center; justify-content: center; color: var(--c-on-primary); }
.link { position: relative; min-height: 64px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; text-align: center; font-size: 0.75rem; color: var(--c-text-muted); text-decoration: none; border-radius: var(--radius-md); font-weight: 600; }
.link:hover { background: var(--c-surface-2); }
.link.current { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; }
.spacer { margin-top: auto; }
.badge { position: absolute; top: 6px; right: 14px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px; background: var(--c-danger); color: #fff; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
:root[data-theme='contrast'] .badge { background: #fff; color: #000; }

@media (max-width: 720px) {
  .shell { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto 1fr auto auto; grid-template-areas: 'top' 'main' 'nav' 'status'; }
  .topbar { grid-area: top; display: flex; align-items: center; justify-content: space-between; padding: var(--space-2) var(--space-4); background: var(--c-surface); border-bottom: 1px solid var(--c-border); }
  .top-actions { display: flex; gap: var(--space-1); }
  .icon-link { position: relative; width: var(--target); height: var(--target); display: inline-flex; align-items: center; justify-content: center; color: var(--c-text); border-radius: var(--radius-sm); }
  .icon-link .badge { top: 2px; right: 0; }
  .word { font-size: var(--fs-lg); }
  .nav { position: sticky; bottom: 0; top: auto; height: auto; flex-direction: row; justify-content: space-around; padding: var(--space-1) var(--space-2); border-right: 0; border-top: 1px solid var(--c-border); }
  .rail-brand, .wide { display: none; }
  .link { flex: 1; min-height: var(--target); }
  .link.center { margin-top: -14px; background: var(--c-primary); color: var(--c-on-primary); border-radius: var(--radius-lg); box-shadow: var(--shadow-1); }
  .link.center.current { background: var(--c-primary-hover); color: var(--c-on-primary); }
  main { padding: var(--space-4); }
}
@media (min-width: 721px) { .word { display: none; } .topbar { display: none; } }
</style>
