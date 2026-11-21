<script setup lang="ts">
import FormField from './FormField.vue'

/** Champ texte (aussi date, heure, e-mail, nombre, mot de passe) : un seul rendu pour toute l'application. */
defineProps<{
  modelValue: string | number
  label: string
  type?: 'text' | 'email' | 'date' | 'time' | 'number' | 'password' | 'url' | 'search'
  hint?: string
  error?: string
  required?: boolean
  placeholder?: string
  autocomplete?: string
  id?: string
  min?: number | string
  max?: number | string
  list?: string
}>()
defineEmits<{ 'update:modelValue': [v: string] }>()
</script>

<template>
  <FormField v-slot="f" :label="label" :hint="hint" :error="error" :required="required" :id="id">
    <input
      :id="f.id" class="input" :type="type ?? 'text'" :value="modelValue" :required="required" :placeholder="placeholder" :autocomplete="autocomplete ?? 'off'"
      :min="min" :max="max" :list="list" :aria-describedby="f.describedBy" :aria-invalid="f.invalid || undefined" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
  </FormField>
</template>
