<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ME } from '../../api'
import type { Suivi } from '../../domain/types'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import AiBadge from '../ui/AiBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'

/**
 * Mode « Relances » (F-63) : une par une, compteur, brouillon modifiable, envoyer / reporter / ne plus relancer.
 * Jamais d'envoi groupé : chaque envoi est un clic.
 */
const props = defineProps<{ open: boolean; queue: Suivi[]; single?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const store = useSuivisStore()
const ui = useUiStore()

const done = ref<Set<string>>(new Set())
const body = ref('')
const pending = computed(() => props.queue.filter((s) => !done.value.has(s.id)))
const current = computed(() => pending.value[0] ?? null)
const total = computed(() => props.queue.length)
const index = computed(() => done.value.size + 1)

const first = (s: Suivi) => s.contact.split(' ')[0]
function resetDraft() {
  body.value = current.value ? t('suivis.relanceDraft', { first: first(current.value), subject: current.value.subject, me: ME }) : ''
}
watch(() => props.open, (o) => { if (o) { done.value = new Set(); resetDraft() } })
watch(current, resetDraft)

async function act(kind: 'send' | 'snooze' | 'stop') {
  const s = current.value
  if (!s) return
  if (kind === 'send') { await store.relanceSent(s.id); ui.notify(t('suivis.relanceSent', { who: s.contact })) }
  if (kind === 'snooze') { await store.snooze(s.id, 2); ui.notify(t('suivis.relanceSnoozed')) }
  if (kind === 'stop') { await store.stopRelance(s.id); ui.notify(t('suivis.relanceStopped', { subject: s.subject })) }
  done.value = new Set([...done.value, s.id])
}
</script>

<template>
  <BaseDialog :open="open" :title="single ? t('mail.draft.title') : t('suivis.relanceMode')" @close="emit('close')">
    <template v-if="current">
      <p class="top">
        <AiBadge />
        <span v-if="!single" class="muted" aria-live="polite">{{ t('suivis.relanceCounter', { i: index, n: total }) }}</span>
      </p>
      <p class="muted">{{ t('suivis.relanceTo', { who: current.contact }) }} · {{ t('suivis.relanceSubject', { subject: current.subject }) }}</p>
      <div class="field">
        <label for="relance-body">{{ t('suivis.relanceMessage') }}</label>
        <textarea id="relance-body" v-model="body" class="input" rows="8" />
      </div>
    </template>
    <p v-else class="end">{{ t('suivis.relanceEnd') }}</p>
    <template #footer>
      <template v-if="current">
        <BaseButton variant="primary" icon="send" :label="t('suivis.relanceSend')" @click="act('send')" />
        <BaseButton icon="clock" :label="t('suivis.relanceSnooze')" @click="act('snooze')" />
        <BaseButton icon="pause" :label="t('suivis.relanceStop')" @click="act('stop')" />
      </template>
      <BaseButton :label="t('common.close')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.top { display: flex; gap: var(--space-3); align-items: center; }
.end { padding: var(--space-5) 0; text-align: center; font-weight: 600; }
</style>
