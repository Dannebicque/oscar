<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '../../composables/useFormat'
import { briefFor } from '../../domain/meetingBrief'
import type { CalEvent } from '../../domain/types'
import { useAccountsStore } from '../../stores/accounts'
import { useMailsStore } from '../../stores/mails'
import { useNow } from '../../composables/useNow'
import BaseIcon from '../ui/BaseIcon.vue'

/** « Avant ce rendez-vous » : les derniers échanges avec les participants. Calcul local, lecture seule. */
const props = defineProps<{ event: CalEvent }>()
const { t } = useI18n()
const fmt = useFormat()
const mails = useMailsStore()
const accounts = useAccountsStore()
const now = useNow()
// L'agenda peut s'ouvrir avant les mails : on les charge si besoin.
onMounted(() => { if (!mails.items.length) void mails.load(); if (!accounts.loaded) void accounts.load() })
const brief = computed(() => briefFor(props.event, mails.items, now.value, accounts.mail.map((a) => a.address)))
</script>

<template>
  <section class="brief" :aria-label="t('brief.title')">
    <h3 class="bt">{{ t('brief.title') }}</h3>
    <p v-if="brief.people.length" class="muted bp"><BaseIcon name="users" :size="14" /> {{ brief.people.join(', ') }}</p>
    <p v-if="brief.items.length === 0" class="muted">{{ brief.people.length ? t('brief.none') : t('brief.noPeople') }}</p>
    <ul v-else class="bl">
      <li v-for="i in brief.items" :key="i.mail.id">
        <RouterLink :to="{ name: 'mails', params: { id: i.mail.id } }" class="bi">
          <BaseIcon :name="i.direction === 'sent' ? 'send' : 'mail'" :size="14" />
          <span class="bs"><strong>{{ i.direction === 'sent' ? t('brief.sentTo', { who: i.who }) : t('brief.receivedFrom', { who: i.who }) }}</strong> · {{ i.mail.subject }}</span>
          <time class="muted" :datetime="i.mail.at">{{ fmt.date(i.mail.at) }}</time>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.brief { display: grid; gap: var(--space-2); padding-top: var(--space-3); border-top: var(--bw) solid var(--c-border); }
.bt { font-size: var(--fs-md); margin: 0; }
.bp { display: flex; align-items: center; gap: var(--space-2); margin: 0; overflow-wrap: anywhere; }
.bl { list-style: none; margin: 0; padding: 0; display: grid; }
.bi { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--space-2); align-items: center; min-height: var(--target); color: var(--c-text); text-decoration: none; border-top: var(--bw) solid var(--c-border); }
.bl li:first-child .bi { border-top: 0; }
.bi:hover .bs { text-decoration: underline; }
.bs { overflow-wrap: anywhere; }
</style>
