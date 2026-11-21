<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDirectoryStore } from '../../stores/directory'
import { useSettingsStore } from '../../stores/settings'
import BaseCard from '../ui/BaseCard.vue'
import SwitchField from '../ui/SwitchField.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Annuaire de l'établissement (F-18) : le serveur se règle côté déploiement ; ici, ce qui s'affiche sous le nom dans la saisie des destinataires. */
const { t } = useI18n()
const dir = useDirectoryStore()
const settings = useSettingsStore()
onMounted(() => dir.load())
</script>

<template>
  <BaseCard class="stack">
    <h3>{{ t('settings.directory.title') }}</h3>
    <p>
      <ToneChip v-if="dir.configured" tone="success" icon="checkCircle" :label="t('settings.directory.on')" />
      <ToneChip v-else tone="neutral" icon="info" :label="t('settings.directory.off')" />
    </p>
    <p class="muted">{{ dir.configured ? t('settings.directory.help') : t('settings.directory.helpOff') }}</p>
    <p class="hint">{{ t('settings.directory.minimum') }}</p>
    <SwitchField v-model="settings.s.dirShowDepartment" :label="t('settings.directory.department')" :help="t('settings.directory.departmentHelp')" />
    <SwitchField v-model="settings.s.dirShowPhone" :label="t('settings.directory.phone')" :help="t('settings.directory.phoneHelp')" />
  </BaseCard>
</template>

<style scoped>
h3 { font-size: var(--fs-md); }
</style>
