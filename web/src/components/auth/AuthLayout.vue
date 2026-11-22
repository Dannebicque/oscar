<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import OscarMark from '../OscarMark.vue'
import { useInstanceStore } from '../../stores/instance'
import BaseIcon from '../ui/BaseIcon.vue'

/**
 * Cadre des pages de connexion : un panneau de présentation sobre et un formulaire. Pas d'effets lumineux ni de motifs :
 * la page doit inspirer confiance et se lire d'un coup d'œil. Le panneau disparaît sur petit écran.
 */
defineProps<{ title: string; subtitle?: string }>()
const { t } = useI18n()
const instance = useInstanceStore()
const POINTS = [{ icon: 'follow', key: 'follow' }, { icon: 'mail', key: 'mail' }, { icon: 'calendar', key: 'agenda' }] as const
</script>

<template>
  <div class="auth">
    <aside class="brand" aria-hidden="false">
      <p class="mark"><span class="logo"><OscarMark /></span><span class="word">{{ instance.name.toLowerCase() }}</span></p>
      <div class="pitch">
        <p class="tagline">{{ t('app.tagline') }}</p>
        <ul>
          <li v-for="p in POINTS" :key="p.key"><BaseIcon :name="p.icon" :size="20" /><span>{{ t(`auth.points.${p.key}`) }}</span></li>
        </ul>
      </div>
      <p class="foot">{{ t('auth.foot') }}</p>
    </aside>
    <main id="main" class="formside">
      <div class="form-wrap">
        <p class="mark small"><span class="logo"><OscarMark /></span><span class="word">{{ instance.name.toLowerCase() }}</span></p>
        <h1>{{ title }}</h1>
        <p v-if="subtitle" class="muted lead">{{ subtitle }}</p>
        <slot />
        <div v-if="$slots.alt" class="alt"><slot name="alt" /></div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.auth { min-height: 100dvh; display: grid; grid-template-columns: minmax(0, 1fr); background: var(--c-bg); }
@media (min-width: 900px) { .auth { grid-template-columns: minmax(320px, 5fr) minmax(0, 7fr); } }
.brand { display: none; }
@media (min-width: 900px) {
  .brand { display: flex; flex-direction: column; justify-content: space-between; gap: var(--space-6); padding: var(--space-7, 48px); background: var(--c-surface-2); border-inline-end: var(--bw) solid var(--c-border); }
}
.mark { display: flex; align-items: center; gap: var(--space-2); margin: 0; }
.mark.small { margin-bottom: var(--space-5); }
@media (min-width: 900px) { .mark.small { display: none; } }
.logo { width: 36px; height: 36px; border-radius: var(--radius-md); background: var(--c-primary); color: var(--c-on-primary); display: inline-flex; align-items: center; justify-content: center; }
.word { font-weight: 800; font-size: 1.25rem; letter-spacing: -0.02em; }
.tagline { font-size: clamp(1.5rem, 2.4vw, 2.1rem); font-weight: 800; letter-spacing: -0.02em; line-height: 1.15; margin: 0 0 var(--space-5); max-width: 16ch; }
.pitch ul { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.pitch li { display: flex; align-items: center; gap: var(--space-3); color: var(--c-text-muted); }
.pitch li :deep(svg) { color: var(--c-primary); flex: none; }
.foot { margin: 0; font-size: var(--fs-sm); color: var(--c-text-muted); }
.formside { display: grid; place-items: center; padding: var(--space-5) var(--space-4); }
.form-wrap { width: min(420px, 100%); display: grid; gap: var(--space-4); align-content: start; }
h1 { font-size: 1.75rem; letter-spacing: -0.02em; margin: 0; }
.lead { margin: calc(var(--space-2) * -1) 0 0; }
.alt { display: grid; gap: var(--space-2); padding-top: var(--space-4); border-top: var(--bw) solid var(--c-border); font-size: var(--fs-sm); }
</style>
