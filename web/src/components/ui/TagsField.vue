<script setup lang="ts">
import { TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText, TagsInputRoot } from 'reka-ui'
import { useI18n } from 'vue-i18n'
import BaseIcon from './BaseIcon.vue'
import FormField from './FormField.vue'

/** Étiquettes : pastilles supprimables (Reka UI TagsInput). Entrée ou virgule valident, retour arrière retire. */
defineProps<{ modelValue: string[]; label: string; hint?: string; id?: string }>()
defineEmits<{ 'update:modelValue': [v: string[]] }>()
const { t } = useI18n()
</script>

<template>
  <FormField v-slot="f" :label="label" :hint="hint" :id="id">
    <TagsInputRoot class="tags" :model-value="modelValue" delimiter="," :add-on-blur="true" @update:model-value="(v) => $emit('update:modelValue', (v as string[]).map((x) => x.trim().replace(/^#/, '')).filter(Boolean))">
      <TagsInputItem v-for="tag in modelValue" :key="tag" :value="tag" class="tag">
        <TagsInputItemText>#{{ tag }}</TagsInputItemText>
        <TagsInputItemDelete class="tag-del" :aria-label="t('tasks.removeTag', { tag })"><BaseIcon name="x" :size="14" /></TagsInputItemDelete>
      </TagsInputItem>
      <TagsInputInput :id="f.id" class="tag-input" :aria-describedby="f.describedBy" autocomplete="off" />
    </TagsInputRoot>
  </FormField>
</template>

<style>
.tags { display: flex; flex-wrap: wrap; gap: var(--space-1); align-items: center; min-height: var(--target); padding: var(--space-1) var(--space-2); background: var(--c-surface); border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-sm); }
.tags:focus-within { outline: 3px solid var(--c-focus); outline-offset: 1px; }
.tag { display: inline-flex; align-items: center; padding: 0 0 0 var(--space-3); border-radius: var(--radius-pill); background: var(--c-primary-soft); color: var(--c-on-primary-soft); min-height: 32px; font-size: var(--fs-sm); font-weight: 600; }
.tag[data-state='active'] { outline: 2px solid var(--c-focus); }
.tag-del { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; min-height: 32px; border: 0; background: transparent; color: inherit; border-radius: var(--radius-pill); cursor: pointer; }
.tag-input { flex: 1; min-width: 120px; min-height: 36px; border: 0; background: transparent; outline: none; padding: 0 var(--space-1); }
</style>
