<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '../components/ui/BaseButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useFormat } from '../composables/useFormat'
import { useSystemStore } from '../stores/system'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const system = useSystemStore()
const ui = useUiStore()
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
  <PageHeader :title="t('journal.title')" :subtitle="t('journal.help')">
    <BaseButton icon="download" :label="t('journal.export')" @click="exportLog" />
  </PageHeader>
  <div class="field filter">
    <label for="acc">{{ t('journal.filter') }}</label>
    <select id="acc" v-model="account" class="input"><option value="all">{{ t('common.all') }}</option><option v-for="a in accounts" :key="a" :value="a">{{ a }}</option></select>
  </div>
  <EmptyState v-if="rows.length === 0" icon="list" :text="t('journal.empty')" />
  <div v-else class="scroll">
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
.filter { max-width: 320px; margin-bottom: var(--space-4); }
.scroll { overflow-x: auto; border: 1px solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface); }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: var(--space-3); border-bottom: 1px solid var(--c-border); vertical-align: top; }
th { background: var(--c-surface-2); font-size: var(--fs-sm); }
.back { margin-top: var(--space-4); }
</style>
