<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, session } from '../../api'
import { useRelance } from '../../composables/useRelance'
import { clock } from '../../domain/clock'
import { daysBetween, isoDay } from '../../domain/dates'
import type { Suivi } from '../../domain/types'
import { useSettingsStore } from '../../stores/settings'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import SuggestBadge from '../ui/SuggestBadge.vue'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import ToneChip from '../ui/ToneChip.vue'

/**
 * Relances en lot (F-63) : toutes les relances dues sur un seul écran, chacune avec son brouillon à relire.
 * Plusieurs relances au même destinataire sont regroupées en un seul message (séparables d'un clic).
 * Rien ne part seul : une relance ne part que si elle a été cochée « relue » ou envoyée par son propre bouton ;
 * l'envoi de plusieurs relances d'un clic demande une confirmation qui nomme les destinataires.
 * Le plafond quotidien limite ce qui est proposé, jamais ce qui part.
 */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const store = useSuivisStore()
const settings = useSettingsStore()
const ui = useUiStore()
const relance = useRelance()

/** Une ligne de l'écran : une relance, ou plusieurs relances au même destinataire. */
interface Row { id: string; items: Suivi[] }
type State = 'idle' | 'sending' | 'sent' | 'queued' | 'error'
const rows = ref<Row[]>([])
const drafts = reactive<Record<string, string>>({})
const ai = reactive<Record<string, boolean>>({})
const ready = reactive<Record<string, boolean>>({})
const state = reactive<Record<string, State>>({})
const skipped = reactive<Record<string, 'snoozed' | 'stopped'>>({})
const errors = reactive<Record<string, string>>({})
const confirm = ref(false)
const busy = ref(false)

const first = (s: Suivi) => s.contact.split(' ')[0]
const isGroup = (r: Row) => r.items.length > 1
const total = computed(() => rows.value.reduce((n, r) => n + r.items.length, 0))
const dueTotal = computed(() => store.mine.filter((s) => store.flag(s) === 'relanceDue').length)
const later = computed(() => Math.max(0, dueTotal.value - total.value))
const finished = (r: Row) => !!skipped[r.id] || state[r.id] === 'sent' || state[r.id] === 'queued'
const todo = computed(() => rows.value.filter((r) => !finished(r)))
const readyList = computed(() => todo.value.filter((r) => ready[r.id] && state[r.id] !== 'sending'))
const overdue = (s: Suivi) => (s.relanceAt ? Math.max(0, daysBetween(s.relanceAt, isoDay(clock.now()))) : 0)

function localDraft(items: Suivi[]) {
  const s = items[0]
  if (items.length === 1) return t('suivis.relanceDraft', { first: first(s), subject: s.subject, me: session.name })
  return t('batch.groupDraft', { first: first(s), list: items.map((x) => `- ${x.subject}`).join('\n'), me: session.name })
}
function fill(list: Row[]) {
  for (const r of list) { drafts[r.id] = localDraft(r.items); ai[r.id] = false; ready[r.id] = false; state[r.id] = 'idle'; delete skipped[r.id]; delete errors[r.id] }
}

watch(() => props.open, async (o) => {
  if (!o) return
  confirm.value = false
  rows.value = relance.group([...store.relances]).map((items) => ({ id: items.length > 1 ? `g:${items[0].id}` : items[0].id, items }))
  fill(rows.value)
  // Brouillons de l'IA pour les relances seules, quand elle est active ; le modèle local reste en place sinon ou en cas de panne.
  await Promise.all(rows.value.filter((r) => !isGroup(r)).map(async (r) => {
    const draft = await store.draftRelance(r.items[0].id).catch(() => null)
    if (draft && drafts[r.id] === localDraft(r.items)) { drafts[r.id] = draft.body; ai[r.id] = draft.ai }
  }))
})
const edit = (r: Row) => { delete ai[r.id]; ready[r.id] = false }

/** Un message par sujet plutôt qu'un seul : la ligne se sépare en autant de lignes. */
function split(r: Row) {
  const parts = r.items.map((s) => ({ id: s.id, items: [s] }))
  const at = rows.value.indexOf(r)
  rows.value = [...rows.value.slice(0, at), ...parts, ...rows.value.slice(at + 1)]
  fill(parts)
}

async function sendOne(r: Row): Promise<boolean> {
  state[r.id] = 'sending'
  delete errors[r.id]
  try {
    state[r.id] = await relance.send(r.items, drafts[r.id], isGroup(r) ? t('batch.groupSubject') : undefined)
    return true
  } catch (e) {
    state[r.id] = 'error'
    const who = r.items[0].contact
    errors[r.id] = e instanceof ApiError && e.message === 'no-recipient' ? t('suivis.relanceNoRecipient', { who }) : t('suivis.relanceFailed', { who })
    return false
  }
}
async function sendReady() {
  const list = [...readyList.value]
  confirm.value = false
  busy.value = true
  let ok = 0
  for (const r of list) if (await sendOne(r)) ok += r.items.length
  busy.value = false
  const wanted = list.reduce((n, r) => n + r.items.length, 0)
  if (ok) ui.notify(t('batch.sentMany', { n: ok }, ok))
  if (ok < wanted) ui.notify(t('batch.someFailed', { n: wanted - ok }, wanted - ok), { tone: 'error' })
}
async function sendSingle(r: Row) {
  if (await sendOne(r)) ui.notify(isGroup(r) ? t('batch.sentMany', { n: r.items.length }, r.items.length) : t('suivis.relanceSent', { who: r.items[0].contact }))
}
async function snooze(r: Row) { for (const s of r.items) await store.snooze(s.id, 2); skipped[r.id] = 'snoozed'; ui.notify(t('suivis.relanceSnoozed')) }
async function stop(r: Row) { for (const s of r.items) await store.stopRelance(s.id); skipped[r.id] = 'stopped'; ui.notify(t('suivis.relanceStopped', { subject: r.items.map((s) => s.subject).join(', ') })) }
</script>

