<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { holdSignal, overlapping } from '../../domain/holds'
import { plainSubject, sameId } from '../../domain/mailIds'
import type { SlotHold } from '../../domain/types'
import { useAgendaStore } from '../../stores/agenda'
import { useMailsStore } from '../../stores/mails'
import { useSuivisStore } from '../../stores/suivis'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import SelectField from '../ui/SelectField.vue'
import ToneChip from '../ui/ToneChip.vue'

/**
 * Créneaux en provisoire (F-74), groupés par proposition. Oscar dit où on en est et propose ; retenir ou libérer reste un clic.
 * `highlight` : la proposition désignée depuis la grille de l'agenda.
 */
const props = defineProps<{ holds: SlotHold[]; highlight?: string | null; mailLink?: boolean }>()
const { t } = useI18n()
const fmt = useFormat()
const router = useRouter()
const agenda = useAgendaStore()
const mails = useMailsStore()
const suivis = useSuivisStore()
const ui = useUiStore()

/** Un message reçu qui répond à notre proposition vaut « a répondu », même sans suivi. */
const replied = (h: SlotHold) => mails.items.some((m) => m.folder !== 'sent' && m.folder !== 'drafts' && (m.parents ?? []).some((p) => sameId(p, h.mailId)))

const groups = computed(() => {
  const by = new Map<string, SlotHold[]>()
  for (const h of props.holds) by.set(h.groupId, [...(by.get(h.groupId) ?? []), h])
  return [...by.entries()].map(([id, list]) => ({
    id, list: [...list].sort((a, b) => Date.parse(a.start) - Date.parse(b.start)), first: list[0],
    signal: holdSignal(list[0], list[0].suiviId ? suivis.byId(list[0].suiviId) : undefined, replied(list[0]), clock.now()),
  }))
})
/** Ce qui occupe déjà la plage : un rendez-vous, ou un créneau proposé à quelqu'un d'autre. */
function clash(h: SlotHold): string | null {
  const e = agenda.work.find((x) => overlapping(x, h))
  if (e) return t('holds.clashEvent', { title: e.title })
  const other = agenda.holds.find((x) => x.groupId !== h.groupId && overlapping(x, h))
  return other ? t('holds.clashHold', { who: other.contact }) : null
}
const label = (h: SlotHold) => `${fmt.dateLong(h.start)}, ${fmt.time(h.start)} – ${fmt.time(h.end)}`

const calendars = computed(() => agenda.sources.filter((s) => !s.personal))
const choice = ref('')
const calendar = computed(() => choice.value || calendars.value[0]?.id || '')
const busy = ref(false)
async function run(action: () => Promise<unknown>, done: string, failed = t('common.actionFailed')) {
  busy.value = true
  try { await action(); ui.notify(done) } catch { ui.notify(failed, { tone: 'error' }) } finally { busy.value = false }
}
const keep = (h: SlotHold) => run(
  () => agenda.keepHold(h, calendar.value, `${plainSubject(h.subject)} · ${h.contact}`),
  t('holds.kept', { when: label(h) }), t('agenda.createFailed'),
)
const release = (h: SlotHold) => run(() => agenda.releaseHold(h.id), t('holds.released'))
const releaseAll = (groupId: string) => run(() => agenda.releaseGroup(groupId), t('holds.releasedAll'))
function openMail(h: SlotHold) {
  const m = mails.openTarget(h.mailId) ?? mails.openTarget(h.repliedToMailId)
  if (m) router.push({ name: 'mails', params: { id: m.id } })
  else ui.notify(t('holds.noMail'), { tone: 'info' })
}
</script>

<template>
  <div class="holds">
    <SelectField v-if="calendars.length > 1" id="hold-cal" :model-value="calendar" :label="t('holds.calendar')" :options="calendars.map((c) => ({ value: c.id, label: c.label }))" @update:model-value="(v: string) => (choice = v)" />
    <section v-for="g in groups" :key="g.id" class="group" :class="{ hit: g.id === highlight }" :data-group="g.id" :aria-label="t('holds.proposedTo', { who: g.first.contact })">
      <p class="head"><ToneChip tone="neutral" icon="clock" :label="t('holds.chip')" /><strong>{{ t('holds.proposedTo', { who: g.first.contact }) }}</strong></p>
      <p class="muted subject">{{ g.first.subject }}</p>
      <p v-if="g.signal !== 'pending'" class="callout" data-tone="warn" role="status">{{ t(`holds.signal.${g.signal}`, { who: g.first.contact }) }}</p>
      <ul>
        <li v-for="h in g.list" :key="h.id">
          <p class="when"><time :datetime="h.start">{{ label(h) }}</time></p>
          <p v-if="clash(h)" class="clash"><ToneChip tone="warn" icon="alert" :label="clash(h)!" /></p>
          <div class="acts">
            <BaseButton icon="check" :disabled="busy || !calendar" :label="t('holds.keep')" :aria-label="t('holds.keepAria', { when: label(h) })" @click="keep(h)" />
            <BaseButton variant="ghost" icon="x" :disabled="busy" :label="t('holds.release')" :aria-label="t('holds.releaseAria', { when: label(h) })" @click="release(h)" />
          </div>
        </li>
      </ul>
      <div class="acts">
        <BaseButton v-if="mailLink !== false" variant="ghost" icon="mail" :label="t('holds.openMail')" @click="openMail(g.first)" />
        <BaseButton v-if="g.list.length > 1" variant="ghost" icon="trash" :disabled="busy" :label="t('holds.releaseAll')" @click="releaseAll(g.id)" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.holds, .group, ul, li { display: grid; gap: var(--space-2); min-width: 0; }
.group { padding: var(--space-3); border: var(--bw) dashed var(--c-border-strong); border-radius: var(--radius-md); }
.group.hit { border-color: var(--c-primary); border-style: solid; }
ul { list-style: none; margin: 0; padding: 0; }
li + li { border-top: var(--bw) solid var(--c-border); padding-top: var(--space-2); }
p { margin: 0; }
.head, .acts { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: center; }
.subject { overflow-wrap: anywhere; }
.when { font-weight: 700; }
</style>
