<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import { useFormat } from '../../composables/useFormat'
import type { InvitationResponse, MailInvitation } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import TextareaField from '../ui/TextareaField.vue'

/**
 * Réponse à une invitation (F-25), relue avant de partir : le message est préparé et modifiable, les chevauchements sont dits.
 * Rien ne part sans le clic sur « Envoyer la réponse » ; « Ajouter sans répondre » ne prévient personne.
 */
const props = defineProps<{ response: InvitationResponse | null; invitation: MailInvitation; conflicts: string[]; canAdd: boolean; added?: boolean; busy?: boolean }>()
const emit = defineEmits<{ close: []; send: [message: string]; addOnly: [] }>()
const { t } = useI18n()
const fmt = useFormat()
const message = ref('')

watch(() => props.response, (r) => {
  if (!r) return
  const i = props.invitation
  message.value = t(`mail.invitation.draft.${r}`, {
    who: i.organizerName.split(' ')[0], title: i.title, date: fmt.dateLong(i.start), start: fmt.time(i.start), end: fmt.time(i.end), me: session.name,
  })
}, { immediate: true })
</script>

<template>
  <BaseDialog :open="response !== null" :title="response ? t(`mail.invitation.replyTitle.${response}`) : ''" @close="emit('close')">
    <p class="muted">{{ t('mail.invitation.to', { who: invitation.organizerName, address: invitation.organizerAddress }) }}</p>
    <ul v-if="conflicts.length" class="callout" data-tone="warn" role="status">
      <li v-for="c in conflicts" :key="c"><BaseIcon name="alert" :size="16" /> {{ c }}</li>
    </ul>
    <p v-else-if="response !== 'declined'" class="free"><BaseIcon name="checkCircle" :size="16" /> {{ t('mail.invitation.noConflict') }}</p>
    <TextareaField id="inv-message" v-model="message" :label="t('mail.invitation.message')" :rows="7" />
    <p class="hint">{{ t(added ? (response === 'declined' ? 'mail.invitation.stays' : 'mail.invitation.alreadyAdded') : response !== 'declined' && canAdd ? 'mail.invitation.willAdd' : 'mail.invitation.willNotAdd') }}</p>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
      <BaseButton v-if="response !== 'declined' && canAdd && !added" :disabled="busy" :label="t('mail.invitation.addOnly')" @click="emit('addOnly')" />
      <BaseButton variant="primary" icon="send" :disabled="busy || !message.trim()" :label="t('mail.invitation.send')" @click="emit('send', message.trim())" />
    </template>
  </BaseDialog>
</template>

<style scoped>
ul { list-style: none; margin: 0; display: grid; gap: var(--space-1); }
li, .free { display: flex; gap: var(--space-2); align-items: center; }
</style>
