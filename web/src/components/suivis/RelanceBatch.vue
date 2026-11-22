<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, session } from '../../api'
import { useFormat } from '../../composables/useFormat'
import { useRelance } from '../../composables/useRelance'
import { clock } from '../../domain/clock'
import { daysBetween, isoDay } from '../../domain/dates'
import type { Suivi } from '../../domain/types'
import { useSettingsStore } from '../../stores/settings'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import AiBadge from '../ui/AiBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ToneChip from '../ui/ToneChip.vue'

/**
 * Relances en lot (F-63) : toutes les relances dues sur un seul écran, chacune avec son brouillon à relire.
 * Rien ne part seul : une relance ne peut partir que si l'utilisateur l'a cochée « relue », et l'envoi groupé demande une confirmation.
 * Le plafond quotidien limite ce qui est proposé, jamais ce qui part.
 */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const fmt = useFormat()
const store = useSuivisStore()
const settings = useSettingsStore()
const ui = useUiStore()
const relance = useRelance()

type State = 'idle' | 'sending' | 'sent' | 'queued' | 'error'
const drafts = reactive<Record<string, string>>({})
const ai = reactive<Record<string, boolean>>({})
const ready = reactive<Record<string, boolean>>({})
const state = reactive<Record<string, State>>({})
const skipped = reactive<Record<string, 'snoozed' | 'stopped'>>({})
const errors = reactive<Record<string, string>>({})
const confirm = ref(false)
const busy = ref(false)
/** Les relances proposées à l'ouverture : la liste ne bouge pas pendant qu'on la relit. */
const queue = ref<Suivi[]>([])

const first = (s: Suivi) => s.contact.split(' ')[0]
const localDraft = (s: Suivi) => t('suivis.relanceDraft', { first: first(s), subject: s.subject, me: session.name })
const dueTotal = computed(() => store.mine.filter((s) => store.flag(s) === 'relanceDue').length)
const later = computed(() => Math.max(0, dueTotal.value - queue.value.length))
const todo = computed(() => queue.value.filter((s) => !skipped[s.id] && state[s.id] !== 'sent' && state[s.id] !== 'queued'))
const readyList = computed(() => todo.value.filter((s) => ready[s.id] && state[s.id] !== 'sending'))
const overdue = (s: Suivi) => (s.relanceAt ? Math.max(0, daysBetween(s.relanceAt, isoDay(clock.now()))) : 0)

watch(() => props.open, async (o) => {
  if (!o) return
  confirm.value = false
  queue.value = [...store.relances]
  for (const s of queue.value) {
    drafts[s.id] = localDraft(s); ai[s.id] = false; ready[s.id] = false; state[s.id] = 'idle'; delete skipped[s.id]; delete errors[s.id]
  }
  // Brouillons de l'IA, un par relance, quand elle est active ; le modèle local reste en place sinon ou en cas de panne.
  await Promise.all(queue.value.map(async (s) => {
    const text = await store.draftRelance(s.id).catch(() => null)
    if (text && drafts[s.id] === localDraft(s)) { drafts[s.id] = text; ai[s.id] = true }
  }))
})
const edit = (s: Suivi) => { ai[s.id] = false; ready[s.id] = false }

async function sendOne(s: Suivi): Promise<boolean> {
  state[s.id] = 'sending'
  delete errors[s.id]
  try {
    state[s.id] = await relance.send(s, drafts[s.id])
    return true
  } catch (e) {
    state[s.id] = 'error'
    errors[s.id] = e instanceof ApiError && e.message === 'no-recipient' ? t('suivis.relanceNoRecipient', { who: s.contact }) : t('suivis.relanceFailed', { who: s.contact })
    return false
  }
}
async function sendReady() {
  const list = [...readyList.value]
  confirm.value = false
  busy.value = true
  let ok = 0
  for (const s of list) if (await sendOne(s)) ok++
  busy.value = false
  if (ok) ui.notify(t('batch.sentMany', { n: ok }, ok))
  if (ok < list.length) ui.notify(t('batch.someFailed', { n: list.length - ok }, list.length - ok), { tone: 'error' })
}
async function sendSingle(s: Suivi) {
  if (await sendOne(s)) ui.notify(t('suivis.relanceSent', { who: s.contact }))
}
async function snooze(s: Suivi) { await store.snooze(s.id, 2); skipped[s.id] = 'snoozed'; ui.notify(t('suivis.relanceSnoozed')) }
async function stop(s: Suivi) { await store.stopRelance(s.id); skipped[s.id] = 'stopped'; ui.notify(t('suivis.relanceStopped', { subject: s.subject })) }
</script>

