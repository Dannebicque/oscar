<script setup lang="ts">
import { useI18n } from 'vue-i18n'

export type KpiId = 'todo' | 'relance' | 'late' | 'wait' | 'done'
/** Tuiles de situation cliquables (maquette « centre de suivi ») : un clic filtre la liste, un second clic retire le filtre. */
defineProps<{ counts: Record<KpiId, number> }>()
const model = defineModel<KpiId | null>({ required: true })
const { t } = useI18n()
const IDS: KpiId[] = ['todo', 'relance', 'late', 'wait', 'done']
</script>

<template>
  <ul class="kpis" :aria-label="t('suivis.kpiLabel')">
    <li v-for="id in IDS" :key="id">
      <button type="button" class="kpi" :class="id" :aria-pressed="model === id" @click="model = model === id ? null : id">
        <span class="n">{{ counts[id] }}</span>
        <span class="l">{{ t(`suivis.kpi.${id}`) }}</span>
      </button>
    </li>
  </ul>
</template>

<style scoped>
.kpis { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: var(--space-2); }
.kpi { display: flex; flex-direction: column; align-items: flex-start; width: 100%; min-height: 72px; padding: var(--space-2) var(--space-3); border-radius: var(--radius-lg); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); color: var(--c-text); font: inherit; text-align: start; cursor: pointer; }
.kpi:hover { border-color: var(--c-border-strong); }
.n { font-size: 1.75rem; font-weight: 800; line-height: 1.1; }
.l { font-size: var(--fs-sm); font-weight: 700; margin-top: 2px; }
.todo .n { color: var(--c-primary); }
.relance .n { color: var(--c-warn); }
.late .n { color: var(--c-danger); }
.done .n { color: var(--c-success); }
.kpi[aria-pressed='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-color: var(--c-accent); box-shadow: 0 0 0 2px var(--c-accent); }
.kpi[aria-pressed='true'] .n { color: inherit; }
</style>
