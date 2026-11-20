<script setup lang="ts" generic="T extends string | number">
import BaseIcon from './BaseIcon.vue'
/** Groupe de boutons radio natifs présentés en cartes. */
defineProps<{ modelValue: T; legend: string; options: { value: T; label: string; help?: string; icon?: string }[]; name: string }>()
defineEmits<{ 'update:modelValue': [v: T] }>()
</script>

<template>
  <fieldset class="group">
    <legend>{{ legend }}</legend>
    <div class="opts">
      <label v-for="o in options" :key="String(o.value)" class="opt" :class="{ on: o.value === modelValue }">
        <input type="radio" :name="name" :value="o.value" :checked="o.value === modelValue" @change="$emit('update:modelValue', o.value)" />
        <BaseIcon v-if="o.icon" :name="o.icon" :size="18" />
        <span><strong>{{ o.label }}</strong><small v-if="o.help" class="muted">{{ o.help }}</small></span>
      </label>
    </div>
  </fieldset>
</template>

<style scoped>
.group { border: 0; padding: 0; margin: 0; }
legend { font-weight: 700; margin-bottom: var(--space-2); padding: 0; }
.opts { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.opt { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); padding: var(--space-2) var(--space-4); border: 1px solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface); cursor: pointer; }
.opt span { display: grid; }
small { font-size: var(--fs-sm); }
.opt.on { border-color: var(--c-primary); background: var(--c-primary-soft); color: var(--c-on-primary-soft); box-shadow: inset 0 0 0 1px var(--c-primary); }
.opt:has(input:focus-visible) { outline: 3px solid var(--c-focus); outline-offset: 2px; }
input { accent-color: var(--c-primary); width: 18px; height: 18px; }
</style>
