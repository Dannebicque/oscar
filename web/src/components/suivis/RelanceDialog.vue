<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, session } from '../../api'
import { useRelance } from '../../composables/useRelance'
import type { Suivi } from '../../domain/types'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import SuggestBadge from '../ui/SuggestBadge.vue'
import OscarSays from '../ui/OscarSays.vue'
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
const relance = useRelance()
const sending = ref(false)

const done = ref<Set<string>>(new Set())
const body = ref('')
const pending = computed(() => props.queue.filter((s) => !done.value.has(s.id)))
const current = computed(() => pending.value[0] ?? null)
const total = computed(() => props.queue.length)
const index = computed(() => done.value.size + 1)

const first = (s: Suivi) => s.contact.split(' ')[0]
function localDraft(s: Suivi) {
  return t('suivis.relanceDraft', { first: first(s), subject: s.subject, me: session.name })
}
const byModel = ref(false)
/** Brouillon d'Oscar s'il est actif ; sinon le texte type. L'utilisateur relit toujours avant d'envoyer. */
async function resetDraft() {
  const s = current.value
  body.value = s ? localDraft(s) : ''
  byModel.value = false
  if (!s) return
  const draft = await store.draftRelance(s.id).catch(() => null)
  if (draft && current.value?.id === s.id && body.value === localDraft(s)) { body.value = draft.body; byModel.value = draft.ai }
}
watch(() => props.open, (o) => { if (o) { done.value = new Set(); resetDraft() } })
watch(current, resetDraft)

async function act(kind: 'send' | 'snooze' | 'stop') {
  const s = current.value
  if (!s) return
  if (kind === 'send') {
    // Un vrai envoi, en réponse dans le fil : rien n'est marqué envoyé s'il échoue.
    sending.value = true
    try {
      await relance.send(s, body.value)
      ui.notify(t('suivis.relanceSent', { who: s.contact }))
    } catch (e) {
      ui.notify(e instanceof ApiError && e.message === 'no-recipient' ? t('suivis.relanceNoRecipient', { who: s.contact }) : t('suivis.relanceFailed', { who: s.contact }), { tone: 'error' })
      return
    } finally {
      sending.value = false
    }
  }
  if (kind === 'snooze') { await store.snooze(s.id, 2); ui.notify(t('suivis.relanceSnoozed')) }
  if (kind === 'stop') { await store.stopRelance(s.id); ui.notify(t('suivis.relanceStopped', { subject: s.subject })) }
  done.value = new Set([...done.value, s.id])
}
</script>

<template>
  <BaseDialog :open="open" :title="single ? t('mail.draft.title') : t('suivis.relanceMode')" @close="emit('close')">
    <template v-if="current">
      <OscarSays situation="draftReady" :level="3" suggestion :ai="byModel" />
      <p class="top">
        <SuggestBadge :ai="byModel" />
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
        <BaseButton variant="primary" icon="send" :disabled="sending" :label="t('suivis.relanceSend')" @click="act('send')" />
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
