<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { clock } from '../../domain/clock'
import { addDays, isoDay } from '../../domain/dates'
import type { Direction, NewSuivi } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import TextField from '../ui/TextField.vue'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; create: [n: NewSuivi] }>()
const { t } = useI18n()

const subject = ref('')
const contact = ref('')
const dueAt = ref(isoDay(addDays(clock.now(), 2)))
const direction = ref<Direction>('waiting')
const submitted = ref(false)
const invalid = computed(() => submitted.value && (!subject.value.trim() || !contact.value.trim()))

watch(() => props.open, (o) => { if (o) { subject.value = ''; contact.value = ''; submitted.value = false } })

function submit() {
  submitted.value = true
  if (!subject.value.trim() || !contact.value.trim()) return
  emit('create', { subject: subject.value.trim(), contact: contact.value.trim(), dueAt: dueAt.value, direction: direction.value, relanceAt: direction.value === 'waiting' ? dueAt.value : null })
}
</script>

<template>
  <BaseDialog :open="open" :title="t('suivis.newTitle')" @close="emit('close')">
    <form id="new-suivi" novalidate @submit.prevent="submit">
      <TextField id="ns-subject" v-model="subject" :label="t('suivis.newSubject')" required :error="submitted && !subject.trim() ? t('suivis.fieldRequired') : ''" />
      <TextField id="ns-contact" v-model="contact" :label="t('suivis.newContact')" required :error="submitted && !contact.trim() ? t('suivis.fieldRequired') : ''" />
      <TextField id="ns-due" v-model="dueAt" type="date" :label="t('suivis.newDue')" />
      <fieldset class="field">
        <legend>{{ t('suivis.newDirection') }}</legend>
        <label class="radio"><input v-model="direction" type="radio" value="waiting" /> {{ t('direction.waiting') }}</label>
        <label class="radio"><input v-model="direction" type="radio" value="toHandle" /> {{ t('direction.toHandle') }}</label>
      </fieldset>
    </form>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
      <BaseButton variant="primary" type="submit" form="new-suivi" :label="t('suivis.create')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
form { display: grid; gap: var(--space-4); }
fieldset { border: 0; padding: 0; margin: 0; }
.radio { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); font-weight: 400; }
</style>
