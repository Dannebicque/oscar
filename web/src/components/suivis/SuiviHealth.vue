<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { computeHealth, durationParts, type Period, type Verdict } from '../../domain/suiviHealth'
import { useNow } from '../../composables/useNow'
import { useSuivisStore } from '../../stores/suivis'
import BaseButton from '../ui/BaseButton.vue'
import Callout from '../ui/Callout.vue'
import EmptyState from '../ui/EmptyState.vue'
import SegmentedControl from '../ui/SegmentedControl.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Bilan des suivis : où se perdent les réponses ? Calcul local, lecture seule ; rien n'est relancé ni clos par cet écran. */
const emit = defineEmits<{ list: [] }>()
const { t } = useI18n()
const store = useSuivisStore()
const now = useNow()
const key = ref<'30' | '90' | 'all'>('90')
const PERIOD: Record<'30' | '90' | 'all', Period> = { 30: 30, 90: 90, all: 0 }
const periods = computed(() => (['30', '90', 'all'] as const).map((k) => ({ value: k, label: t(`health.period.${k}`) })))
const h = computed(() => computeHealth(store.items, now.value, PERIOD[key.value]))

function dur(ms: number | null): string {
  if (ms === null) return '–'
  const { days, hours } = durationParts(ms)
  if (days > 0) return hours > 0 && days < 3 ? `${t('health.d', { n: days })} ${t('health.h', { n: hours })}` : t('health.d', { n: days })
  return hours > 0 ? t('health.h', { n: hours }) : t('health.lessHour')
}
const pct = computed(() => (h.value.rate === null ? '–' : `${Math.round(h.value.rate * 100)} %`))
const BUCKETS = ['d0_2', 'd3_7', 'd8_14', 'd15'] as const
const maxBucket = computed(() => Math.max(1, ...BUCKETS.map((b) => h.value.ages[b])))
const weekPlus = computed(() => h.value.ages.d8_14 + h.value.ages.d15)

const TONE: Record<Verdict, { tone: 'success' | 'neutral' | 'warn' | 'info'; icon: string }> = {
  fast: { tone: 'success', icon: 'checkCircle' }, ok: { tone: 'neutral', icon: 'clock' }, slow: { tone: 'warn', icon: 'clock' },
  silent: { tone: 'warn', icon: 'alert' }, waiting: { tone: 'info', icon: 'send' },
}
const all = ref(false)
const rows = computed(() => (all.value ? h.value.contacts : h.value.contacts.slice(0, 8)))
const silent = computed(() => h.value.contacts.find((c) => c.verdict === 'silent'))
</script>

