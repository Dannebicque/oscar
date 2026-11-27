<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import type { Mail } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SuggestBadge from '../ui/SuggestBadge.vue'

/**
 * Choix des créneaux pour une demande de disponibilités (F-74). L'IA ne lit que libre/occupé ; l'utilisateur coche, Oscar prépare
 * le brouillon dans l'écran de rédaction, où l'utilisateur le relit : rien ne part sans son clic.
 */
const props = defineProps<{ open: boolean; mail: Mail }>()
const emit = defineEmits<{ close: []; pick: [slots: string[]] }>()
const { t } = useI18n()
const fmt = useFormat()

const slots = computed(() => props.mail.hints.availability?.slots ?? [])
const chosen = ref<string[]>([])
const label = (s: { start: string; end: string }) => `${fmt.dateLong(s.start)}, ${fmt.time(s.start)} – ${fmt.time(s.end)}`

watch(() => props.open, (o) => { if (o) chosen.value = slots.value.slice(0, 2).map((s) => s.start) })
</script>

<template>
  <BaseDialog :open="open" :title="t('mail.ai.availAction')" @close="emit('close')">
    <p class="top"><SuggestBadge /><span class="muted">{{ t('agenda.freeBusy') }}</span></p>
    <p class="muted">{{ t('mail.ai.availPickHelp') }}</p>
    <fieldset>
      <legend>{{ t('mail.ai.availTitle') }}</legend>
      <label v-for="s in slots" :key="s.start" class="check"><input v-model="chosen" type="checkbox" :value="s.start" /> {{ label(s) }}</label>
    </fieldset>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
      <BaseButton variant="primary" icon="sparkles" :disabled="chosen.length === 0" :label="t('mail.ai.availPrepare')" @click="emit('pick', chosen)" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.top { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
fieldset { border: 0; padding: 0; margin: 0; display: grid; gap: 2px; }
legend { font-weight: 700; margin-bottom: var(--space-2); }
.check { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); }
</style>
