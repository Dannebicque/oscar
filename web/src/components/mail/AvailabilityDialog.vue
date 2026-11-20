<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import { useFormat } from '../../composables/useFormat'
import type { Mail } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import AiBadge from '../ui/AiBadge.vue'

/**
 * Réponse assistée à une demande de disponibilités (F-74). L'IA ne lit que libre/occupé ;
 * l'utilisateur choisit les créneaux, relit le brouillon ; poser en provisoire est décoché par défaut ; rien ne part sans clic.
 */
const props = defineProps<{ open: boolean; mail: Mail }>()
const emit = defineEmits<{ close: []; send: [slots: string[], tentative: boolean] }>()
const { t } = useI18n()
const fmt = useFormat()

const slots = computed(() => props.mail.hints.availability?.slots ?? [])
const chosen = ref<string[]>([])
const tentative = ref(false)
const body = ref('')
const label = (s: { start: string; end: string }) => `${fmt.dateLong(s.start)}, ${fmt.time(s.start)} – ${fmt.time(s.end)}`

watch(() => props.open, (o) => { if (o) { chosen.value = []; tentative.value = false } })
watch([chosen, () => props.open], () => {
  const lines = slots.value.filter((s) => chosen.value.includes(s.start)).map((s) => `- ${label(s)}`).join('\n')
  body.value = `Bonjour ${props.mail.from.name.split(' ')[0]},\n\nVoici mes disponibilités :\n${lines}\n\nDis-moi ce qui te convient.\n${session.name}`
}, { deep: true, immediate: true })
</script>

<template>
  <BaseDialog :open="open" :title="t('mail.ai.availAction')" @close="emit('close')">
    <p class="top"><AiBadge /><span class="muted">{{ t('agenda.freeBusy') }}</span></p>
    <fieldset>
      <legend>{{ t('mail.ai.availTitle') }}</legend>
      <label v-for="s in slots" :key="s.start" class="check"><input v-model="chosen" type="checkbox" :value="s.start" /> {{ label(s) }}</label>
    </fieldset>
    <label class="check"><input v-model="tentative" type="checkbox" /> {{ t('mail.ai.availTentative') }}</label>
    <div class="field">
      <label for="avail-body">{{ t('suivis.relanceMessage') }}</label>
      <textarea id="avail-body" v-model="body" class="input" rows="7" />
    </div>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
      <BaseButton variant="primary" icon="send" :disabled="chosen.length === 0" :label="t('common.send')" @click="emit('send', chosen, tentative)" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.top { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
fieldset { border: 0; padding: 0; margin: 0; display: grid; gap: 2px; }
legend { font-weight: 700; margin-bottom: var(--space-2); }
.check { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); }
</style>
