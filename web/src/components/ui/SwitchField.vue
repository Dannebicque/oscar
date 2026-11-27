<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui'

/** Interrupteur sur Reka UI : role="switch", clavier (Espace) et état annoncés. */
defineProps<{ modelValue: boolean; label: string; help?: string }>()
defineEmits<{ 'update:modelValue': [v: boolean] }>()
const id = `sw-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <div class="switch-field">
    <SwitchRoot :id="id" class="sw-root" :model-value="modelValue" :aria-describedby="help ? `${id}-h` : undefined" @update:model-value="(v: boolean) => $emit('update:modelValue', v)">
      <SwitchThumb class="sw-thumb" />
    </SwitchRoot>
    <div class="switch-text">
      <label :for="id">{{ label }}</label>
      <p v-if="help" :id="`${id}-h`" class="hint">{{ help }}</p>
    </div>
  </div>
</template>

<style scoped>
.switch-field { display: flex; gap: var(--space-3); align-items: flex-start; min-height: var(--target); min-width: 0; box-sizing: border-box; }
.switch-text { flex: 1 1 0; min-width: 0; }
.switch-text .hint { overflow-wrap: anywhere; }
.sw-root { flex: none; box-sizing: border-box; width: 48px; height: 28px; margin-top: 8px; padding: 0; border-radius: 14px; box-shadow: none; transform: none; background: var(--c-border); border: 2px solid var(--c-text-muted); position: relative; cursor: pointer; transition: background var(--dur) var(--ease); }
/* La zone touchable dépasse le dessin : 44 px au moins (N-01). */
.sw-root::after { content: ''; position: absolute; inset: -10px -2px; }
.sw-root[data-state='checked'] { background: var(--c-primary); border-color: var(--c-primary); }
.sw-thumb { display: block; box-sizing: border-box; width: 20px; height: 20px; border-radius: 50%; box-shadow: none; background: var(--c-surface); border: 2px solid var(--c-text-muted); transform: translateX(2px); transition: transform var(--dur) var(--ease); }
.sw-root[data-state='checked'] .sw-thumb { transform: translateX(22px); border-color: var(--c-primary); }
label { font-weight: 600; display: block; padding-top: 8px; cursor: pointer; }
</style>
