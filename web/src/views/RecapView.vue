<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AiBadge from '../components/ui/AiBadge.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { isoDay } from '../domain/dates'
import { flagOf } from '../domain/suivi'
import { useOscar } from '../oscar/useOscar'
import { useAgendaStore } from '../stores/agenda'
import { useSettingsStore } from '../stores/settings'
import { useSuivisStore } from '../stores/suivis'
import { useTasksStore } from '../stores/tasks'

const { t } = useI18n()
const { say } = useOscar()
const fmt = useFormat()
const suivis = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const settings = useSettingsStore()
onMounted(() => Promise.all([suivis.load(), tasks.load(), agenda.load()]))

type Slot = 'morning' | 'evening'
const slot = ref<Slot>('morning')
const options = computed(() => (['morning', 'evening'] as Slot[]).map((v) => ({ value: v, label: t(`recap.${v}`) })))
const now = clock.now()
const sections = computed(() => [
  { key: 'mailsToHandle', items: suivis.mine.filter((s) => ['replyReceived', 'late'].includes(flagOf(s, now) ?? '')).map((s) => `${s.subject} · ${s.contact}`) },
  { key: 'events', items: agenda.work.filter((e) => isoDay(new Date(e.start)) === isoDay(now) && new Date(e.end) > now).map((e) => `${fmt.time(e.start)} · ${e.title}`) },
  { key: 'tasks', items: tasks.today.map((x) => x.title) },
  { key: 'relances', items: suivis.relances.map((s) => `${s.subject} · ${s.contact}`) },
])
</script>

<template>
  <PageHeader :title="t('recap.title')" :subtitle="say('recapIntro')">
    <SegmentedControl v-model="slot" :options="options" :label="t('recap.title')" />
  </PageHeader>
  <article class="card stack recap">
    <p class="head"><AiBadge /><span class="muted">{{ t('recap.sentTo', { time: slot === 'morning' ? settings.s.recapMorning : settings.s.recapEvening }) }}</span></p>
    <section v-for="s in sections" :key="s.key" :aria-labelledby="`r-${s.key}`">
      <h2 :id="`r-${s.key}`">{{ t(`recap.${s.key}`) }}</h2>
      <ul v-if="s.items.length"><li v-for="(i, n) in s.items" :key="n">{{ i }}</li></ul>
      <p v-else class="muted">{{ t('recap.nothing') }}</p>
    </section>
  </article>
</template>

<style scoped>
.recap { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); align-items: start; }
.recap > .head { grid-column: 1 / -1; }
.head { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
h2 { font-size: var(--fs-md); margin-bottom: var(--space-1); }
ul { display: grid; gap: var(--space-1); list-style: disc; padding-left: var(--space-5); }
</style>
