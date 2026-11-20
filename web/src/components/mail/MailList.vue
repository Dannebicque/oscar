<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { clock } from '../../domain/clock'
import type { Mail } from '../../domain/types'
import { useSuivisStore } from '../../stores/suivis'
import BaseIcon from '../ui/BaseIcon.vue'
import SegmentedControl from '../ui/SegmentedControl.vue'
import ToneChip from '../ui/ToneChip.vue'
import FlagChip from '../ui/FlagChip.vue'

const props = defineProps<{ mails: Mail[]; selectedId: string | null; shared: boolean }>()
const emit = defineEmits<{ select: [id: string] }>()
const { t } = useI18n()
const fmt = useFormat()
const suivis = useSuivisStore()

type F = 'all' | 'unread' | 'attach' | 'week'
const filter = ref<F>('all')
const q = ref('')
const options = computed(() => (['all', 'unread', 'attach', 'week'] as F[]).map((v) => ({ value: v, label: t(`mail.filters.${v}`) })))
const weekAgo = () => clock.now().getTime() - 7 * 86_400_000

const visible = computed(() =>
  props.mails.filter((m) => {
    if (filter.value === 'unread' && !m.unread) return false
    if (filter.value === 'attach' && m.attachments.length === 0) return false
    if (filter.value === 'week' && new Date(m.at).getTime() < weekAgo()) return false
    const needle = q.value.trim().toLowerCase()
    return !needle || `${m.subject} ${m.from.name} ${m.paragraphs.join(' ')}`.toLowerCase().includes(needle)
  }),
)
const flagOf = (m: Mail) => (m.suivi ? suivis.byId(m.suivi) : undefined)
</script>

<template>
  <section class="list" :aria-label="t('mail.list')">
    <div class="tools">
      <div class="field">
        <label for="mail-q" class="sr-only">{{ t('mail.search') }}</label>
        <div class="search"><BaseIcon name="search" :size="16" /><input id="mail-q" v-model="q" type="search" class="input" :placeholder="t('mail.searchPlaceholder')" /></div>
      </div>
      <SegmentedControl v-model="filter" :options="options" :label="t('mail.filterLabel')" />
    </div>
    <p v-if="visible.length === 0" class="muted empty">{{ t('mail.empty') }}</p>
    <ul>
      <li v-for="m in visible" :key="m.id">
        <button type="button" class="item" :class="{ unread: m.unread }" :aria-current="m.id === selectedId ? 'true' : undefined" @click="emit('select', m.id)">
          <span class="top">
            <strong class="from"><span v-if="m.unread" class="sr-only">{{ t('mail.unread') }} : </span>{{ m.from.name }}</strong>
            <time class="muted" :datetime="m.at">{{ fmt.date(m.at) }}</time>
          </span>
          <span class="subject">{{ m.subject }}</span>
          <span class="chips">
            <ToneChip v-if="m.attachments.length" tone="neutral" icon="paperclip" :label="t('mail.attachment')" />
            <template v-if="shared && m.claim">
              <ToneChip v-if="m.claim.state === 'new'" tone="info" icon="star" :label="t('mail.claim.new')" />
              <ToneChip v-else-if="m.claim.state === 'taken'" tone="warn" icon="user" :label="t('mail.claim.taken', { who: m.claim.by })" />
              <ToneChip v-else tone="success" icon="checkCircle" :label="t('mail.claim.handled', { who: m.claim.by })" />
            </template>
            <FlagChip v-if="flagOf(m) && suivis.flag(flagOf(m)!)" :flag="suivis.flag(flagOf(m)!)!" />
            <ToneChip v-else-if="flagOf(m) && flagOf(m)!.status !== 'done'" tone="neutral" icon="follow" :label="t('mail.suivi')" />
          </span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.list { display: grid; gap: var(--space-3); align-content: start; }
.tools { display: grid; gap: var(--space-2); }
.search { position: relative; display: flex; align-items: center; }
.search svg { position: absolute; left: var(--space-3); color: var(--c-text-muted); }
.search .input { padding-left: 36px; }
ul { display: grid; gap: var(--space-2); }
.item { width: 100%; display: grid; gap: 4px; text-align: left; padding: var(--row-y) var(--space-3); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); min-height: var(--target); }
.item:hover { background: var(--c-surface-2); }
.item[aria-current='true'] { border-color: var(--c-primary); background: var(--c-primary-soft); box-shadow: inset 4px 0 0 var(--c-primary); }
.top { display: flex; justify-content: space-between; gap: var(--space-2); }
.from { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.unread .from, .unread .subject { font-weight: 800; }
.subject { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chips { display: flex; gap: var(--space-1); flex-wrap: wrap; }
.empty { padding: var(--space-4); }
time { font-size: var(--fs-sm); white-space: nowrap; }
</style>
