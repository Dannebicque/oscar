<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { useOscar } from '../../oscar/useOscar'
import { useAgendaStore } from '../../stores/agenda'
import BaseButton from '../ui/BaseButton.vue'
import Callout from '../ui/Callout.vue'

/** Encart « Prochain RDV » (F-71) : en cours (avec heure de fin) ou suivant, délai, lieu et rendez-vous d'après. */
const props = defineProps<{ now: Date }>()
const { t } = useI18n()
const { say } = useOscar()
const fmt = useFormat()
const agenda = useAgendaStore()
const n = computed(() => agenda.next)
</script>

<template>
  <Callout :level="2" tone="info" icon="calendar" :title="t('home.nextTitle')" class="next">
    <template v-if="n.next">
      <p v-if="n.noneToday" class="big">{{ say('noMeeting') }}</p>
      <p v-if="n.noneToday" class="sub">{{ t('home.nextUpcoming', { title: n.next.title, when: fmt.dateTime(n.next.start) }) }}</p>
      <template v-else>
        <p class="big">{{ n.next.title }}</p>
        <p class="sub">
          <template v-if="n.current">{{ t('home.nextCurrent', { end: fmt.time(n.current.end) }) }}</template>
          <template v-else>{{ t('home.nextIn', { delay: fmt.delay(n.next.start, props.now) }) }} · {{ fmt.time(n.next.start) }}</template>
          <template v-if="n.next.location"> · {{ n.next.location }}</template>
        </p>
        <p v-if="n.after" class="sub muted">{{ t('home.nextAfter', { title: n.after.title, time: fmt.time(n.after.start) }) }}</p>
      </template>
    </template>
    <p v-else class="big">{{ say('noMeeting') }}</p>
    <template #actions><BaseButton :label="t('home.openAgenda')" @click="$router.push('/agenda')" /></template>
  </Callout>
</template>

<style scoped>
.big { font-size: var(--fs-lg); font-weight: 800; }
.sub { font-size: var(--fs-sm); }
</style>
