<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useFormat } from '../../composables/useFormat'
import { useMailsStore } from '../../stores/mails'
import { useOutgoingStore } from '../../stores/outgoing'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Mails programmés (F-73) : l'heure d'envoi est celle que l'utilisateur a choisie ; il peut annuler ou modifier tant qu'ils ne sont pas partis. */
const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const mails = useMailsStore()
const outgoing = useOutgoingStore()
const ui = useUiStore()
onMounted(() => mails.loadScheduled())

/** Annuler rend le message ; « Modifier » le rouvre dans l'éditeur, « Annuler l'envoi » le garde en brouillon local. */
async function reopen(id: string, edit: boolean) {
  try {
    outgoing.restore = await mails.cancelScheduled(id)
    if (edit) await router.push({ name: 'compose', query: { restore: '1' } })
    else ui.notify(t('schedule.cancelled'), { tone: 'info' })
  } catch (e) {
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  }
}
</script>

<template>
  <section :aria-label="t('schedule.list')" class="sl">
    <p v-if="mails.scheduled.length === 0" class="muted empty">{{ t('schedule.empty') }}</p>
    <ul v-else class="items">
      <li v-for="s in mails.scheduled" :key="s.id" class="it">
        <strong class="subj">{{ s.subject }}</strong>
        <span class="muted">{{ s.to.join(', ') }}</span>
        <span class="when"><ToneChip v-if="s.status === 'failed'" tone="danger" icon="alert" :label="t('schedule.failed')" /><ToneChip v-else-if="s.status === 'sending'" tone="info" icon="send" :label="t('schedule.sending')" /><ToneChip v-else tone="neutral" icon="clock" :label="t('schedule.at', { when: fmt.dateTime(s.sendAt) })" /></span>
        <span class="acts">
          <BaseButton icon="edit" :label="t('schedule.edit')" :disabled="s.status === 'sending'" @click="reopen(s.id, true)" />
          <BaseButton variant="ghost" icon="x" :label="t('schedule.cancel')" :disabled="s.status === 'sending'" @click="reopen(s.id, false)" />
        </span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.sl { padding: var(--space-3); overflow-y: auto; min-height: 0; background: var(--c-surface); border-right: var(--bw) solid var(--c-border); }
.items { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.it { display: grid; gap: var(--space-1); padding: var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface-2); }
.subj { overflow-wrap: anywhere; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; margin-top: var(--space-1); }
.empty { padding: var(--space-3); }
</style>
