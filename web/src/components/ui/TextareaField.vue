<script setup lang="ts">
import FormField from './FormField.vue'

defineProps<{ modelValue: string; label: string; hint?: string; error?: string; required?: boolean; rows?: number; id?: string; placeholder?: string }>()
defineEmits<{ 'update:modelValue': [v: string] }>()
</script>

<template>
  <FormField v-slot="f" :label="label" :hint="hint" :error="error" :required="required" :id="id">
    <textarea
      :id="f.id" class="input" :value="modelValue" :rows="rows ?? 5" :required="required" :placeholder="placeholder"
      :aria-describedby="f.describedBy" :aria-invalid="f.invalid || undefined" @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
  </FormField>
</template>
