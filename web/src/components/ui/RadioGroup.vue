<script setup lang="ts" generic="T extends string | number">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui'
import BaseIcon from './BaseIcon.vue'

/** Groupe de choix exclusifs sur Reka UI : flèches pour passer d'un choix à l'autre, un seul arrêt de tabulation. */
defineProps<{ modelValue: T; legend: string; options: { value: T; label: string; help?: string; icon?: string }[]; name: string }>()
defineEmits<{ 'update:modelValue': [v: T] }>()
const uid = `rg-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <div class="group">
    <p :id="uid" class="legend">{{ legend }}</p>
    <RadioGroupRoot class="opts" :model-value="String(modelValue)" :name="name" :aria-labelledby="uid" @update:model-value="(v: unknown) => $emit('update:modelValue', (typeof options[0].value === 'number' ? Number(v) : String(v)) as T)">
      <label v-for="o in options" :key="String(o.value)" class="opt" :class="{ on: o.value === modelValue }">
        <RadioGroupItem class="rg-item" :value="String(o.value)" :aria-label="o.label" />
        <BaseIcon v-if="o.icon" :name="o.icon" :size="18" />
        <span><strong>{{ o.label }}</strong><small v-if="o.help" class="muted">{{ o.help }}</small></span>
      </label>
    </RadioGroupRoot>
  </div>
</template>

<style scoped>
.legend { font-weight: 700; margin-bottom: var(--space-2); }
.opts { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.opt { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); padding: var(--space-2) var(--space-4); border: 1px solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface); cursor: pointer; }
.opt span { display: grid; }
small { font-size: var(--fs-sm); }
.opt.on { border-color: var(--c-primary); background: var(--c-primary-soft); color: var(--c-on-primary-soft); box-shadow: inset 0 0 0 1px var(--c-primary); }
.opt:has(.rg-item:focus-visible) { outline: 3px solid var(--c-focus); outline-offset: 2px; }
.rg-item { flex: none; width: 20px; height: 20px; padding: 0; border-radius: 50%; border: 2px solid var(--c-text-muted); background: var(--c-surface); position: relative; }
.rg-item[data-state='checked'] { border-color: var(--c-primary); }
.rg-item[data-state='checked']::after { content: ''; position: absolute; inset: 3px; border-radius: 50%; background: var(--c-primary); }
.rg-item:focus-visible { outline: none; }
</style>
