<script setup lang="ts">
import { computed } from 'vue'

/**
 * Champ de formulaire : étiquette, aide, erreur et liaison ARIA (aria-describedby, aria-invalid) à UN seul endroit.
 * Toutes les saisies de l'application passent par lui (ou par TextField, SelectField, TextareaField qui l'utilisent).
 */
const props = defineProps<{ label: string; id?: string; hint?: string; error?: string; required?: boolean }>()
const uid = `ff-${Math.random().toString(36).slice(2, 8)}`
const fid = computed(() => props.id ?? uid)
const describedBy = computed(() => [props.hint ? `${fid.value}-hint` : '', props.error ? `${fid.value}-err` : ''].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div class="field">
    <label :for="fid">{{ label }}<span v-if="required" class="req" aria-hidden="true"> *</span></label>
    <slot :id="fid" :described-by="describedBy" :invalid="!!error" />
    <p v-if="hint" :id="`${fid}-hint`" class="hint">{{ hint }}</p>
    <p v-if="error" :id="`${fid}-err`" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.req { color: var(--c-danger); }
</style>
