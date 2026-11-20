<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import type { Mail } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Journal d'activité + notes internes (F-45, F-69) : réservés aux membres de l'alias, jamais envoyés dehors. */
defineProps<{ mail: Mail }>()
const emit = defineEmits<{ addNote: [text: string] }>()
const { t } = useI18n()
const fmt = useFormat()
const text = ref('')
function submit() {
  const v = text.value.trim()
  if (!v) return
  emit('addNote', v)
  text.value = ''
}
</script>

<template>
  <div class="wrap">
    <section aria-labelledby="act-t">
      <h3 id="act-t">{{ t('mail.activity.title') }}</h3>
      <ol class="log">
        <li v-for="(e, i) in mail.activity" :key="i"><time class="muted" :datetime="e.at">{{ fmt.dateTime(e.at) }}</time> · <strong>{{ e.author }}</strong> · {{ fmt.journal(e) }}</li>
      </ol>
    </section>
    <section aria-labelledby="notes-t">
      <h3 id="notes-t"><BaseIcon name="lock" :size="16" /> {{ t('mail.notes.title') }}</h3>
      <p class="muted">{{ t('mail.notes.help') }}</p>
      <ul class="notes">
        <li v-for="n in mail.notes" :key="n.id"><strong>{{ n.author }}</strong> · <time class="muted" :datetime="n.at">{{ fmt.dateTime(n.at) }}</time><p>{{ n.text }}</p></li>
      </ul>
      <form @submit.prevent="submit">
        <label for="note" class="sr-only">{{ t('mail.notes.label') }}</label>
        <input id="note" v-model="text" class="input" :placeholder="t('mail.notes.label')" autocomplete="off" />
        <BaseButton type="submit" icon="plus" :label="t('mail.notes.add')" />
      </form>
    </section>
  </div>
</template>

<style scoped>
.wrap { display: grid; gap: var(--space-4); grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
h3 { font-size: var(--fs-md); display: flex; gap: var(--space-2); align-items: center; margin-bottom: var(--space-2); }
.log, .notes { display: grid; gap: var(--space-2); font-size: var(--fs-sm); }
.notes li { background: var(--c-surface-2); border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3); }
form { display: flex; gap: var(--space-2); margin-top: var(--space-3); flex-wrap: wrap; }
form .input { flex: 1; min-width: 160px; }
</style>
