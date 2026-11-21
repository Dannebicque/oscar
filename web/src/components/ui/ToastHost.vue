<script setup lang="ts">
import { ToastAction, ToastClose, ToastDescription, ToastProvider, ToastRoot, ToastViewport } from 'reka-ui'
import { useI18n } from 'vue-i18n'
import { useUiStore } from '../../stores/ui'
import BaseIcon from './BaseIcon.vue'

/**
 * Notifications sur Reka UI : annoncées aux lecteurs d'écran sans voler le focus (N-02), pause au survol et au focus,
 * balayage pour fermer, F8 pour y aller au clavier. Les erreurs restent jusqu'à fermeture.
 */
const ui = useUiStore()
const { t } = useI18n()

/** On garde la notification en main : Reka la ferme au clic sur l'action, avant ou après ce gestionnaire. */
async function undo(toast: (typeof ui.toasts)[number]) {
  ui.dismiss(toast.id)
  await toast.undo?.()
}
</script>

<template>
  <!-- Annonce des changements de page (titre), sans voler le focus (N-02). Les notifications s'annoncent elles-mêmes. -->
  <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ ui.announcement }}</div>
  <ToastProvider :duration="8000" swipe-direction="right" :label="t('ui.toasts')">
    <ToastRoot
      v-for="toast in ui.toasts" :key="toast.id" class="toast" :class="toast.tone" :type="toast.tone === 'error' ? 'foreground' : 'background'"
      :duration="toast.tone === 'error' ? 2_147_483_647 : 8000" :open="true" @update:open="(v: boolean) => { if (!v) ui.dismiss(toast.id) }"
    >
      <ToastDescription class="toast-text">{{ toast.message }}</ToastDescription>
      <ToastAction v-if="toast.undo" as-child :alt-text="t('common.undo')">
        <button type="button" class="toast-btn" @click="undo(toast)"><BaseIcon name="undo" :size="16" />{{ t('common.undo') }}</button>
      </ToastAction>
      <ToastClose class="toast-btn toast-x" :aria-label="t('common.close')"><BaseIcon name="x" :size="16" /></ToastClose>
    </ToastRoot>
    <ToastViewport class="toasts" />
  </ToastProvider>
</template>

<style>
.toasts { position: fixed; z-index: 70; left: 50%; transform: translateX(-50%); bottom: 72px; display: grid; gap: var(--space-2); width: min(560px, calc(100vw - 32px)); margin: 0; padding: 0; list-style: none; outline: none; }
.toast { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4); background: var(--c-text); color: var(--c-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-2); }
.toast-text { flex: 1; margin: 0; }
.toast.error { background: var(--c-danger-solid); color: var(--c-on-danger-solid); }
.toast-btn { display: inline-flex; align-items: center; gap: 6px; min-height: var(--target); min-width: var(--target); justify-content: center; padding: 0 var(--space-3); border: 0; border-radius: var(--radius-sm); background: transparent; color: inherit; font-weight: 600; }
.toast-btn:hover { background: color-mix(in srgb, currentColor 15%, transparent); }
.toast[data-state='closed'] { display: none; }
:root[data-theme='contrast'] .toast { border: 2px solid var(--c-text); }
@media (max-width: 720px) { .toasts { bottom: 128px; } }
</style>
