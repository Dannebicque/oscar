<script setup lang="ts">
import BaseIcon from './BaseIcon.vue'

withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'ai'
  icon?: string
  /** Bouton à icône seule : `label` devient son nom accessible. */
  iconOnly?: boolean
  label?: string
  type?: 'button' | 'submit'
  pressed?: boolean
  expanded?: boolean
  disabled?: boolean
}>(), { variant: 'secondary', type: 'button' })
</script>

<template>
  <!-- L'aspect vient de la classe partagée .btn (styles/components.css). -->
  <button
    :type="type" class="btn" :class="[variant === 'secondary' ? '' : variant, { icon: iconOnly }]" :disabled="disabled"
    :aria-label="iconOnly ? label : undefined" :aria-pressed="pressed" :aria-expanded="expanded"
  >
    <BaseIcon v-if="icon" :name="icon" :size="18" />
    <span v-if="!iconOnly"><slot>{{ label }}</slot></span>
  </button>
</template>
