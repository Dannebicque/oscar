<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../../api'
import { useFormat } from '../../composables/useFormat'
import type { AuditEntry } from '../../domain/types'
import BaseCard from '../ui/BaseCard.vue'

/** Journal d'audit : qui a changé quoi dans l'administration. Pas de valeurs, pas de contenu : seulement des actions. */
const { t } = useI18n()
const fmt = useFormat()
const rows = ref<AuditEntry[]>([])
const loaded = ref(false)
onMounted(async () => {
  try { rows.value = await api.instance.audit() } catch { /* liste vide */ } finally { loaded.value = true }
})
const keys = (e: AuditEntry) => (Array.isArray(e.details.keys) ? (e.details.keys as string[]).map((k) => t(`audit.keys.${k}`)).join(', ') : '')
</script>

<template>
  <BaseCard class="stack" aria-labelledby="aa-h">
    <h2 id="aa-h">{{ t('audit.title') }}</h2>
    <p class="muted">{{ t('audit.help') }}</p>
    <p v-if="loaded && rows.length === 0" class="muted">{{ t('audit.none') }}</p>
    <div v-else class="scroll">
      <table>
        <caption class="sr-only">{{ t('audit.title') }}</caption>
        <thead><tr><th scope="col">{{ t('audit.when') }}</th><th scope="col">{{ t('audit.who') }}</th><th scope="col">{{ t('audit.what') }}</th></tr></thead>
        <tbody>
          <tr v-for="e in rows" :key="e.id">
            <td class="nowrap">{{ fmt.dateTime(e.at) }}</td>
            <td>{{ e.actor }}</td>
            <td>{{ t(`audit.actions.${e.action.replace('.', '_')}`) }}<template v-if="e.target"> · <strong>{{ e.target }}</strong></template><template v-if="keys(e)"> · {{ keys(e) }}</template></td>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseCard>
</template>

<style scoped>
h2 { font-size: var(--fs-md); margin: 0; }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: var(--fs-sm); }
th, td { text-align: start; padding: var(--space-2) var(--space-3); border-top: var(--bw) solid var(--c-border); vertical-align: top; overflow-wrap: anywhere; }
thead th { border-top: 0; color: var(--c-text-muted); }
.nowrap { white-space: nowrap; }
</style>
