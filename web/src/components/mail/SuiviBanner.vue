<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { daysBetween, isoDay } from '../../domain/dates'
import { flagOf } from '../../domain/suivi'
import type { Suivi } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import Callout from '../ui/Callout.vue'

/** Bandeau d'état au-dessus du mail (§10.1). Les actions proposent ; rien ne change sans clic. Aspect : Callout partagé. */
const props = defineProps<{ suivi: Suivi }>()
const emit = defineEmits<{ draft: []; stop: []; close: []; keepOpen: []; keepWaiting: []; reopen: [] }>()
const { t } = useI18n()
const fmt = useFormat()

const flag = computed(() => flagOf(props.suivi, clock.now()))
const kind = computed(() => (props.suivi.status === 'done' ? 'done' : flag.value ?? (props.suivi.direction === 'waiting' ? 'waiting' : 'toHandle')))
const META = {
  replyReceived: { key: 'reply', icon: 'mail', tone: 'info' }, relanceDue: { key: 'relance', icon: 'clock', tone: 'warn' },
  late: { key: 'late', icon: 'alert', tone: 'danger' }, dueToday: { key: 'toHandle', icon: 'calendar', tone: 'warn' },
  waiting: { key: 'waiting', icon: 'hourglass', tone: 'neutral' }, toHandle: { key: 'toHandle', icon: 'follow', tone: 'info' }, done: { key: 'done', icon: 'checkCircle', tone: 'success' },
} as const
const meta = computed(() => META[kind.value])
const params = computed(() => ({
  who: props.suivi.contact, date: fmt.date(kind.value === 'waiting' ? props.suivi.relanceAt ?? props.suivi.dueAt : props.suivi.dueAt),
  n: props.suivi.relanceAt ? Math.max(1, daysBetween(props.suivi.relanceAt, isoDay(clock.now())) + 2) : 2,
}))
</script>

<template>
  <Callout :tone="meta.tone" :icon="meta.icon" :title="t(`mail.banner.${meta.key}`)" :aria-label="t('mail.suivi')">
    <p>{{ t(`mail.banner.${meta.key}Text`, params) }}</p>
    <template #actions>
      <template v-if="kind === 'relanceDue'">
        <BaseButton variant="primary" :label="t('mail.seeDraft')" @click="emit('draft')" />
        <BaseButton :label="t('mail.noRelance')" @click="emit('stop')" />
        <BaseButton :label="t('mail.closeSuivi')" @click="emit('close')" />
      </template>
      <template v-else-if="kind === 'replyReceived'">
        <BaseButton variant="primary" :label="t('mail.closeSuivi')" @click="emit('close')" />
        <BaseButton :label="t('mail.keepOpen')" @click="emit('keepOpen')" />
        <BaseButton :label="t('mail.keepWaiting')" @click="emit('keepWaiting')" />
      </template>
      <BaseButton v-else-if="kind === 'done'" :label="t('mail.reopenSuivi')" @click="emit('reopen')" />
      <template v-else>
        <BaseButton :label="t('mail.closeSuivi')" @click="emit('close')" />
        <BaseButton v-if="kind === 'waiting' && !suivi.noRelance" :label="t('mail.noRelance')" @click="emit('stop')" />
      </template>
    </template>
  </Callout>
</template>
