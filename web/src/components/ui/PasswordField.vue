<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseIcon from './BaseIcon.vue'
import FormField from './FormField.vue'

/** Champ de mot de passe avec bouton « afficher » (aria-pressed) : utile au clavier adapté et à la saisie sur mobile. */
defineProps<{ modelValue: string; label: string; hint?: string; error?: string; required?: boolean; autocomplete?: 'current-password' | 'new-password' | 'off'; id?: string }>()
defineEmits<{ 'update:modelValue': [v: string] }>()
const { t } = useI18n()
const shown = ref(false)
</script>

<template>
  <FormField v-slot="f" :label="label" :hint="hint" :error="error" :required="required" :id="id">
    <span class="wrap">
      <input
        :id="f.id" class="input" :type="shown ? 'text' : 'password'" :value="modelValue" :required="required" :autocomplete="autocomplete ?? 'current-password'"
        :aria-describedby="f.describedBy" :aria-invalid="f.invalid || undefined" autocapitalize="off" spellcheck="false" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button type="button" class="eye" :aria-pressed="shown" :aria-label="shown ? t('auth.hidePassword') : t('auth.showPassword')" @click="shown = !shown"><BaseIcon :name="shown ? 'eyeOff' : 'eye'" :size="18" /></button>
    </span>
  </FormField>
</template>

<style scoped>
.wrap { position: relative; display: block; }
.wrap .input { width: 100%; padding-inline-end: calc(var(--target) + var(--space-1)); }
.eye { position: absolute; inset-block: 0; inset-inline-end: 0; width: var(--target); border: 0; background: transparent; color: var(--c-text-muted); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border-radius: var(--radius-sm); }
.eye:hover { color: var(--c-text); }
</style>
