<script setup lang="ts">
import { TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText, TagsInputRoot } from 'reka-ui'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { extractAddress } from '../../api/http'
import { searchDirectory } from '../../domain/directory'
import BaseIcon from './BaseIcon.vue'

/**
 * Destinataires (F-73, F-18). Pastilles, suppression et navigation entre pastilles : TagsInput de Reka UI.
 * Suggestions de l'annuaire : motif ARIA « combobox à liste » (flèches, Entrée, Échap), écrit ici car l'assemblage
 * Combobox + TagsInput de Reka réécrivait le texte tapé. Une adresse libre est acceptée si elle est valide.
 */
const props = defineProps<{ modelValue: string[]; id: string; label: string; invalid?: boolean; describedBy?: string }>()
const emit = defineEmits<{ 'update:modelValue': [v: string[]] }>()
const { t } = useI18n()

const search = ref('')
const rejected = ref('')
const active = ref(-1)
const dismissed = ref(false)
const listId = `${props.id}-list`
const suggestions = computed(() => searchDirectory(search.value).filter((e) => !props.modelValue.includes(e.address)))
const open = computed(() => !dismissed.value && search.value.trim().length > 0)

const field = (e: Event) => e.target as HTMLInputElement
const dirName = (address: string) => searchDirectory(address, 1).find((e) => e.address === address)?.name ?? ''

function add(address: string, input?: HTMLInputElement | null) {
  if (!props.modelValue.includes(address)) emit('update:modelValue', [...props.modelValue, address])
  search.value = ''
  rejected.value = ''
  active.value = -1
  dismissed.value = false
  const el = input ?? (document.getElementById(props.id) as HTMLInputElement | null)
  if (el) el.value = ''
}
/** Valide ce qui est tapé (lu dans le DOM : c'est la vérité). Une adresse invalide est refusée et annoncée. */
function commit(raw: string, input?: HTMLInputElement | null) {
  const text = raw.trim()
  if (!text) return
  const address = extractAddress(text)
  if (!address) { rejected.value = text; return }
  add(address, input)
}
function onInput(e: Event) {
  search.value = field(e).value
  rejected.value = ''
  active.value = -1
  dismissed.value = false
}
function onKey(e: KeyboardEvent) {
  const el = field(e)
  const list = suggestions.value
  if (e.key === 'ArrowDown' && open.value && list.length) { e.preventDefault(); active.value = (active.value + 1) % list.length }
  else if (e.key === 'ArrowUp' && open.value && list.length) { e.preventDefault(); active.value = (active.value - 1 + list.length) % list.length }
  else if (e.key === 'Escape' && open.value) { e.preventDefault(); e.stopPropagation(); dismissed.value = true; active.value = -1 }
  else if (e.key === 'Enter') {
    e.preventDefault()
    if (open.value && active.value >= 0 && list[active.value]) add(list[active.value].address, el)
    else commit(el.value, el)
  } else if (e.key === ';' || e.key === ',') { e.preventDefault(); commit(el.value, el) }
}
</script>

<template>
  <div class="ri">
    <TagsInputRoot class="ri-box" :class="{ bad: invalid || rejected }" :model-value="modelValue" delimiter="" :add-on-blur="false" @update:model-value="(v) => emit('update:modelValue', v as string[])">
      <TagsInputItem v-for="a in modelValue" :key="a" :value="a" class="ri-chip">
        <TagsInputItemText class="ri-text">{{ dirName(a) ? `${dirName(a)} · ` : '' }}{{ a }}</TagsInputItemText>
        <TagsInputItemDelete class="ri-del" :aria-label="t('compose.removeRecipient', { to: a })"><BaseIcon name="x" :size="14" /></TagsInputItemDelete>
      </TagsInputItem>
      <TagsInputInput
        :id="id" class="ri-input" role="combobox" aria-autocomplete="list" :aria-expanded="open && suggestions.length > 0" :aria-controls="listId"
        :aria-activedescendant="active >= 0 ? `${id}-opt-${active}` : undefined" :aria-label="label" :aria-invalid="invalid || !!rejected" :aria-describedby="describedBy"
        autocomplete="off" @input="onInput" @keydown="onKey" @blur="(e: FocusEvent) => commit(field(e).value, field(e))"
      />
    </TagsInputRoot>
    <ul v-show="open && suggestions.length > 0" :id="listId" class="ri-list" role="listbox" :aria-label="t('compose.directory')">
      <li
        v-for="(e, i) in suggestions" :id="`${id}-opt-${i}`" :key="e.address" role="option" class="ri-item" :aria-selected="i === active" :class="{ on: i === active }"
        @mousedown.prevent="add(e.address)"
      >
        <strong>{{ e.name }}</strong><span class="muted">{{ e.address }}</span>
      </li>
    </ul>
    <p v-if="open && suggestions.length === 0" class="ri-empty" role="status">{{ t('compose.noMatch') }}</p>
    <p v-if="rejected" class="error-text" role="alert">{{ t('compose.badAddress', { text: rejected }) }}</p>
  </div>
</template>

<style>
.ri { position: relative; display: grid; gap: var(--space-1); }
.ri-box { display: flex; flex-wrap: wrap; gap: var(--space-1); align-items: center; min-height: var(--target); padding: var(--space-1) var(--space-2); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-sm); }
.ri-box:focus-within { outline: 3px solid var(--c-focus); outline-offset: 2px; }
.ri-box.bad { border-color: var(--c-danger); }
.ri-chip { display: inline-flex; align-items: center; gap: 2px; padding: 0 0 0 var(--space-3); border-radius: var(--radius-pill); background: var(--c-primary-soft); color: var(--c-on-primary-soft); min-height: 32px; }
.ri-chip[data-state='active'] { outline: 2px solid var(--c-focus); }
.ri-text { font-size: var(--fs-sm); font-weight: 600; }
.ri-del { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; min-height: 32px; border: 0; background: transparent; color: inherit; border-radius: var(--radius-pill); cursor: pointer; }
.ri-del:hover { background: color-mix(in srgb, currentColor 18%, transparent); }
.ri-input { flex: 1; min-width: 160px; min-height: 36px; border: 0; background: transparent; outline: none; padding: 0 var(--space-1); }
.ri-list { position: absolute; z-index: 40; top: calc(100% + 4px); left: 0; min-width: 280px; max-height: 280px; overflow: auto; margin: 0; padding: var(--space-1); list-style: none; background: var(--c-surface); color: var(--c-text); border: 1px solid var(--c-border); border-radius: var(--radius-md); box-shadow: var(--shadow-2); }
.ri-item { display: grid; padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); cursor: pointer; min-height: var(--target); align-content: center; }
.ri-item.on, .ri-item:hover { background: var(--c-primary-soft); color: var(--c-on-primary-soft); }
.ri-item .muted { font-size: var(--fs-sm); }
.ri-empty { font-size: var(--fs-sm); color: var(--c-text-muted); }
</style>