<template>
  <BaseDialog :open="open" :title="t('batch.title')" :description="t('batch.help')" @close="emit('close')">
    <div class="rb">
      <p class="cap" aria-live="polite">
        <strong>{{ t('batch.count', { n: queue.length }, queue.length) }}</strong>
        · {{ t('batch.cap', { n: settings.s.dailyCap }) }}
        <template v-if="later"> · {{ t('batch.later', { n: later }, later) }}</template>
      </p>

      <p v-if="queue.length === 0" class="muted">{{ t('suivis.relanceEnd') }}</p>
      <ol v-else class="list">
        <li v-for="s in queue" :key="s.id" class="item" :class="{ done: skipped[s.id] || state[s.id] === 'sent' || state[s.id] === 'queued' }">
          <header class="top">
            <span class="who"><strong>{{ s.contact }}</strong> · {{ s.subject }}</span>
            <span class="muted meta">{{ t('batch.meta', { k: s.relanceCount + 1, n: overdue(s) }, overdue(s)) }}</span>
          </header>

          <template v-if="state[s.id] === 'sent' || state[s.id] === 'queued'">
            <ToneChip tone="success" icon="checkCircle" :label="state[s.id] === 'queued' ? t('batch.queued') : t('batch.sent')" />
          </template>
          <template v-else-if="skipped[s.id]">
            <ToneChip tone="neutral" :icon="skipped[s.id] === 'snoozed' ? 'clock' : 'pause'" :label="skipped[s.id] === 'snoozed' ? t('batch.snoozed') : t('batch.stopped')" />
          </template>
          <template v-else>
            <div class="field">
              <label :for="`rb-${s.id}`" class="lbl">{{ t('suivis.relanceMessage') }} <AiBadge v-if="ai[s.id]" /></label>
              <textarea :id="`rb-${s.id}`" v-model="drafts[s.id]" class="input" rows="5" @input="edit(s)" />
            </div>
            <p v-if="errors[s.id]" class="err" role="alert"><ToneChip tone="danger" icon="alert" :label="errors[s.id]" /></p>
            <div class="acts">
              <label class="rd"><input v-model="ready[s.id]" type="checkbox" :disabled="busy" /> {{ t('batch.ready') }}</label>
              <BaseButton icon="send" :disabled="busy || state[s.id] === 'sending'" :label="t('suivis.relanceSend')" @click="sendSingle(s)" />
              <BaseButton icon="clock" :disabled="busy" :label="t('suivis.relanceSnooze')" @click="snooze(s)" />
              <BaseButton icon="pause" :disabled="busy" :label="t('suivis.relanceStop')" @click="stop(s)" />
            </div>
          </template>
        </li>
      </ol>

      <div v-if="confirm" class="confirm" role="alertdialog" :aria-label="t('batch.confirmTitle')">
        <p><strong>{{ t('batch.confirmTitle', { n: readyList.length }, readyList.length) }}</strong> {{ readyList.map((s) => s.contact).join(', ') }}.</p>
        <p class="muted">{{ t('batch.confirmText') }}</p>
        <div class="acts">
          <BaseButton variant="primary" icon="send" :label="t('batch.confirmYes')" @click="sendReady" />
          <BaseButton :label="t('batch.back')" @click="confirm = false" />
        </div>
      </div>
    </div>
    <template #footer>
      <BaseButton v-if="!confirm" variant="primary" icon="send" :disabled="busy || readyList.length === 0" :label="t('batch.sendReady', { n: readyList.length }, readyList.length)" @click="confirm = true" />
      <BaseButton :label="t('common.close')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.rb { display: grid; gap: var(--space-3); }
.cap { margin: 0; }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); max-height: 55vh; overflow-y: auto; }
.item { display: grid; gap: var(--space-2); padding: var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface-2); }
.item.done { opacity: 1; background: var(--c-surface); }
.top { display: flex; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
.who { overflow-wrap: anywhere; }
.meta { font-size: var(--fs-sm); }
.field { display: grid; gap: var(--space-1); }
.lbl { display: inline-flex; align-items: center; gap: var(--space-2); font-weight: 700; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: center; }
.rd { display: inline-flex; align-items: center; gap: var(--space-2); min-height: var(--target); font-weight: 600; }
.confirm { display: grid; gap: var(--space-2); padding: var(--space-3); border: var(--bw-card) solid var(--c-warn); border-radius: var(--radius-md); background: var(--c-warn-soft); }
.confirm p { margin: 0; }
.err { margin: 0; }
</style>
