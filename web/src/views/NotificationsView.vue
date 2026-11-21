<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '../components/ui/BaseButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import NotificationChannels from '../components/settings/NotificationChannels.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import { useFormat } from '../composables/useFormat'
import type { NotificationKind } from '../domain/types'
import { useSystemStore } from '../stores/system'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const system = useSystemStore()
const ui = useUiStore()

const META: Record<NotificationKind, { icon: string; tone: 'info' | 'warn' | 'danger' | 'neutral' }> = {
  replyReceived: { icon: 'mail', tone: 'info' }, relanceDue: { icon: 'clock', tone: 'warn' }, invitation: { icon: 'calendar', tone: 'neutral' }, connectionFailed: { icon: 'alert', tone: 'danger' },
  takenOver: { icon: 'user', tone: 'warn' }, memberReplied: { icon: 'send', tone: 'info' }, scheduledFailed: { icon: 'alert', tone: 'danger' },
}
const sorted = computed(() => [...system.notifications].sort((a, b) => b.at.localeCompare(a.at)))
async function markAll() {
  await system.markAllRead()
  ui.notify(t('alerts.marked'))
}
</script>

<template>
  <PageHeader :title="t('nav.alerts')">
    <BaseButton icon="check" :disabled="system.unreadCount === 0" :label="t('alerts.markAll')" @click="markAll" />
  </PageHeader>
  <EmptyState v-if="sorted.length === 0" icon="bell" :text="t('alerts.empty')" />
  <ul v-else class="list">
    <li v-for="n in sorted" :key="n.id" class="row" :class="{ hot: !n.read }">
      <div class="main">
        <p class="chips">
          <ToneChip :tone="META[n.kind].tone" :icon="META[n.kind].icon" :label="t(`alerts.kinds.${n.kind}`)" />
          <ToneChip v-if="!n.read" tone="info" icon="star" :label="t('alerts.unread')" />
        </p>
        <p class="txt">{{ t(`alerts.messages.${n.kind}`, n.params, Number(n.params.n ?? 1)) }}</p>
        <time class="muted" :datetime="n.at">{{ fmt.dateTime(n.at) }}</time>
      </div>
      <BaseButton :label="t('alerts.open')" @click="router.push('/' + n.to)" />
    </li>
  </ul>
  <NotificationChannels class="chan" />
</template>

<style scoped>
.list { display: grid; gap: var(--gap); }
.main { flex: 1; display: grid; gap: 2px; }
.chips { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.txt { font-weight: 600; }
.chan { margin-top: var(--space-5); }
time { font-size: var(--fs-sm); }
</style>
