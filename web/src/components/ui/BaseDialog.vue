<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { useI18n } from 'vue-i18n'
import BaseIcon from './BaseIcon.vue'

/**
 * Fenêtre modale sur Reka UI (composants accessibles sans style, maintenus) :
 * piège de focus, Échap, retour du focus à l'élément d'origine, rôle et nom accessibles.
 */
defineProps<{ open: boolean; title: string; description?: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
</script>

<template>
  <DialogRoot :open="open" @update:open="(v: boolean) => { if (!v) emit('close') }">
    <DialogPortal>
      <DialogOverlay class="dlg-overlay" />
      <DialogContent class="dlg" :aria-describedby="description ? undefined : undefined">
        <header class="dlg-head">
          <DialogTitle class="dlg-title">{{ title }}</DialogTitle>
          <DialogClose class="dlg-x" :aria-label="t('ui.dialogClose')"><BaseIcon name="x" :size="18" /></DialogClose>
        </header>
        <DialogDescription v-if="description" class="muted">{{ description }}</DialogDescription>
        <div class="dlg-body"><slot /></div>
        <footer v-if="$slots.footer" class="dlg-foot"><slot name="footer" /></footer>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<style>
.dlg-overlay { position: fixed; inset: 0; z-index: 60; background: var(--c-overlay); }
.dlg {
  position: fixed; z-index: 61; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(640px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto;
  display: grid; gap: var(--space-4); padding: var(--space-5); background: var(--c-surface); color: var(--c-text);
  border: 1px solid var(--c-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-2);
}
.dlg:focus { outline: none; }
.dlg-head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }
.dlg-title { font-size: var(--fs-lg); line-height: 1.3; margin: 0; }
.dlg-x { min-width: var(--target); min-height: var(--target); display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: var(--radius-sm); background: transparent; color: inherit; }
.dlg-x:hover { background: var(--c-surface-2); }
.dlg-foot { display: flex; flex-wrap: wrap; gap: var(--space-2); justify-content: flex-end; }
</style>
