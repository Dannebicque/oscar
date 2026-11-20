<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUiStore } from '../../stores/ui'
import BaseButton from './BaseButton.vue'

const ui = useUiStore()
const { t } = useI18n()

async function undo(id: number) {
  const toast = ui.toasts.find((x) => x.id === id)
  ui.dismiss(id)
  await toast?.undo?.()
}
</script>

<template>
  <!-- Zone live unique, polie : annonce sans voler le focus (N-02). -->
  <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ ui.announcement }}</div>
  <section class="toasts" :aria-label="t('ui.toasts')"><ul>
    <li v-for="toast in ui.toasts" :key="toast.id" class="toast" :class="toast.tone">
      <span>{{ toast.message }}</span>
      <BaseButton v-if="toast.undo" variant="ghost" icon="undo" :label="t('common.undo')" @click="undo(toast.id)" />
      <BaseButton variant="ghost" icon="x" icon-only :label="t('common.close')" @click="ui.dismiss(toast.id)" />
    </li>
  </ul></section>
</template>

<style scoped>
.toasts { position: fixed; z-index: 50; left: 50%; transform: translateX(-50%); bottom: 72px; width: min(560px, calc(100vw - 32px)); pointer-events: none; }
.toasts ul { display: grid; gap: var(--space-2); }
.toast { pointer-events: auto; }
.toast { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4); background: var(--c-text); color: var(--c-bg); border-radius: var(--radius-md); box-shadow: var(--shadow-2); }
.toast > span { flex: 1; }
.toast.error { background: var(--c-danger); color: #fff; }
.toast :deep(.btn) { color: inherit; }
:root[data-theme='contrast'] .toast { border: 2px solid #fff; }
@media (max-width: 720px) { .toasts { bottom: 128px; } }
</style>
