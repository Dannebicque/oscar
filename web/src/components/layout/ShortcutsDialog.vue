<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'

const { t } = useI18n()
const ui = useUiStore()
const ROWS = [['c'], ['q'], ['/'], ['j'], ['k'], ['r'], ['e'], ['#'], ['s'], ['g', 'i'], ['g', 's'], ['g', 't'], ['g', 'a'], ['g', 'h'], ['?']] as const
const id = (keys: readonly string[]) => keys.join('')
</script>

<template>
  <BaseDialog :open="ui.shortcutsOpen" :title="t('shortcuts.title')" :description="t('shortcuts.help')" @close="ui.shortcutsOpen = false">
    <dl class="sc">
      <template v-for="keys in ROWS" :key="id(keys)">
        <dt><template v-for="(k, i) in keys" :key="k"><kbd class="kbd">{{ k }}</kbd><span v-if="i < keys.length - 1" class="muted"> {{ t('shortcuts.then') }} </span></template></dt>
        <dd>{{ t(`shortcuts.keys.${id(keys)}`) }}</dd>
      </template>
    </dl>
    <p class="hint">{{ t('shortcuts.off') }}</p>
    <template #footer><BaseButton :label="t('common.close')" @click="ui.shortcutsOpen = false" /></template>
  </BaseDialog>
</template>

<style scoped>
.sc { display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-5); margin: 0; align-items: center; }
.sc dd { margin: 0; }
</style>
