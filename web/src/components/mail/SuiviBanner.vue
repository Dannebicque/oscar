<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { daysBetween, isoDay } from '../../domain/dates'
import { flagOf } from '../../domain/suivi'
import type { Suivi } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Bandeau d'état au-dessus du mail (§10.1). Les actions proposent ; rien ne change sans clic. */
const props = defineProps<{ suivi: Suivi }>()
const emit = defineEmits<{ draft: []; stop: []; close: []; keepOpen: []; keepWaiting: []; reopen: [] }>()
const { t } = useI18n()
const fmt = useFormat()

const flag = computed(() => flagOf(props.suivi, clock.now()))
const kind = computed(() => {
  if (props.suivi.status === 'done') return 'done'
  return flag.value ?? (props.suivi.direction === 'waiting' ? 'waiting' : 'toHandle')
})
const META = {
  replyReceived: { key: 'reply', icon: 'mail', cls: 'info' }, relanceDue: { key: 'relance', icon: 'clock', cls: 'warn' },
  late: { key: 'late', icon: 'alert', cls: 'danger' }, dueToday: { key: 'toHandle', icon: 'calendar', cls: 'warn' },
  waiting: { key: 'waiting', icon: 'hourglass', cls: 'neutral' }, toHandle: { key: 'toHandle', icon: 'follow', cls: 'info' }, done: { key: 'done', icon: 'checkCircle', cls: 'success' },
} as const
const meta = computed(() => META[kind.value])
const params = computed(() => ({
  who: props.suivi.contact, date: fmt.date(kind.value === 'waiting' ? props.suivi.relanceAt ?? props.suivi.dueAt : props.suivi.dueAt),
  n: props.suivi.relanceAt ? Math.max(1, daysBetween(props.suivi.relanceAt, isoDay(clock.now())) + 2) : 2,
}))
</script>

<template>
  <section class="banner" :class="meta.cls" :aria-label="t('mail.suivi')">
    <BaseIcon :name="meta.icon" :size="22" />
    <div class="txt">
      <h3>{{ t(`mail.banner.${meta.key}`) }}</h3>
      <p>{{ t(`mail.banner.${meta.key}Text`, params) }}</p>
    </div>
    <div class="actions">
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
    </div>
  </section>
</template>

<style scoped>
.banner { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid; }
.txt { flex: 1; min-width: 200px; }
h3 { font-size: var(--fs-md); }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.info { background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-color: var(--c-primary); }
.warn { background: var(--c-warn-soft); color: var(--c-warn); border-color: var(--c-warn); }
.danger { background: var(--c-danger-soft); color: var(--c-danger); border-color: var(--c-danger); }
.success { background: var(--c-success-soft); color: var(--c-success); border-color: var(--c-success); }
.neutral { background: var(--c-neutral-soft); color: var(--c-text); border-color: var(--c-border); }
.banner p { color: var(--c-text); }
</style>
