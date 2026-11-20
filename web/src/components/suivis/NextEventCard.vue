<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { useAgendaStore } from '../../stores/agenda'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Encart « Prochain RDV » (F-71) : en cours (avec heure de fin) ou suivant, délai, lieu et rendez-vous d'après. */
const props = defineProps<{ now: Date }>()
const { t } = useI18n()
const fmt = useFormat()
const agenda = useAgendaStore()
const n = computed(() => agenda.next)
</script>

<template>
  <section class="next" aria-labelledby="next-title">
    <BaseIcon name="calendar" :size="24" />
    <div class="txt">
      <p id="next-title" class="kicker">{{ t('home.nextTitle') }}</p>
      <template v-if="n.next">
        <p v-if="n.noneToday" class="title">{{ t('home.nextNone') }}</p>
        <p v-if="n.noneToday" class="sub">{{ t('home.nextUpcoming', { title: n.next.title, when: fmt.dateTime(n.next.start) }) }}</p>
        <template v-else>
          <p class="title">{{ n.next.title }}</p>
          <p class="sub">
            <template v-if="n.current">{{ t('home.nextCurrent', { end: fmt.time(n.current.end) }) }}</template>
            <template v-else>{{ t('home.nextIn', { delay: fmt.delay(n.next.start, props.now) }) }} · {{ fmt.time(n.next.start) }}</template>
            <template v-if="n.next.location"> · {{ n.next.location }}</template>
          </p>
          <p v-if="n.after" class="sub muted">{{ t('home.nextAfter', { title: n.after.title, time: fmt.time(n.after.start) }) }}</p>
        </template>
      </template>
      <p v-else class="title">{{ t('home.nextNone') }}</p>
    </div>
    <BaseButton :label="t('home.openAgenda')" @click="$router.push('/agenda')" />
  </section>
</template>

<style scoped>
.next { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4) var(--space-5); background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-radius: var(--radius-lg); flex-wrap: wrap; border: 1px solid var(--c-primary); }
.txt { flex: 1; min-width: 220px; display: grid; gap: 2px; }
.kicker { font-size: var(--fs-sm); font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.title { font-size: var(--fs-lg); font-weight: 800; }
.sub { font-size: var(--fs-sm); }
.muted { color: inherit; opacity: 0.85; }
</style>
