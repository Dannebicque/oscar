<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { flagOf, groupByStatus } from '../../domain/suivi'
import type { Suivi } from '../../domain/types'
import FlagChip from '../ui/FlagChip.vue'
import StatusChip from '../ui/StatusChip.vue'

/** Vue Tableau (F-70) : à traiter, en cours, en attente, traité. Des listes, pas du glisser-déposer : tout reste au clavier. */
const props = defineProps<{ items: Suivi[] }>()
defineEmits<{ open: [s: Suivi] }>()
const { t } = useI18n()
const fmt = useFormat()
const columns = computed(() => groupByStatus(props.items))
</script>

<template>
  <div class="board">
    <section v-for="c in columns" :key="c.status" class="col" :aria-labelledby="`col-${c.status}`">
      <h3 :id="`col-${c.status}`"><StatusChip :status="c.status" /> <span class="n">{{ c.items.length }}</span></h3>
      <ul v-if="c.items.length">
        <li v-for="s in c.items" :key="s.id" class="card pad-sm flat">
          <button type="button" class="open" @click="$emit('open', s)">
            <strong>{{ s.subject }}</strong>
            <span class="muted">{{ s.contact }} · {{ fmt.date(s.dueAt) }}</span>
          </button>
          <FlagChip v-if="flagOf(s, clock.now())" :flag="flagOf(s, clock.now())!" />
        </li>
      </ul>
      <p v-else class="muted">{{ t('suivis.boardEmpty') }}</p>
    </section>
  </div>
</template>

<style scoped>
.board { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); align-items: start; }
.col { background: var(--c-surface-2); border-radius: var(--radius-md); padding: var(--space-3); display: grid; gap: var(--space-3); }
h3 { display: flex; align-items: center; gap: var(--space-2); }
.n { font-weight: 800; }
ul { display: grid; gap: var(--space-2); }

.open { display: grid; gap: 2px; text-align: left; background: none; border: 0; padding: 0; min-height: var(--target); }
</style>
