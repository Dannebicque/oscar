<script setup lang="ts">
import { useI18n } from 'vue-i18n'

/** Démo publique : on dit clairement que les données sont fictives et que rien ne part. Absent de l'application réelle. */
const { t } = useI18n()
const link = import.meta.env.VITE_DEMO_LINK || '/'
</script>

<template>
  <p class="demo" role="note"><span>{{ t('demo.banner') }}</span> <a :href="link" target="_top">{{ t('demo.install') }}</a></p>
</template>

<style scoped>
.demo { position: fixed; z-index: 40; top: 8px; left: 50%; transform: translateX(-50%); margin: 0; max-width: calc(100vw - 24px); display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; justify-content: center; padding: var(--space-1) var(--space-4); background: var(--c-ai-soft); color: var(--c-text); border: var(--bw) solid var(--c-ai); border-radius: var(--radius-pill); font-size: var(--fs-sm); font-weight: 600; }
.demo a { font-weight: 800; }
@media (max-width: 720px) { .demo { top: 56px; } }
</style>
