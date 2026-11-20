<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui'

/** Interrupteur sur Reka UI : role="switch", clavier (Espace) et état annoncés. */
defineProps<{ modelValue: boolean; label: string; help?: string }>()
defineEmits<{ 'update:modelValue': [v: boolean] }>()
const id = `sw-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <div class="sw">
    <SwitchRoot :id="id" class="sw-root" :model-value="modelValue" :aria-describedby="help ? `${id}-h` : undefined" @update:model-value="(v: boolean) => $emit('update:modelValue', v)">
      <SwitchThumb class="sw-thumb" />
    </SwitchRoot>
    <div>
      <label :for="id">{{ label }}</label>
      <p v-if="help" :id="`${id}-h`" class="hint">{{ help }}</p>
    </div>
  </div>
</template>

<style scoped>
.sw { display: flex; gap: var(--space-3); align-items: flex-start; min-height: var(--target); }
.sw-root { flex: none; width: 48px; height: 28px; margin-top: 8px; padding: 0; border-radius: 14px; background: var(--c-border); border: 2px solid var(--c-text-muted); position: relative; cursor: pointer; transition: background var(--dur) var(--ease); }
.sw-root[data-state='checked'] { background: var(--c-primary); border-color: var(--c-primary); }
.sw-thumb { display: block; width: 20px; height: 20px; border-radius: 50%; background: var(--c-surface); border: 2px solid var(--c-text-muted); transform: translateX(2px); transition: transform var(--dur) var(--ease); }
.sw-root[data-state='checked'] .sw-thumb { transform: translateX(22px); border-color: var(--c-primary); }
label { font-weight: 600; display: block; padding-top: 8px; cursor: pointer; }
</style>
