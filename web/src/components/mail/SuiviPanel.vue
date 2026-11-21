<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import { useFormat } from '../../composables/useFormat'
import type { Suivi } from '../../domain/types'
import StatusChip from '../ui/StatusChip.vue'

/** Volet latéral : responsable, échéance, relance et journal non modifiable (F-58). */
defineProps<{ suivi: Suivi }>()
const { t } = useI18n()
const fmt = useFormat()
</script>

<template>
  <aside class="side" :aria-label="t('mail.suiviPanel')">
    <h3>{{ t('mail.suiviPanel') }}</h3>
    <StatusChip :status="suivi.status" />
    <dl>
      <dt>{{ t('suivis.responsible') }}</dt><dd>{{ suivi.responsible === session.name ? t('common.me') : suivi.responsible ?? t('suivis.responsibleNone') }}</dd>
      <dt>{{ t('suivis.dueLabel') }}</dt><dd>{{ fmt.date(suivi.dueAt) }}</dd>
      <dt>{{ t('suivis.relanceLabel') }}</dt>
      <dd>
        {{ suivi.noRelance ? t('suivis.relanceStopped2') : suivi.relanceAt ? t('suivis.relanceOn', { date: fmt.date(suivi.relanceAt) }) : t('suivis.relanceNone') }}
        <span v-if="suivi.relanceCount" class="muted"> · {{ t('suivis.attempts', { n: suivi.relanceCount }, suivi.relanceCount) }}</span>
      </dd>
    </dl>
    <h3>{{ t('suivis.journal') }}</h3>
    <p class="hint">{{ t('suivis.journalHelp') }}</p>
    <ol class="log">
      <li v-for="(e, i) in suivi.journal" :key="i"><time :datetime="e.at" class="muted">{{ fmt.dateTime(e.at) }}</time><br />{{ fmt.journal(e) }} · {{ e.author }}</li>
    </ol>
  </aside>
</template>

<style scoped>
.side { display: grid; gap: var(--space-2); align-content: start; padding: var(--space-4); border-left: var(--bw) solid var(--c-border); background: var(--c-surface); min-height: 0; overflow-y: auto; }
h3 { margin-top: var(--space-2); }
dl { display: grid; gap: 2px; margin: 0; }
dt { font-size: var(--fs-sm); color: var(--c-text-muted); margin-top: var(--space-2); }
dd { margin: 0; font-weight: 600; }
.log { display: grid; gap: var(--space-2); font-size: var(--fs-sm); border-left: 2px solid var(--c-border); padding-left: var(--space-3); }
</style>
