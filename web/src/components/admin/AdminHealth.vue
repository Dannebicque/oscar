<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../../api'
import type { HealthCheck, HealthReport } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Santé de l'instance : états de service et compteurs. Jamais de contenu d'utilisateur. */
const { t } = useI18n()
const report = ref<HealthReport | null>(null)
const failed = ref(false)
const loading = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try { report.value = await api.instance.health() } catch { failed.value = true } finally { loading.value = false }
}
onMounted(load)

const TONE = { ok: { tone: 'success', icon: 'checkCircle' }, warn: { tone: 'warn', icon: 'alert' }, error: { tone: 'danger', icon: 'alert' } } as const
const detail = (c: HealthCheck) => t(`health2.${c.id}.${c.status}`, c.data as Record<string, string | number>)
</script>

<template>
  <BaseCard class="stack" aria-labelledby="ah-h">
    <div class="head">
      <h2 id="ah-h">{{ t('health2.title') }}</h2>
      <BaseButton icon="refresh" :disabled="loading" :label="t('health2.refresh')" @click="load" />
    </div>
    <p v-if="failed" class="error-text" role="alert">{{ t('common.actionFailed') }}</p>
    <template v-if="report">
      <ul class="tiles" :aria-label="t('health2.counts')">
        <li class="tile"><span class="n">{{ report.counts.users }}</span><span class="l">{{ t('health2.users') }}</span><span class="s">{{ t('health2.active', { n: report.counts.activeLast30Days }) }}</span></li>
        <li class="tile"><span class="n">{{ report.counts.mailAccounts }}</span><span class="l">{{ t('health2.mailAccounts') }}</span></li>
        <li class="tile"><span class="n">{{ report.counts.admins }}</span><span class="l">{{ t('health2.admins') }}</span><span class="s">{{ t('health2.disabled', { n: report.counts.disabled }) }}</span></li>
      </ul>
      <ul class="checks">
        <li v-for="c in report.checks" :key="c.id" class="check">
          <ToneChip :tone="TONE[c.status].tone" :icon="TONE[c.status].icon" :label="t(`health2.status.${c.status}`)" />
          <span class="what"><strong>{{ t(`health2.${c.id}.name`) }}</strong><span class="muted"> · {{ detail(c) }}</span></span>
        </li>
      </ul>
      <p class="muted">{{ t('health2.version', { v: report.version, php: report.php }) }}</p>
    </template>
  </BaseCard>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
h2 { font-size: var(--fs-md); margin: 0; }
.tiles { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-2); }
.tile { display: grid; gap: 2px; padding: var(--space-3); background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.tile .n { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
.tile .l { font-weight: 700; }
.tile .s { font-size: var(--fs-sm); color: var(--c-text-muted); }
.checks { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.check { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; min-height: var(--target); }
.what { flex: 1 1 260px; overflow-wrap: anywhere; }
</style>
