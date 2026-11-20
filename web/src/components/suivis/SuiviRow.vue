<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import type { PriorityItem } from '../../domain/priorities'
import AiBadge from '../ui/AiBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import FlagChip from '../ui/FlagChip.vue'
import ToneChip from '../ui/ToneChip.vue'

const props = defineProps<{ item: PriorityItem; rank?: number; extra?: string; compact?: boolean }>()
defineEmits<{ open: [item: PriorityItem]; done: [item: PriorityItem] }>()
const { t } = useI18n()
const fmt = useFormat()
const ICON = { mail: 'mail', task: 'task', rdv: 'calendar' } as const
</script>

<template>
  <li class="row" :class="{ hot: item.flag === 'replyReceived' || item.flag === 'late' }">
    <span v-if="rank" class="rank" aria-hidden="true">{{ rank }}</span>
    <div class="main">
      <p class="line">
        <ToneChip tone="neutral" :icon="ICON[item.kind]" :label="t(`kinds.${item.kind}`)" />
        <FlagChip v-if="item.flag" :flag="item.flag" />
        <AiBadge v-if="item.suggestedByAi" />
      </p>
      <h3>{{ item.title }}</h3>
      <p class="meta muted">
        <span v-if="item.meta">{{ item.meta }} · </span>{{ t('common.dueOn', { date: fmt.date(item.dueAt) }) }}<span v-if="extra"> · {{ extra }}</span>
      </p>
      <p v-if="item.reason && !compact" class="reason"><BaseIcon name="sparkles" :size="14" /> {{ t('suivis.reasonPrefix', { reason: item.reason }) }}</p>
    </div>
    <div class="actions">
      <BaseButton :label="t('suivis.open')" @click="$emit('open', item)" />
      <BaseButton v-if="item.kind !== 'rdv'" icon="check" :label="item.kind === 'mail' ? t('suivis.close') : t('tasks.done')" @click="$emit('done', item)" />
    </div>
  </li>
</template>

<style scoped>
.row { display: flex; gap: var(--space-4); align-items: center; padding: var(--row-y) var(--space-4); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); }
.row.hot { border-left: 4px solid var(--c-primary); }
.rank { width: 28px; height: 28px; border-radius: 50%; background: var(--c-surface-2); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; flex: none; }
.main { flex: 1; min-width: 0; display: grid; gap: 2px; }
.line { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; }
h3 { font-size: var(--fs-md); }
.meta { font-size: var(--fs-sm); }
.reason { font-size: var(--fs-sm); color: var(--c-ai); display: flex; gap: 4px; align-items: center; }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; justify-content: flex-end; }
@media (max-width: 720px) { .row { flex-direction: column; align-items: stretch; } .actions { justify-content: flex-start; } }
</style>
