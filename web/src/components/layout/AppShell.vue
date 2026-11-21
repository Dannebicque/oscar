<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAccountsStore } from '../../stores/accounts'
import { useAuthStore } from '../../stores/auth'
import { useSettingsStore } from '../../stores/settings'
import { useSwipeNav } from '../../composables/useSwipeNav'
import { useShortcuts } from '../../composables/useShortcuts'
import { useSystemStore } from '../../stores/system'
import OfflineBanner from './OfflineBanner.vue'
import QuickCaptureDialog from './QuickCaptureDialog.vue'
import { onNative } from '../../desktop/bridge'
import { useDesktopNotifications } from '../../desktop/useDesktopNotifications'
import { useUiStore } from '../../stores/ui'
import ShortcutsDialog from './ShortcutsDialog.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import ToastHost from '../ui/ToastHost.vue'
import OscarMark from '../OscarMark.vue'
import { session } from '../../api'
import { MAIN_NAV } from './NavLinks'
import StatusBar from './StatusBar.vue'

const { t } = useI18n()
const route = useRoute()
const system = useSystemStore()
const auth = useAuthStore()
const settings = useSettingsStore()
const accounts = useAccountsStore()
const router = useRouter()
useShortcuts()
useSwipeNav()
useDesktopNotifications()
const ui = useUiStore()
/** Bureau : le raccourci global et le menu de la barre système ouvrent la capture rapide. */
let offCapture: (() => void) | undefined
onMounted(async () => { offCapture = await onNative('oscar://quick-capture', () => { ui.quickCaptureOpen = true }) })
onBeforeUnmount(() => offCapture?.())

/** Charge les données de la session, puis les rafraîchit chaque minute (le temps réel viendra avec Mercure). */
let poll: ReturnType<typeof setInterval> | undefined
async function start() {
  if (!auth.authenticated) return
  await settings.loadFromServer()
  await Promise.all([system.load(), accounts.load()])
  clearInterval(poll)
  poll = setInterval(() => { if (document.visibilityState === 'visible') void system.load() }, 60_000)
}
onMounted(start)
onBeforeUnmount(() => clearInterval(poll))
watch(() => auth.authenticated, (ok) => { if (ok) void start(); else { clearInterval(poll); router.replace({ name: 'login' }) } })
async function signOut() {
  await auth.logout()
  router.replace({ name: 'login' })
}

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
        <span v-if="n.name === 'suivis'" class="orb" aria-hidden="true"><BaseIcon :name="n.icon" :size="20" /></span><BaseIcon v-else :name="n.icon" :size="22" /><span>{{ t(n.label) }}</span>
      </RouterLink>
      <RouterLink to="/alertes" class="link wide spacer" :class="{ current: route.name === 'alerts' }" :aria-current="route.name === 'alerts' ? 'page' : undefined" :aria-label="t('nav.alertsCount', { n: system.unreadCount }, system.unreadCount)">
        <BaseIcon name="bell" :size="22" /><span>{{ t('nav.alerts') }}</span><span v-if="system.unreadCount" class="badge" aria-hidden="true">{{ system.unreadCount }}</span>
      </RouterLink>
      <RouterLink to="/reglages" class="link wide" :class="{ current: route.name === 'settings' || route.name === 'journal' }" :aria-current="route.name === 'settings' ? 'page' : undefined">
        <BaseIcon name="settings" :size="22" /><span>{{ t('nav.settings') }}</span>
      </RouterLink>
      <button v-if="session.token" type="button" class="link wide out" :aria-label="t('nav.signOut')" @click="signOut"><BaseIcon name="lock" :size="22" /><span>{{ t('nav.signOutShort') }}</span></button>
    </nav>

    <main id="main" tabindex="-1" :class="{ bleed: route.meta.bleed }"><OfflineBanner class="ob" /><div class="page"><RouterView /></div></main>
    <StatusBar />
  </div>
  <ShortcutsDialog />
  <QuickCaptureDialog />
  <ToastHost />
</template>

<style scoped>
.skip { position: absolute; left: var(--space-2); top: -80px; z-index: 100; background: var(--c-surface); color: var(--c-text); border: 2px solid var(--c-focus); padding: var(--space-3) var(--space-4); border-radius: var(--radius-sm); }
.skip:focus { top: var(--space-2); }
.skip + .skip:focus { left: 200px; }

