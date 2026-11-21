<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import type { Connection } from '../../domain/types'
import { useSettingsStore } from '../../stores/settings'
import { useSystemStore } from '../../stores/system'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Barre d'état toujours visible (F-77) : messagerie, agendas et assistant IA avec le mode utilisé. */
const { t } = useI18n()
const system = useSystemStore()
const settings = useSettingsStore()
const ui = useUiStore()
const fmt = useFormat()
const selected = ref<Connection | null>(null)

const ICON = { ok: 'checkCircle', syncing: 'refresh', error: 'alert' } as const
const name = (c: Connection) => t(`status.${c.id}`)
const label = (c: Connection) => (c.id === 'ai' ? t(`status.aiMode.${settings.s.aiMode}`) : name(c))
const SERVICE = { mail: 'mail', calendar: 'calendar', google: 'calendar', ai: 'sparkles' } as Record<string, string>
const shortName = (c: Connection) => t(`status.short.${c.id === 'ai' ? settings.s.aiMode : c.id}`)
const stateText = (c: Connection) => (c.state === 'ok' && c.id !== 'ai' ? t('status.okShort') : t(`status.${c.state}`))
const summaryKey = computed(() => (system.hasError ? 'error' : 'ok'))

async function retry(c: Connection) {
  await system.syncNow()
  const next = system.connections.find((x) => x.id === c.id) ?? c
  selected.value = next
  if (next.state === 'error') ui.notify(t('status.retryFailed', { name: name(next) }), { tone: 'error' })
  else ui.notify(t('status.retryOk', { name: name(next) }))
}
</script>

<template>
  <section class="status" :aria-label="t('status.label')">
    <ul class="conns">
      <li v-for="c in system.connections" :key="c.id">
        <button type="button" class="conn" :class="c.state" :title="`${label(c)} : ${stateText(c)}`" :aria-label="`${label(c)} : ${stateText(c)}. ${t('status.details')}`" @click="selected = c">
          <BaseIcon :name="SERVICE[c.id] ?? 'alert'" :size="16" :class="{ ai: c.id === 'ai' }" />
          <span>{{ shortName(c) }}</span>
          <BaseIcon :name="ICON[c.state]" :size="16" class="state" />
          <span v-if="c.state !== 'ok'" class="txt">{{ stateText(c) }}</span>
        </button>
      </li>
    </ul>
    <p class="summary" :class="summaryKey"><BaseIcon :name="ICON[summaryKey]" :size="16" /><span class="sr-only">{{ t('status.label') }} : </span>{{ system.hasError ? t('status.error') : t('status.okShort') }}</p>
    <p v-if="system.lastSync" class="sync">{{ t('status.lastSync', { time: fmt.time(system.lastSync) }) }}</p>
    <RouterLink to="/journal" class="journal">{{ t('status.journal') }}</RouterLink>
  </section>

  <BaseDialog :open="selected !== null" :title="selected ? label(selected) : ''" @close="selected = null">
    <template v-if="selected">
      <p>{{ selected.detail }}</p>
      <p class="muted">{{ selected.lastOk ? t('status.lastOk', { when: fmt.dateTime(selected.lastOk) }) : t('status.neverOk') }}</p>
    </template>
    <template #footer>
      <BaseButton v-if="selected && selected.state === 'error'" variant="primary" icon="refresh" :disabled="system.syncing" :label="t('status.retry')" @click="retry(selected)" />
      <RouterLink v-if="selected && selected.state === 'error'" to="/journal" @click="selected = null">{{ t('status.journal') }}</RouterLink>
      <BaseButton :label="t('common.close')" @click="selected = null" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.status { grid-area: status; position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; gap: var(--space-3); padding: var(--space-1) var(--space-4); background: var(--c-surface-2); border-top: 1px solid var(--c-border); font-size: var(--fs-sm); min-height: 44px; }
.conns { display: flex; gap: var(--space-2); flex: 1; min-width: 0; overflow-x: auto; }
.conn { display: inline-flex; align-items: center; gap: 6px; min-height: var(--target); padding: 0 var(--space-3); border: var(--bw) solid var(--c-border); background: var(--c-surface); border-radius: 999px; color: var(--c-text); white-space: nowrap; }
.conn:hover { background: var(--c-surface-2); border-color: var(--c-text-muted); }
.conn .state { color: var(--c-text-muted); }
.conn.ok .state { color: var(--c-success); }
.conn.error { color: var(--c-danger); font-weight: 700; border-color: var(--c-danger); }
.conn.error .state { color: var(--c-danger); }
.conn .ai { color: var(--c-ai); }
.summary { display: none; align-items: center; gap: 6px; flex: 1; }
.summary.error { color: var(--c-danger); font-weight: 700; }
.sync { color: var(--c-text-muted); white-space: nowrap; }
.journal { min-height: var(--target); display: inline-flex; align-items: center; }
@media (max-width: 720px) { .conns { display: none; } .summary { display: inline-flex; } .sync { display: none; } }
</style>