<template>
  <section class="health" :aria-label="t('health.title')">
    <div class="head">
      <h2 class="hd">{{ t('health.title') }}</h2>
      <SegmentedControl v-model="key" :options="periods" :label="t('health.periodLabel')" />
    </div>

    <EmptyState v-if="h.asked === 0" icon="send" :text="t('health.empty')" />
    <template v-else>
      <ul class="tiles" :aria-label="t('health.tiles')">
        <li class="tile"><span class="n">{{ pct }}</span><span class="l">{{ t('health.rate') }}</span><span class="s">{{ t('health.rateSub', { a: h.answered, n: h.asked }) }}</span></li>
        <li class="tile"><span class="n">{{ dur(h.medianMs) }}</span><span class="l">{{ t('health.median') }}</span><span class="s">{{ t('health.medianSub') }}</span></li>
        <li class="tile"><span class="n">{{ h.open }}</span><span class="l">{{ t('health.open') }}</span><span class="s">{{ t('health.openSub', { n: weekPlus }, weekPlus) }}</span></li>
        <li class="tile"><span class="n">{{ h.relances }}</span><span class="l">{{ t('health.relances') }}</span><span class="s">{{ t('health.relancesSub') }}</span></li>
      </ul>

      <Callout v-if="silent" tone="info" icon="clock" :level="3" :title="t('health.tipTitle')">
        <p>{{ t('health.tip', { who: silent.contact, n: silent.asked }, silent.asked) }}</p>
        <template #actions><BaseButton :label="t('health.seeList')" @click="emit('list')" /></template>
      </Callout>

      <section class="card" aria-labelledby="age-h">
        <h3 id="age-h">{{ t('health.ageTitle') }}</h3>
        <p v-if="h.open === 0" class="muted">{{ t('health.ageNone') }}</p>
        <ul v-else class="ages">
          <li v-for="b in BUCKETS" :key="b" class="age">
            <span class="al">{{ t(`health.age.${b}`) }}</span>
            <span class="bar" aria-hidden="true"><span class="fill" :style="{ width: `${(h.ages[b] / maxBucket) * 100}%` }" /></span>
            <strong class="an">{{ h.ages[b] }}</strong>
          </li>
        </ul>
      </section>

      <section class="card" aria-labelledby="who-h">
        <h3 id="who-h">{{ t('health.byContact') }}</h3>
        <div class="scroll">
          <table>
            <caption class="sr-only">{{ t('health.byContact') }}</caption>
            <thead>
              <tr>
                <th scope="col">{{ t('health.col.contact') }}</th><th scope="col" class="num">{{ t('health.col.asked') }}</th><th scope="col" class="num">{{ t('health.col.answered') }}</th>
                <th scope="col" class="num">{{ t('health.col.median') }}</th><th scope="col" class="num">{{ t('health.col.open') }}</th><th scope="col">{{ t('health.col.state') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in rows" :key="c.contact">
                <th scope="row">{{ c.contact }}</th>
                <td class="num">{{ c.asked }}</td><td class="num">{{ c.answered }}</td><td class="num">{{ dur(c.medianMs) }}</td>
                <td class="num">{{ c.open }}<span v-if="c.oldestOpenDays !== null && c.open" class="muted"> · {{ t('health.oldest', { n: c.oldestOpenDays }) }}</span></td>
                <td><ToneChip :tone="TONE[c.verdict].tone" :icon="TONE[c.verdict].icon" :label="t(`health.verdict.${c.verdict}`)" /></td>
              </tr>
            </tbody>
          </table>
        </div>
        <BaseButton v-if="h.contacts.length > 8" variant="ghost" :label="all ? t('health.fewer') : t('health.more', { n: h.contacts.length - 8 })" :expanded="all" @click="all = !all" />
      </section>
      <p class="hint">{{ t('health.note') }}</p>
    </template>
  </section>
</template>

<style scoped>
.health { display: grid; gap: var(--space-4); }
.head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
.hd { margin: 0; font-size: var(--fs-md); }
.tiles { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-2); }
.tile { display: grid; gap: 2px; padding: var(--space-3); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); }
.tile .n { font-size: 1.75rem; font-weight: 800; line-height: 1.1; }
.tile .l { font-weight: 700; }
.tile .s { font-size: var(--fs-sm); color: var(--c-text-muted); }
h3 { margin: 0 0 var(--space-2); font-size: var(--fs-md); }
.ages { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.age { display: grid; grid-template-columns: 110px minmax(0, 1fr) 36px; gap: var(--space-3); align-items: center; min-height: 32px; }
.bar { display: block; height: 14px; background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: 999px; overflow: hidden; }
.fill { display: block; height: 100%; background: var(--c-primary); border-radius: 999px; }
.an { text-align: end; }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: var(--fs-sm); }
th, td { text-align: start; padding: var(--space-2) var(--space-3); border-top: var(--bw) solid var(--c-border); vertical-align: middle; }
thead th { border-top: 0; color: var(--c-text-muted); font-weight: 700; }
tbody th { font-weight: 700; }
.num { text-align: end; white-space: nowrap; }
@media (max-width: 720px) { .age { grid-template-columns: 90px minmax(0, 1fr) 32px; } }
</style>
