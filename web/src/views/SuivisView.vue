<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SuiviCard from '../components/SuiviCard.vue'
import { useSuivisStore } from '../stores/suivis'
const store = useSuivisStore()
const { t } = useI18n()
onMounted(() => store.load())
</script>

<template>
  <h1>{{ t('suivis.title') }}</h1>
  <p class="count" aria-live="polite">{{ t('suivis.actionCount', { n: store.actionCount }, store.actionCount) }}</p>
  <p v-if="store.loading">{{ t('suivis.loading') }}</p>
  <p v-else-if="store.items.length === 0">{{ t('suivis.empty') }}</p>
  <ul v-else class="list">
    <SuiviCard v-for="s in store.sorted" :key="s.id" :suivi="s" @close="store.close" />
  </ul>
</template>

<style scoped>
h1 { margin: 0 0 4px; font-size: 24px; }
.count { margin: 0 0 16px; color: var(--grey); }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
</style>
