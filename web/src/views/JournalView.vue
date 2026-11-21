<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseIcon from '../components/ui/BaseIcon.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useFormat } from '../composables/useFormat'
import { useOscar } from '../oscar/useOscar'
import { useSystemStore } from '../stores/system'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const system = useSystemStore()
const ui = useUiStore()
const { say } = useOscar()
const ICON = { ok: 'checkCircle', syncing: 'refresh', error: 'alert' } as const
const stateText = (c: { id: string; state: string }) => (c.state === 'ok' && c.id !== 'ai' ? t('status.okShort') : t(`status.${c.state}`))
const account = ref('all')
onMounted(() => { if (!system.log.length) system.load() })

const accounts = computed(() => [...new Set(system.log.map((l) => l.account))])
const rows = computed(() => system.log.filter((l) => account.value === 'all' || l.account === account.value).sort((a, b) => b.at.localeCompare(a.at)))

/** Export pour le support (F-78) : JSON horodaté, sans contenu de mail. */
function exportLog() {
  const blob = new Blob([JSON.stringify(rows.value, null, 2)], { type: 'application/json' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = 'oscar-journal.json'
  a.click()
  URL.revokeObjectURL(a.href)
  ui.notify(t('journal.exported'))
}
</script>

<template>
  <PageHeader :title="t('journal.title')" :subtitle="say('journalIntro')">
    <BaseButton icon="download" :label="t('journal.export')" @click="exportLog" />
  </PageHeader>
  <ul class="conns" :aria-label="t('status.label')">
    <li v-for="c in system.connections" :key="c.id" class="conn" :class="c.state">
      <div>
        <p class="cn"><strong>{{ t(`status.${c.id}`) }}</strong><span class="state"><BaseIcon :name="ICON[c.state]" :size="16" />{{ stateText(c) }}</span></p>
        <p class="muted">{{ c.detail }}<template v-if="c.id !== 'ai'"> · {{ c.lastOk ? t('status.lastOk', { when: fmt.dateTime(c.lastOk) }) : t('status.neverOk') }}</template></p>
      </div>
      <BaseButton v-if="c.state === 'error'" variant="primary" icon="refresh" :label="t('status.retry')" :disabled="system.syncing" @click="system.syncNow()" />
    </li>
  </ul>
  <h2 class="lh">{{ t('journal.log') }}</h2>
  <div class="field filter">
    <label for="acc">{{ t('journal.filter') }}</label>
    <select id="acc" v-model="account" class="input"><option value="all">{{ t('common.all') }}</option><option v-for="a in accounts" :key="a" :value="a">{{ a }}</option></select>
  </div>
  <EmptyState v-if="rows.length === 0" icon="list" :text="t('journal.empty')" />
  <div v-else class="card pad-0 scroll">
    <table>
      <thead><tr><th scope="col">{{ t('journal.date') }}</th><th scope="col">{{ t('journal.account') }}</th><th scope="col">{{ t('journal.type') }}</th><th scope="col">{{ t('journal.message') }}</th><th scope="col">{{ t('journal.action') }}</th></tr></thead>
      <tbody>
        <tr v-for="l in rows" :key="l.id">
          <td>{{ fmt.dateTime(l.at) }}</td><td>{{ l.account }}</td><td>{{ t(`journal.types.${l.type}`) }}</td><td>{{ t(`journal.messages.${l.message}`) }}</td><td>{{ t(`journal.actions.${l.action}`) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
  <p class="back"><RouterLink to="/reglages">{{ t('journal.back') }}</RouterLink></p>
</template>

<style scoped>
.conns { list-style: none; margin: 0 0 var(--space-4); padding: 0; display: grid; gap: var(--space-2); }
.conn { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-3) var(--space-4); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-inline-start: 4px solid var(--c-success); border-radius: var(--radius-lg); }
.conn.error { border-inline-start-color: var(--c-warn); }
.conn.syncing { border-inline-start-color: var(--c-primary); }
.cn { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.state { display: inline-flex; align-items: center; gap: 4px; padding: 2px var(--space-2); border-radius: 999px; font-size: var(--fs-sm); font-weight: 700; background: var(--c-success-soft); color: var(--c-success); }
.error .state { background: var(--c-warn-soft); color: var(--c-warn); }
.lh { font-size: var(--fs-lg); margin-bottom: var(--space-2); }
.filter { max-width: 320px; margin-bottom: var(--space-4); }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: var(--space-3); border-bottom: 1px solid var(--c-border); vertical-align: top; }
th { background: var(--c-surface-2); font-size: var(--fs-sm); }
.back { margin-top: var(--space-4); }
</style>
