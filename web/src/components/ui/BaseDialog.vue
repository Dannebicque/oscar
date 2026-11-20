<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from './BaseButton.vue'

/** <dialog> natif : piège de focus, Échap et inertie du reste de la page gérés par le navigateur. */
const props = defineProps<{ open: boolean; title: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const el = ref<HTMLDialogElement | null>(null)
const titleId = `dlg-${Math.random().toString(36).slice(2, 8)}`
let opener: HTMLElement | null = null

watch(() => props.open, async (open) => {
  await nextTick()
  const d = el.value
  if (!d) return
  if (open && !d.open) {
    opener = document.activeElement as HTMLElement | null
    d.showModal()
  } else if (!open && d.open) {
    d.close()
  }
})

/** Rend le focus à l'élément qui a ouvert la fenêtre. */
function onClose() {
  opener?.focus?.()
  emit('close')
}
</script>

<template>
  <dialog ref="el" class="dialog" :aria-labelledby="titleId" @close="onClose" @click.self="emit('close')">
    <div v-if="open" class="panel">
      <header>
        <h2 :id="titleId">{{ title }}</h2>
        <BaseButton variant="ghost" icon="x" icon-only :label="t('ui.dialogClose')" @click="emit('close')" />
      </header>
      <div class="body"><slot /></div>
      <footer v-if="$slots.footer"><slot name="footer" /></footer>
    </div>
  </dialog>
</template>

<style scoped>
.dialog { border: 1px solid var(--c-border); border-radius: var(--radius-lg); padding: 0; width: min(640px, calc(100vw - 32px)); background: var(--c-surface); color: var(--c-text); box-shadow: var(--shadow-2); }
.dialog::backdrop { background: var(--c-overlay); }
.panel { display: grid; gap: var(--space-4); padding: var(--space-5); }
header { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }
footer { display: flex; flex-wrap: wrap; gap: var(--space-2); justify-content: flex-end; }
</style>
