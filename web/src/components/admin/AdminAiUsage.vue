<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../../api'
import type { AiUsage } from '../../domain/types'
import BaseCard from '../ui/BaseCard.vue'
import SegmentedControl from '../ui/SegmentedControl.vue'

/** Usage de l'IA : des nombres sur l'ensemble de l'instance. Jamais une personne, un mail ni un texte. */
const { t, te } = useI18n()
type Days = '7' | '30' | '90'
const days = ref<Days>('30')
const usage = ref<AiUsage | null>(null)
const failed = ref(false)
const periods = (['7', '30', '90'] as Days[]).map((v) => ({ value: v, label: t('aiUsage.days', { n: v }) }))
const COLUMNS = ['total', 'model', 'rules', 'fallback'] as const

async function load() {
  failed.value = false
  try { usage.value = await api.instance.aiUsage(Number(days.value)) } catch { failed.value = true }
}
onMounted(load)
watch(days, load)
const feature = (id: string) => (te(`aiUsage.feature.${id}`) ? t(`aiUsage.feature.${id}`) : id)
</script>

<template>
  <BaseCard class="stack" aria-labelledby="aiu-h">
    <div class="head">
      <h2 id="aiu-h">{{ t('aiUsage.title') }}</h2>
      <SegmentedControl v-model="days" :options="periods" :label="t('aiUsage.period')" />
    </div>
    <p class="muted">{{ t('aiUsage.privacy') }}</p>
    <p v-if="failed" class="error-text" role="alert">{{ t('common.actionFailed') }}</p>
    <template v-if="usage">
      <ul class="tiles" :aria-label="t('aiUsage.summary')">
        <li class="tile"><span class="n">{{ usage.totals.total }}</span><span class="l">{{ t('aiUsage.requests') }}</span><span class="s">{{ t('aiUsage.activeUsers', { n: usage.activeUsers, total: usage.users }) }}</span></li>
        <li class="tile"><span class="n">{{ usage.totals.model }}</span><span class="l">{{ t('aiUsage.model') }}</span><span class="s">{{ t('aiUsage.modelHelp') }}</span></li>
        <li class="tile"><span class="n">{{ usage.totals.rules }}</span><span class="l">{{ t('aiUsage.rules') }}</span><span class="s">{{ t('aiUsage.rulesHelp') }}</span></li>
        <li class="tile"><span class="n">{{ usage.totals.fallback }}</span><span class="l">{{ t('aiUsage.fallback') }}</span><span class="s">{{ t('aiUsage.fallbackHelp') }}</span></li>
      </ul>
      <p v-if="usage.totals.unknown" class="hint">{{ t('aiUsage.unknown', { n: usage.totals.unknown }) }}</p>

      <p v-if="!usage.totals.total" class="muted">{{ t('aiUsage.none') }}</p>
      <div v-else class="scroll">
        <table>
          <caption>{{ t('aiUsage.byFeature') }}</caption>
          <thead><tr><th scope="col">{{ t('aiUsage.featureCol') }}</th><th v-for="c in COLUMNS" :key="c" scope="col">{{ t(`aiUsage.col.${c}`) }}</th></tr></thead>
          <tbody>
            <tr v-for="f in usage.features" :key="f.feature"><th scope="row">{{ feature(f.feature) }}</th><td v-for="c in COLUMNS" :key="c">{{ f[c] }}</td></tr>
          </tbody>
        </table>
      </div>

      <h3>{{ t('aiUsage.modes') }}</h3>
      <p>{{ t('aiUsage.modesLine', { university: usage.modes.university, external: usage.modes.external, off: usage.modes.off }) }}</p>
      <p class="muted">{{ t(usage.externalAllowed ? 'aiUsage.allowed' : 'aiUsage.forbidden') }}</p>
      <p v-if="usage.services.length">{{ t('aiUsage.services') }} {{ usage.services.map((s) => `${s.service === 'anthropic' ? 'Anthropic' : t('aiUsage.openai')} : ${s.total}`).join(' · ') }}</p>
    </template>
  </BaseCard>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
h2 { font-size: var(--fs-md); margin: 0; }
h3 { font-size: var(--fs-md); margin: 0; }
.tiles { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-2); }
.tile { display: grid; gap: 2px; align-content: start; padding: var(--space-3); background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.tile .n { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
.tile .l { font-weight: 700; }
.tile .s { font-size: var(--fs-sm); color: var(--c-text-muted); }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
caption { text-align: start; font-weight: 700; padding-bottom: var(--space-2); }
th, td { padding: var(--space-2) var(--space-3); border-bottom: var(--bw) solid var(--c-border); text-align: end; }
th[scope='row'], thead th:first-child { text-align: start; }
thead th { font-size: var(--fs-sm); color: var(--c-text-muted); }
</style>
