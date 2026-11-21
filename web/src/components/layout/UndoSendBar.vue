<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useOutgoingStore } from '../../stores/outgoing'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Annuler l'envoi (F-73) : quelques secondes de rattrapage après le clic sur « Envoyer ». Annuler rouvre le message dans l'éditeur. */
const { t } = useI18n()
const router = useRouter()
const out = useOutgoingStore()

function cancel() {
  out.cancel()
  void router.push({ name: 'compose', query: { restore: '1' } })
}
// Fermer l'onglet pendant l'attente ferait perdre le message : le navigateur prévient.
const guard = (e: BeforeUnloadEvent) => { if (out.pending) { e.preventDefault(); e.returnValue = '' } }
onMounted(() => window.addEventListener('beforeunload', guard))
onBeforeUnmount(() => window.removeEventListener('beforeunload', guard))
</script>

<template>
  <div v-if="out.pending" class="undo" role="status">
    <BaseIcon name="send" :size="18" />
    <span class="msg"><span class="sr-only">{{ t('undoSend.pending') }} </span><span aria-hidden="true">{{ t('undoSend.in', { n: out.remaining }) }}</span></span>
    <BaseButton variant="primary" :label="t('undoSend.cancel')" @click="cancel" />
    <BaseButton :label="t('undoSend.now')" @click="out.sendNow()" />
  </div>
</template>

<style scoped>
.undo { position: fixed; z-index: 30; left: 50%; transform: translateX(-50%); bottom: calc(var(--target) + var(--space-4)); display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; max-width: calc(100vw - 2 * var(--space-4)); padding: var(--space-2) var(--space-4); background: var(--c-surface); color: var(--c-text); border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-lg); box-shadow: var(--shadow-2, var(--shadow-1)); font-weight: 600; }
.msg { min-width: 120px; }
@media (max-width: 720px) { .undo { bottom: calc(var(--target) * 2 + var(--space-4)); } }
</style>
