<script setup lang="ts">
/** Interrupteur : une vraie case à cocher native avec role="switch" (clavier et lecteur d'écran gratuits). */
defineProps<{ modelValue: boolean; label: string; help?: string }>()
defineEmits<{ 'update:modelValue': [v: boolean] }>()
const id = `sw-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <div class="sw">
    <input :id="id" type="checkbox" role="switch" :checked="modelValue" :aria-describedby="help ? `${id}-h` : undefined" @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)" />
    <div>
      <label :for="id">{{ label }}</label>
      <p v-if="help" :id="`${id}-h`" class="hint">{{ help }}</p>
    </div>
  </div>
</template>

<style scoped>
.sw { display: flex; gap: var(--space-3); align-items: flex-start; min-height: var(--target); }
input { appearance: none; flex: none; width: 48px; height: 28px; margin-top: 8px; border-radius: 14px; background: var(--c-border); position: relative; cursor: pointer; border: 2px solid var(--c-text-muted); transition: background var(--dur) var(--ease); }
input::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: var(--c-surface); border: 2px solid var(--c-text-muted); transition: transform var(--dur) var(--ease); }
input:checked { background: var(--c-primary); border-color: var(--c-primary); }
input:checked::after { transform: translateX(20px); border-color: var(--c-primary); }
label { font-weight: 600; display: block; padding-top: 8px; }
</style>
