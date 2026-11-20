<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Suivi } from '../domain/types'
import StateChip from './StateChip.vue'
const props = defineProps<{ suivi: Suivi }>()
defineEmits<{ close: [id: string] }>()
const { t, locale } = useI18n()
const date = () => new Date(props.suivi.dueAt).toLocaleDateString(locale.value, { dateStyle: 'medium' })
</script>

<template>
  <li class="card">
    <div class="body">
      <p class="meta">
        <StateChip :state="suivi.state" />
        <span v-if="suivi.suggestedByAi" class="ai">{{ t('suivis.aiBadge') }}</span>
      </p>
      <h3>{{ suivi.subject }}</h3>
      <p class="sub">{{ suivi.contact }} · {{ t('suivis.due', { date: date() }) }}</p>
    </div>
    <button v-if="suivi.state !== 'done'" type="button" :aria-label="t('suivis.closeLabel', { subject: suivi.subject })" @click="$emit('close', suivi.id)">
      {{ t('suivis.close') }}
    </button>
  </li>
</template>

<style scoped>
.card { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e4e8ee; border-radius: 14px; padding: 14px 18px; }
.body { flex: 1; }
h3 { margin: 6px 0 2px; font-size: 16px; }
.meta { margin: 0; display: flex; gap: 8px; align-items: center; }
.sub { margin: 0; font-size: 14px; color: var(--grey); }
.ai { font-size: 12px; font-weight: 700; color: var(--violet); background: #efebff; border-radius: 999px; padding: 2px 10px; }
button { padding: 0 16px; border: 1px solid #c5ccd6; border-radius: 10px; background: #fff; cursor: pointer; }
</style>
