<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { folderTree } from '../../domain/folders'
import type { MoveTarget } from '../../domain/types'
import { useMailsStore } from '../../stores/mails'
import BaseButton from '../ui/BaseButton.vue'
import BaseDialog from '../ui/BaseDialog.vue'

/** Déplacer vers : la boîte de réception, les archives, la corbeille ou un dossier personnel (sous-dossiers compris). */
const props = defineProps<{ open: boolean; /** dossier actuel du message (exclu de la liste) ; plusieurs messages : rien */ current?: string; count?: number }>()
const emit = defineEmits<{ pick: [target: MoveTarget]; close: [] }>()
const { t } = useI18n()
const mails = useMailsStore()
const choice = ref<MoveTarget>('archive')
const base = computed(() => (['inbox', 'archive', 'trash'] as const).filter((f) => f !== props.current))
const custom = computed(() => folderTree(mails.folders).filter((f) => f.target !== props.current))
watch(() => props.open, (o) => { if (o) { void mails.loadFolders(); choice.value = base.value[0] ?? 'archive' } })
</script>

<template>
  <BaseDialog :open="open" :title="t('mail.move')" @close="emit('close')">
    <fieldset class="moves">
      <legend>{{ t('mail.moveTo') }}</legend>
      <label v-for="f in base" :key="f" class="mv"><input v-model="choice" type="radio" name="move" :value="f" /> {{ t(`mail.folder.${f}`) }}</label>
      <template v-if="custom.length">
        <p class="mv-h">{{ t('folders.mine') }}</p>
        <label v-for="f in custom" :key="f.path" class="mv" :style="{ paddingInlineStart: `${f.depth * 20}px` }"><input v-model="choice" type="radio" name="move" :value="f.target" /> {{ f.name }}</label>
      </template>
      <p v-else class="muted mv-none">{{ t('folders.noneYet') }}</p>
    </fieldset>
    <template #footer>
      <BaseButton variant="primary" icon="folder" :label="t('mail.move')" @click="emit('pick', choice)" />
      <BaseButton :label="t('common.cancel')" @click="emit('close')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.moves { border: 0; padding: 0; margin: 0; display: grid; gap: 2px; max-height: 50vh; overflow-y: auto; }
.moves legend { font-weight: 700; margin-bottom: var(--space-2); }
.mv { display: flex; align-items: center; gap: var(--space-2); min-height: var(--target); }
.mv-h { margin: var(--space-2) 0 0; font-weight: 700; }
.mv-none { margin: 0; }
</style>
