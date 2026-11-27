<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import type { Mail } from '../../domain/types'
import { useFormat } from '../../composables/useFormat'
import { useMailsStore } from '../../stores/mails'
import Avatar from '../ui/Avatar.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import MailBodyText from './MailBodyText.vue'

/**
 * Messages précédents de la conversation, sous le message ouvert, du plus récent au plus ancien : on remonte le temps en descendant.
 * Le plus proche est déjà déplié (ex. le mail d'origine d'une réponse). Chacun dit qui l'a écrit et quand.
 */
const props = defineProps<{ mail: Mail }>()
const { t } = useI18n()
const fmt = useFormat()
const mails = useMailsStore()

const thread = computed(() => mails.threadOf(props.mail))
const isEarlier = (m: Mail) => m.at < props.mail.at || (m.at === props.mail.at && m.id !== props.mail.id && m.folder === 'sent')
const earlier = computed(() => thread.value.filter((m) => m.id !== props.mail.id && isEarlier(m)).reverse())
/** Quand on lit un message ancien, on dit qu'il y a plus récent, avec le chemin pour y aller. */
const newest = computed(() => thread.value.filter((m) => m.id !== props.mail.id && !isEarlier(m)).at(-1))
const open = ref<Set<string>>(new Set())
const isOpen = (m: Mail) => open.value.has(m.id) || (m.id === earlier.value[0]?.id && !closed.value.has(m.id))
const closed = ref<Set<string>>(new Set())
function toggle(m: Mail) {
  if (isOpen(m)) { open.value.delete(m.id); closed.value.add(m.id) } else { closed.value.delete(m.id); open.value.add(m.id); void mails.loadBody(m.id) }
  open.value = new Set(open.value); closed.value = new Set(closed.value)
}
const last = computed(() => earlier.value[0])
onMounted(() => { if (last.value) void mails.loadBody(last.value.id) })
watch(last, (m) => { if (m) void mails.loadBody(m.id) })
const who = (m: Mail) => (m.folder === 'sent' ? t('mail.thread.me') : m.from.name)
</script>

<template>
  <p v-if="newest" class="callout newer" data-tone="info">
    <BaseIcon name="reply" :size="16" />
    <span>{{ t('mail.thread.newer', { who: who(newest), date: fmt.dateTime(newest.at) }) }}</span>
    <RouterLink class="btn" :to="{ name: 'mails', params: { id: newest.id } }">{{ t('mail.thread.seeNewer') }}</RouterLink>
  </p>
  <section v-if="earlier.length" class="thread" :aria-label="t('mail.thread.title')">
    <h3>{{ t('mail.thread.previous') }} · {{ t('mail.thread.count', { n: earlier.length }, earlier.length) }}</h3>
    <ul>
      <li v-for="m in earlier" :key="m.id" class="msg" :class="{ mine: m.folder === 'sent' }">
        <button type="button" class="line" :aria-expanded="isOpen(m)" :aria-controls="`th-${m.id}`" @click="toggle(m)">
          <Avatar :name="who(m)" :size="28" />
          <strong>{{ who(m) }}</strong>
          <span class="when muted"><time :datetime="m.at">{{ fmt.dateTime(m.at) }}</time></span>
          <BaseIcon :name="isOpen(m) ? 'chevronUp' : 'chevronDown'" :size="16" />
        </button>
        <div v-if="isOpen(m)" :id="`th-${m.id}`" class="text">
          <MailBodyText :mail="m" />
          <p v-if="mails.loadingBody.has(m.id)" class="muted" role="status">{{ t('mail.bodyLoading') }}</p>
          <p v-else-if="mails.gone.has(m.id)" class="muted">{{ t('mail.bodyGone') }}</p>
          <p v-else-if="mails.bodyError.has(m.id)" class="error-text" role="alert">{{ t('mail.bodyError') }}</p>
          <p v-else-if="!m.paragraphs.length && !m.html" class="muted">{{ t('mail.bodyEmpty') }}</p>
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.thread { display: grid; gap: var(--space-2); }
.newer { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin: 0; }
.thread h3 { font-size: var(--fs-sm); color: var(--c-text-muted); }
ul { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.msg { background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); overflow: hidden; }
.msg.mine { border-inline-start: 4px solid var(--c-accent); }
.line { display: flex; align-items: center; gap: var(--space-2); width: 100%; min-height: 44px; padding: var(--space-2) var(--space-3); background: none; border: 0; text-align: start; color: inherit; font: inherit; cursor: pointer; }
.when { margin-inline-start: auto; font-size: var(--fs-sm); }
.text { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--space-2); padding: 0 var(--space-4) var(--space-3); }
</style>
