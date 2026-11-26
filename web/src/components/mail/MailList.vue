<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import { emptyCriteria, matches, parseQuery } from '../../domain/mailSearch'
import type { Mail } from '../../domain/types'
import { useSuivisStore } from '../../stores/suivis'
import BaseButton from '../ui/BaseButton.vue'
import Avatar from '../ui/Avatar.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import FlagChip from '../ui/FlagChip.vue'
import SegmentedControl from '../ui/SegmentedControl.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Liste dense des messages (standard Gmail / Outlook / Thunderbird) : expéditeur, heure, objet, aperçu, pastilles. */
const props = defineProps<{ mails: Mail[]; selectedId: string | null; shared: boolean; folder?: string }>()
const emit = defineEmits<{ select: [id: string]; bulk: [action: 'archive' | 'trash' | 'inbox' | 'move', ids: string[]] }>()
const { t } = useI18n()
const fmt = useFormat()
const suivis = useSuivisStore()

type F = 'all' | 'unread' | 'attach' | 'week'
const filter = ref<F>('all')
const q = ref('')
/** Filtres avancés (F-12) : le panneau et les opérateurs tapés dans la recherche (de:, objet:, apres:, avant:, pj, suivi) se combinent. */
const adv = ref(emptyCriteria())
const advOpen = ref(false)
const criteria = computed(() => parseQuery(q.value, adv.value))
const activeCount = computed(() => [criteria.value.from, criteria.value.subject, criteria.value.after, criteria.value.before, criteria.value.attachment, criteria.value.followed, criteria.value.unread].filter(Boolean).length)
const reset = () => { adv.value = emptyCriteria(); q.value = '' }
const options = computed(() => (['all', 'unread', 'attach', 'week'] as F[]).map((v) => ({ value: v, label: t(`mail.filters.${v}`) })))
const weekAgo = () => clock.now().getTime() - 7 * 86_400_000

