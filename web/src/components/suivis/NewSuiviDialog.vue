<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { clock } from '../../domain/clock'
import { addDays, isoDay } from '../../domain/dates'
import type { Direction, NewSuivi } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'

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
      <div class="field">
        <label for="ns-subject">{{ t('suivis.newSubject') }}</label>
        <input id="ns-subject" v-model="subject" class="input" required :aria-invalid="submitted && !subject.trim()" aria-describedby="ns-err" autocomplete="off" />
      </div>
      <div class="field">
        <label for="ns-contact">{{ t('suivis.newContact') }}</label>
        <input id="ns-contact" v-model="contact" class="input" required :aria-invalid="submitted && !contact.trim()" aria-describedby="ns-err" autocomplete="off" />
      </div>
      <p id="ns-err" class="error-text" role="alert">{{ invalid ? t('suivis.fieldRequired') : '' }}</p>
      <div class="field">
        <label for="ns-due">{{ t('suivis.newDue') }}</label>
        <input id="ns-due" v-model="dueAt" type="date" class="input" />
      </div>
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
