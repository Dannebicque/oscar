<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import { useOscar } from '../../oscar/useOscar'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import StatusChip from '../ui/StatusChip.vue'

/** Fiche d'un suivi qui n'a pas de message à montrer : créé à la main, ou message pas (ou plus) synchronisé. */
const props = defineProps<{ suiviId: string | null }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const fmt = useFormat()
const suivis = useSuivisStore()
const ui = useUiStore()
const { say } = useOscar()
const suivi = computed(() => (props.suiviId ? suivis.byId(props.suiviId) : undefined))

async function done() {
  const s = suivi.value
  if (!s) return
  emit('close')
  const undo = await suivis.close(s.id)
  ui.notify(say('closed', { subject: s.subject }), { undo: undo && (() => undo()) })
}
</script>

<template>
  <BaseDialog :open="suivi !== undefined" :title="suivi?.subject ?? ''" @close="emit('close')">
    <template v-if="suivi">
      <p class="callout" data-tone="neutral">{{ t(suivi.mailId ? 'suivis.detail.mailMissing' : 'suivis.detail.noMail') }}</p>
      <div><StatusChip :status="suivi.status" /></div>
      <dl>
        <dt>{{ t('suivis.detail.contact') }}</dt><dd>{{ suivi.contact }}</dd>
        <dt>{{ t('suivis.detail.direction') }}</dt><dd>{{ t(`suivis.detail.${suivi.direction}`) }}</dd>
        <dt>{{ t('suivis.dueLabel') }}</dt><dd>{{ suivi.dueAt ? fmt.date(suivi.dueAt) : '—' }}</dd>
        <dt>{{ t('suivis.relanceLabel') }}</dt><dd>{{ suivi.noRelance ? t('suivis.relanceStopped2') : suivi.relanceAt ? t('suivis.relanceOn', { date: fmt.date(suivi.relanceAt) }) : t('suivis.relanceNone') }}</dd>
      </dl>
      <template v-if="suivi.journal.length">
        <h3>{{ t('suivis.journal') }}</h3>
        <ol class="log">
          <li v-for="(e, i) in suivi.journal" :key="i"><time :datetime="e.at" class="muted">{{ fmt.dateTime(e.at) }}</time> · {{ fmt.journal(e) }} · {{ e.author }}</li>
        </ol>
      </template>
    </template>
    <template #footer>
      <BaseButton v-if="suivi && suivi.status !== 'done'" variant="primary" icon="checkCircle" :label="t('suivis.detail.close')" @click="done" />
      <BaseButton :label="t('suivis.detail.dismiss')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
dl { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: var(--space-1) var(--space-3); margin: 0; }
dt { color: var(--c-text-muted); }
dd { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
h3 { font-size: var(--fs-md); margin: 0; }
.log { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-1); font-size: var(--fs-sm); }
</style>