const visible = computed(() =>
  props.mails.filter((m) => {
    if (filter.value === 'unread' && !m.unread) return false
    if (filter.value === 'attach' && m.attachments.length === 0) return false
    if (filter.value === 'week' && new Date(m.at).getTime() < weekAgo()) return false
    return matches(m, criteria.value)
  }),
)
const suiviOf = (m: Mail) => (m.suivi ? suivis.byId(m.suivi) : undefined)
const preview = (m: Mail) => m.paragraphs.find((p) => p.length > 20) ?? ''
/** Traitement par lot : on coche des messages, puis une action s'applique à tous (avec annulation). */
const selecting = ref(false)
// Le message ouvert depuis un suivi ou l'accueil peut être loin dans la liste : on l'amène à l'écran.
const root = ref<HTMLElement | null>(null)
watch([() => props.selectedId, () => props.mails.length], async () => {
  await nextTick()
  root.value?.querySelector('[aria-current="true"]')?.scrollIntoView?.({ block: 'nearest' })
}, { immediate: true, flush: 'post' })
const picked = ref<Set<string>>(new Set())
const pickedIds = computed(() => visible.value.filter((m) => picked.value.has(m.id)).map((m) => m.id))
const allPicked = computed(() => visible.value.length > 0 && pickedIds.value.length === visible.value.length)
function toggle(id: string) {
  const next = new Set(picked.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  picked.value = next
}
const toggleAll = () => { picked.value = allPicked.value ? new Set() : new Set(visible.value.map((m) => m.id)) }
function clearSelection() { picked.value = new Set(); selecting.value = false }
const run = (action: 'archive' | 'trash' | 'inbox' | 'move') => emit('bulk', action, pickedIds.value)
defineExpose({ visible, clearSelection })
</script>

<template>
  <section ref="root" class="list" :aria-label="t('mail.list')">
    <div class="tools">
      <div class="search"><BaseIcon name="search" :size="16" /><label for="mail-q" class="sr-only">{{ t('mail.search') }}</label><input id="mail-q" v-model="q" type="search" class="input" :placeholder="t('mail.searchPlaceholder')" /></div>
      <SegmentedControl v-model="filter" :options="options" :label="t('mail.filterLabel')" />
      <div class="adv-bar">
        <button type="button" class="adv-toggle sel-toggle" :aria-pressed="selecting" @click="selecting ? clearSelection() : (selecting = true)"><BaseIcon name="checkCircle" :size="16" />{{ selecting ? t('mail.bulk.done') : t('mail.bulk.select') }}</button>
        <button type="button" class="adv-toggle" :aria-expanded="advOpen" aria-controls="mail-adv" @click="advOpen = !advOpen"><BaseIcon name="filter" :size="16" />{{ t('mail.adv.toggle') }}<span v-if="activeCount" class="adv-n">{{ t('mail.adv.active', { n: activeCount }) }}</span></button>
        <button v-if="activeCount || q" type="button" class="adv-reset" @click="reset">{{ t('mail.adv.reset') }}</button>
      </div>
      <fieldset v-show="advOpen" id="mail-adv" class="adv">
        <legend class="sr-only">{{ t('mail.adv.toggle') }}</legend>
        <label class="adv-f"><span>{{ t('mail.adv.from') }}</span><input v-model="adv.from" type="text" class="input" autocomplete="off" /></label>
        <label class="adv-f"><span>{{ t('mail.adv.subject') }}</span><input v-model="adv.subject" type="text" class="input" autocomplete="off" /></label>
        <label class="adv-f"><span>{{ t('mail.adv.after') }}</span><input v-model="adv.after" type="date" class="input" /></label>
        <label class="adv-f"><span>{{ t('mail.adv.before') }}</span><input v-model="adv.before" type="date" class="input" /></label>
        <label class="adv-c"><input v-model="adv.attachment" type="checkbox" /> {{ t('mail.adv.attachment') }}</label>
        <label class="adv-c"><input v-model="adv.followed" type="checkbox" /> {{ t('mail.adv.followed') }}</label>
        <label class="adv-c"><input v-model="adv.unread" type="checkbox" /> {{ t('mail.adv.unread') }}</label>
        <p class="hint adv-help">{{ t('mail.adv.help') }}</p>
      </fieldset>
    </div>
    <div v-if="selecting" class="bulk" role="toolbar" :aria-label="t('mail.bulk.label')">
      <label class="bulk-all"><input type="checkbox" :checked="allPicked" @change="toggleAll" /> {{ t('mail.bulk.all') }}</label>
      <span class="bulk-n" aria-live="polite">{{ t('mail.bulk.count', { n: pickedIds.length }, pickedIds.length) }}</span>
      <span class="bulk-acts">
        <BaseButton v-if="folder !== 'archive'" icon="archive" icon-only :title="t('mail.archive')" :label="t('mail.archive')" :disabled="!pickedIds.length" @click="run('archive')" />
        <BaseButton v-if="folder !== 'inbox'" icon="inbox" icon-only :title="t('mail.folder.inbox')" :label="t('mail.bulk.toInbox')" :disabled="!pickedIds.length" @click="run('inbox')" />
        <BaseButton icon="folder" icon-only :title="t('mail.move')" :label="t('mail.move')" :disabled="!pickedIds.length" @click="run('move')" />
        <BaseButton v-if="folder !== 'trash'" icon="trash" icon-only :title="t('mail.delete')" :label="t('mail.delete')" :disabled="!pickedIds.length" @click="run('trash')" />
      </span>
    </div>
    <p v-if="visible.length === 0" class="muted empty">{{ t('mail.empty') }}</p>
    <ul class="items" role="list">
      <li v-for="m in visible" :key="m.id" :data-mail="m.id" :class="{ sel: selecting }">
        <label v-if="selecting" class="pick"><input type="checkbox" :checked="picked.has(m.id)" :aria-label="t('mail.bulk.pick', { subject: m.subject })" @change="toggle(m.id)" /></label>
        <button type="button" class="item" :class="{ unread: m.unread, on: m.id === selectedId }" :aria-current="m.id === selectedId ? 'true' : undefined" @click="emit('select', m.id)">
          <Avatar :name="m.from.name" :size="34" />
          <span class="body">
            <span class="top">
              <strong class="from"><span v-if="m.unread" class="sr-only">{{ t('mail.unread') }} : </span>{{ m.from.name }}</strong>
              <time class="muted" :datetime="m.at">{{ fmt.date(m.at) }}</time>
            </span>
            <span class="subject">{{ m.subject }}</span>
            <span v-if="preview(m)" class="snippet muted">{{ preview(m) }}</span>
            <span v-if="m.attachments.length || (shared && m.claim) || suiviOf(m)" class="chips">
              <ToneChip v-if="m.attachments.length" tone="neutral" icon="paperclip" :label="t('mail.attachment')" />
              <template v-if="shared && m.claim">
                <ToneChip v-if="m.claim.state === 'new'" tone="info" icon="star" :label="t('mail.claim.new')" />
                <ToneChip v-else-if="m.claim.state === 'taken'" tone="warn" icon="user" :label="t('mail.claim.taken', { who: m.claim.by })" />
                <ToneChip v-else tone="success" icon="checkCircle" :label="t('mail.claim.handled', { who: m.claim.by })" />
              </template>
              <FlagChip v-if="suiviOf(m) && suivis.flag(suiviOf(m)!)" :flag="suivis.flag(suiviOf(m)!)!" />
              <ToneChip v-else-if="suiviOf(m) && suiviOf(m)!.status !== 'done'" tone="neutral" icon="follow" :label="t('mail.suivi')" />
            </span>
          </span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.list { display: flex; flex-direction: column; min-height: 0; background: var(--c-surface); border-right: var(--bw) solid var(--c-border); }
.tools { display: grid; gap: var(--space-2); padding: var(--space-3); border-bottom: var(--bw) solid var(--c-border); }
.search { position: relative; display: flex; align-items: center; }
.search svg { position: absolute; left: var(--space-3); color: var(--c-text-muted); pointer-events: none; }
.search .input { padding-left: 36px; }
.tools :deep(.seg) { display: flex; flex-wrap: nowrap; overflow-x: auto; }
.tools :deep(.seg-item) { flex: 1 1 auto; padding: 0 var(--space-2); white-space: nowrap; }
.bulk { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; padding: var(--space-2) var(--space-3); background: var(--c-primary-soft); color: var(--c-on-primary-soft); border-bottom: var(--bw) solid var(--c-border); font-weight: 600; }
.bulk-all { display: inline-flex; align-items: center; gap: var(--space-2); min-height: var(--target); }
.bulk-n { flex: 1; min-width: 80px; }
.bulk-acts { display: flex; gap: var(--space-1); }
.items li.sel { display: flex; align-items: stretch; }
.items li.sel .item { flex: 1; min-width: 0; }
.pick { display: flex; align-items: center; justify-content: center; min-width: var(--target); cursor: pointer; }
.adv-bar { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); flex-wrap: wrap; }
.adv-toggle, .adv-reset { min-height: var(--target); display: inline-flex; align-items: center; gap: var(--space-2); background: transparent; border: 0; color: var(--c-accent-text, var(--c-accent)); font: inherit; font-weight: 700; text-decoration: underline; padding: 0 var(--space-2); }
.adv-n { font-weight: 800; color: var(--c-text); text-decoration: none; }
.adv { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2) var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-3); margin: 0; min-width: 0; }
.adv-f { display: grid; gap: 2px; font-weight: 600; font-size: var(--fs-sm); min-width: 0; }
.adv-c { display: flex; align-items: center; gap: var(--space-2); min-height: var(--target); grid-column: 1 / -1; }
.adv-help { grid-column: 1 / -1; margin: 0; }
.items { overflow-y: auto; min-height: 0; flex: 1; }
.items li { border-bottom: var(--bw) solid var(--c-border); }
.item { width: 100%; display: flex; gap: var(--space-3); text-align: left; padding: var(--row-y) var(--space-3); min-height: var(--target); background: transparent; border: 0; border-inline-start: 3px solid transparent; color: inherit; }
.item:hover { background: var(--c-surface-2); }
.item.on { background: var(--c-primary-soft); border-inline-start-color: var(--c-accent); }
.body { display: grid; gap: 2px; min-width: 0; flex: 1; }
.top { display: flex; justify-content: space-between; gap: var(--space-2); }
.from { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.unread .from, .unread .subject { font-weight: 800; }
.unread::before { content: ''; align-self: center; width: 8px; height: 8px; border-radius: 50%; background: var(--c-accent); flex: none; margin-inline-end: -4px; }
.subject, .snippet { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.snippet { font-size: var(--fs-sm); }
.chips { display: flex; gap: var(--space-1); flex-wrap: wrap; margin-top: 2px; }
time { font-size: var(--fs-sm); white-space: nowrap; }
.empty { padding: var(--space-4); }
</style>
