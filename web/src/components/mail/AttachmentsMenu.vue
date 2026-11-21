<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui'
import { useI18n } from 'vue-i18n'
import type { Attachment } from '../../domain/types'
import BaseIcon from '../ui/BaseIcon.vue'

/** Pièces jointes d'un message : un menu déroulant (Reka UI : flèches, Échap, focus rendu), chaque entrée télécharge le fichier. Jamais d'aperçu. */
defineProps<{ items: Attachment[] }>()
const emit = defineEmits<{ download: [a: Attachment] }>()
const { t } = useI18n()
</script>

<template>
  <DropdownMenuRoot>
    <DropdownMenuTrigger class="btn att-trigger" :aria-label="t('mail.attachmentsMenu', { n: items.length }, items.length)">
      <BaseIcon name="paperclip" :size="16" />
      <span>{{ t('mail.attachmentsCount', { n: items.length }, items.length) }}</span>
      <BaseIcon name="chevronDown" :size="16" />
    </DropdownMenuTrigger>
    <DropdownMenuPortal>
      <DropdownMenuContent class="att-menu" align="start" :side-offset="6">
        <DropdownMenuItem v-for="a in items" :key="a.name + a.index" class="att-item" @select="emit('download', a)">
          <BaseIcon name="download" :size="16" />
          <span class="att-name">{{ a.name }}</span>
          <span class="att-size">{{ a.size }}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>

<style>
.att-menu { z-index: 60; min-width: 260px; max-width: min(420px, 92vw); padding: var(--space-1); background: var(--c-surface); border: var(--bw-card) solid var(--c-border-strong); border-radius: var(--radius-md); box-shadow: var(--shadow-2, var(--shadow-1)); }
.att-item { display: flex; align-items: center; gap: var(--space-2); min-height: var(--target); padding: 0 var(--space-3); border-radius: var(--radius-sm); color: var(--c-text); cursor: pointer; outline: none; }
.att-item[data-highlighted] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); }
.att-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.att-size { color: var(--c-text-muted); font-size: var(--fs-sm); white-space: nowrap; }
.att-trigger { gap: var(--space-2); justify-self: start; width: fit-content; }
</style>