/* L'application occupe la fenêtre : la zone centrale défile, la barre d'état reste visible (standard des clients de messagerie). */
.shell { display: grid; grid-template-columns: var(--rail-w) minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) auto; grid-template-areas: 'nav main' 'status status'; height: 100dvh; }
.topbar { display: none; }
.nav { grid-area: nav; min-height: 0; overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-3) var(--space-2); background: var(--c-surface); border-right: var(--bw) solid var(--c-border); }
main { grid-area: main; padding: var(--space-5) var(--space-5); min-width: 0; min-height: 0; overflow-y: auto; scroll-padding-top: var(--space-5); }
.page { width: 100%; }
.ob { margin: calc(var(--space-5) * -1) calc(var(--space-5) * -1) var(--space-4); }
main.bleed .ob { margin: 0; }
main.bleed { padding: 0; overflow: hidden; }
main.bleed .page { max-width: none; height: 100%; margin: 0; }
.brand { display: flex; align-items: center; gap: var(--space-2); text-decoration: none; color: var(--c-text); font-weight: 800; }
.rail-brand { justify-content: center; margin-bottom: var(--space-3); }
.logo { width: 38px; height: 38px; border-radius: var(--radius-md); background: var(--c-primary); display: inline-flex; align-items: center; justify-content: center; color: var(--c-on-primary); box-shadow: var(--glow); }
.link { box-sizing: border-box; width: 100%; min-width: 0; padding: var(--space-1) 2px; position: relative; min-height: 62px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; text-align: center; font-size: 0.75rem; color: var(--c-text-muted); text-decoration: none; border-radius: var(--radius-md); font-weight: 600; border: var(--bw) solid transparent; }
.link:hover { background: var(--c-surface-2); }
.link.current { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; border-color: color-mix(in srgb, var(--c-accent) 40%, transparent); }
.link.center { color: var(--c-text); font-weight: 800; }
.orb { width: 36px; height: 36px; border-radius: 50%; background: var(--c-primary); color: var(--c-on-primary); display: inline-flex; align-items: center; justify-content: center; box-shadow: var(--glow); }
.link.center:hover .orb { background: var(--c-primary-hover); }
.link > span:not(.orb):not(.badge) { max-width: 100%; text-align: center; line-height: 1.15; overflow-wrap: anywhere; hyphens: auto; }
.link > :deep(svg), .link > .orb { flex: none; }
.spacer { margin-top: auto; }
:root[data-style='neon'][data-theme='dark'] .link.current { box-shadow: var(--glow-soft); text-shadow: var(--text-glow); }
.out { border: 0; background: transparent; padding-inline: 2px; font: inherit; font-size: 0.75rem; cursor: pointer; }
.badge { position: absolute; top: 6px; right: 14px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px; background: var(--c-danger-solid); color: var(--c-on-danger-solid); font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
:root[data-theme='contrast'] .badge { background: var(--c-text); color: var(--c-bg); }

@media (max-width: 720px) {
  /* La barre du bas et la barre d'état restent visibles : seule la zone centrale défile (U-01). */
  .shell { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr) auto auto; grid-template-areas: 'top' 'main' 'nav' 'status'; }
  .topbar { grid-area: top; display: flex; align-items: center; justify-content: space-between; padding: var(--space-2) var(--space-4); background: var(--c-surface); border-bottom: 1px solid var(--c-border); }
  .top-actions { display: flex; gap: var(--space-1); }
  .icon-link { position: relative; width: var(--target); height: var(--target); display: inline-flex; align-items: center; justify-content: center; color: var(--c-text); border-radius: var(--radius-sm); }
  .icon-link .badge { top: 2px; right: 0; }
  .word { font-size: var(--fs-lg); }
  .nav { position: static; height: auto; flex-direction: row; justify-content: space-around; padding: var(--space-1) var(--space-2); border-right: 0; border-top: 1px solid var(--c-border); }
  .rail-brand, .wide { display: none; }
  .link { flex: 1; min-height: var(--target); }
  .link.current:not(.center) { box-shadow: inset 0 -4px 0 var(--c-primary); }
  .link.center { margin-top: -14px; background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-radius: var(--radius-lg); border-color: var(--c-primary); box-shadow: var(--shadow-1); }
  .link.center .orb { background: transparent; box-shadow: none; width: auto; height: auto; color: inherit; }
  .link.center.current { background: var(--c-primary); color: var(--c-on-primary); }
  main { padding: var(--space-4); }
  main.bleed { padding: 0; }
}
@media (min-width: 721px) { .word { display: none; } .topbar { display: none; } }
</style>
