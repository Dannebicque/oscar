<script setup lang="ts" generic="T extends string | number">
import FormField from './FormField.vue'

/** Liste déroulante native (la plus fiable au clavier, au tactile et au lecteur d'écran), habillée comme les autres champs. */
const props = defineProps<{ modelValue: T; label: string; options: { value: T; label: string }[]; hint?: string; error?: string; required?: boolean; id?: string }>()
const emit = defineEmits<{ 'update:modelValue': [v: T] }>()
const pick = (e: Event) => {
  const raw = (e.target as HTMLSelectElement).value
  emit('update:modelValue', (typeof props.options[0]?.value === 'number' ? Number(raw) : raw) as T)
}
</script>

<template>
  <FormField v-slot="f" :label="label" :hint="hint" :error="error" :required="required" :id="id">
    <select :id="f.id" class="input" :value="modelValue" :required="required" :aria-describedby="f.describedBy" :aria-invalid="f.invalid || undefined" @change="pick">
      <option v-for="o in options" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
    </select>
  </FormField>
</template>