<template>
  <BaseDialog :open="open" :title="t('batch.title')" :description="t('batch.help')" @close="emit('close')">
    <div class="rb">
      <p class="cap" aria-live="polite">
        <strong>{{ t('batch.count', { n: total }, total) }}</strong>
        · {{ t('batch.cap', { n: settings.s.dailyCap }) }}
        <template v-if="later"> · {{ t('batch.later', { n: later }, later) }}</template>
      </p>

      <p v-if="rows.length === 0" class="muted">{{ t('suivis.relanceEnd') }}</p>
      <ol v-else class="list">
        <li v-for="r in rows" :key="r.id" class="item" :class="{ done: finished(r) }">
          <header class="top">
            <span class="who">
              <strong>{{ r.items[0].contact }}</strong>
              <template v-if="!isGroup(r)"> · {{ r.items[0].subject }}</template>
            </span>
            <span v-if="!isGroup(r)" class="muted meta">{{ t('batch.meta', { k: r.items[0].relanceCount + 1, n: overdue(r.items[0]) }, overdue(r.items[0])) }}</span>
            <ToneChip v-else tone="info" icon="send" :label="t('batch.grouped', { n: r.items.length }, r.items.length)" />
          </header>
          <ul v-if="isGroup(r)" class="subjects">
            <li v-for="s in r.items" :key="s.id">{{ s.subject }} <span class="muted">· {{ t('batch.meta', { k: s.relanceCount + 1, n: overdue(s) }, overdue(s)) }}</span></li>
          </ul>

          <template v-if="state[r.id] === 'sent' || state[r.id] === 'queued'">
            <ToneChip tone="success" icon="checkCircle" :label="state[r.id] === 'queued' ? t('batch.queued') : t('batch.sent')" />
          </template>
          <template v-else-if="skipped[r.id]">
            <ToneChip tone="neutral" :icon="skipped[r.id] === 'snoozed' ? 'clock' : 'pause'" :label="skipped[r.id] === 'snoozed' ? t('batch.snoozed') : t('batch.stopped')" />
          </template>
          <template v-else>
            <div class="field">
              <label :for="`rb-${r.id}`" class="lbl">{{ t('suivis.relanceMessage') }} <SuggestBadge v-if="ai[r.id] !== undefined" :ai="ai[r.id]" /></label>
              <textarea :id="`rb-${r.id}`" v-model="drafts[r.id]" class="input" :rows="isGroup(r) ? 8 : 5" @input="edit(r)" />
            </div>
            <p v-if="errors[r.id]" class="err" role="alert"><ToneChip tone="danger" icon="alert" :label="errors[r.id]" /></p>
            <div class="acts">
              <label class="rd"><input v-model="ready[r.id]" type="checkbox" :disabled="busy" /> {{ t('batch.ready') }}</label>
              <BaseButton icon="send" :disabled="busy || state[r.id] === 'sending'" :label="t('suivis.relanceSend')" @click="sendSingle(r)" />
              <BaseButton icon="clock" :disabled="busy" :label="t('suivis.relanceSnooze')" @click="snooze(r)" />
              <BaseButton icon="pause" :disabled="busy" :label="t('suivis.relanceStop')" @click="stop(r)" />
              <BaseButton v-if="isGroup(r)" variant="ghost" :disabled="busy" :label="t('batch.split')" @click="split(r)" />
            </div>
          </template>
        </li>
      </ol>

      <div v-if="confirm" class="confirm" role="alertdialog" :aria-label="t('batch.confirmTitle')">
        <p><strong>{{ t('batch.confirmTitle', { n: readyList.length }, readyList.length) }}</strong> {{ readyList.map((r) => r.items[0].contact).join(', ') }}.</p>
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
.item.done { background: var(--c-surface); }
.top { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.who { overflow-wrap: anywhere; }
.meta { font-size: var(--fs-sm); }
.subjects { margin: 0; padding-inline-start: var(--space-5); display: grid; gap: 2px; font-size: var(--fs-sm); }
.field { display: grid; gap: var(--space-1); }
.lbl { display: inline-flex; align-items: center; gap: var(--space-2); font-weight: 700; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: center; }
.rd { display: inline-flex; align-items: center; gap: var(--space-2); min-height: var(--target); font-weight: 600; }
.confirm { display: grid; gap: var(--space-2); padding: var(--space-3); border: var(--bw-card) solid var(--c-warn); border-radius: var(--radius-md); background: var(--c-warn-soft); }
.confirm p { margin: 0; }
.err { margin: 0; }
</style>
