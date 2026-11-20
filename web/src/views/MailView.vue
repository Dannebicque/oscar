<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import MailList from '../components/mail/MailList.vue'
import MailReader from '../components/mail/MailReader.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { SHARED } from '../api'
import type { MailFolder } from '../domain/types'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'

const props = defineProps<{ id?: string }>()
const { t } = useI18n()
const router = useRouter()
const mails = useMailsStore()
const suivis = useSuivisStore()

type Box = { mailbox: string; folder: MailFolder | 'suivis' }
const box = ref<Box>({ mailbox: 'personal', folder: 'inbox' })
onMounted(() => Promise.all([mails.load(), suivis.load()]))

const FOLDERS: MailFolder[] = ['inbox', 'sent', 'drafts', 'archive', 'trash']
const list = computed(() => {
  const all = mails.items.filter((m) => m.mailbox === box.value.mailbox)
  const base = box.value.folder === 'suivis' ? all.filter((m) => m.suivi && suivis.byId(m.suivi)?.status !== 'done') : all.filter((m) => m.folder === box.value.folder)
  return [...base].sort((a, b) => b.at.localeCompare(a.at))
})
const selected = computed(() => mails.byId(props.id ?? '') ?? null)
const sharedBox = computed(() => box.value.mailbox !== 'personal')

// Un lien direct vers un mail (depuis Suivis ou l'accueil) place le panneau sur la bonne boîte.
watch(() => [props.id, mails.items.length], () => {
  const m = props.id ? mails.byId(props.id) : null
  if (m && (m.mailbox !== box.value.mailbox)) box.value = { mailbox: m.mailbox, folder: 'inbox' }
  if (m) mails.markRead(m.id)
}, { immediate: true })

function pick(mailbox: string, folder: Box['folder']) {
  box.value = { mailbox, folder }
  if (props.id) router.push({ name: 'mails' })
}
const select = (id: string) => router.push({ name: 'mails', params: { id } })
const count = (mailbox: string) => mails.unread(mailbox)
const suivisCount = computed(() => suivis.mine.filter((s) => s.status !== 'done' && s.mailId).length)
</script>

<template>
  <PageHeader :title="t('mail.title')">
    <BaseButton variant="primary" icon="edit" :label="t('mail.compose')" @click="router.push('/ecrire')" />
  </PageHeader>

  <ErrorState v-if="mails.error" @retry="mails.load()" />
  <div v-else class="layout">
    <nav class="folders" :aria-label="t('mail.folders')">
      <ul>
        <li v-for="f in FOLDERS" :key="f">
          <button type="button" :aria-current="box.mailbox === 'personal' && box.folder === f ? 'true' : undefined" @click="pick('personal', f)">
            <BaseIcon :name="f === 'inbox' ? 'inbox' : f === 'sent' ? 'send' : f === 'drafts' ? 'draft' : f === 'archive' ? 'archive' : 'trash'" :size="18" />
            <span>{{ t(`mail.folder.${f}`) }}</span><span v-if="f === 'inbox' && count('personal')" class="n">{{ count('personal') }}</span>
          </button>
        </li>
        <li>
          <button type="button" :aria-current="box.mailbox === 'personal' && box.folder === 'suivis' ? 'true' : undefined" @click="pick('personal', 'suivis')">
            <BaseIcon name="follow" :size="18" /><span>{{ t('mail.folder.suivis') }}</span><span class="n">{{ suivisCount }}</span>
          </button>
        </li>
      </ul>
      <h2 class="sec">{{ t('mail.shared') }}</h2>
      <ul>
        <li>
          <button type="button" :aria-current="box.mailbox === SHARED.id ? 'true' : undefined" @click="pick(SHARED.id, 'inbox')">
            <BaseIcon name="users" :size="18" /><span>{{ `${SHARED.address.split('@')[0]}@` }}</span><span v-if="count(SHARED.id)" class="n">{{ count(SHARED.id) }}</span>
          </button>
        </li>
      </ul>
    </nav>

    <div class="mid">
      <p v-if="sharedBox" class="members muted">{{ t('mail.sharedMembers') }}</p>
      <p v-if="mails.loading" class="muted">{{ t('common.loading') }}</p>
      <MailList v-else :mails="list" :selected-id="id ?? null" :shared="sharedBox" @select="select" />
    </div>

    <div class="read">
      <MailReader v-if="selected" :key="selected.id" :mail="selected" />
      <EmptyState v-else icon="mail" :text="t('mail.selectOne')" />
    </div>
  </div>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: 200px minmax(280px, 340px) minmax(0, 1fr); gap: var(--space-4); align-items: start; }
.folders { display: grid; gap: var(--space-2); position: sticky; top: var(--space-4); }
.folders ul { display: grid; gap: 2px; }
.folders .sec { font-size: var(--fs-sm); color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-top: var(--space-3); }
.folders button { width: 100%; display: flex; align-items: center; gap: var(--space-2); min-height: var(--target); padding: 0 var(--space-3); border: 0; background: transparent; border-radius: var(--radius-sm); text-align: left; }
.folders button:hover { background: var(--c-surface-2); }
.folders button[aria-current='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; }
.folders button span:nth-child(2) { flex: 1; }
.n { min-width: 24px; padding: 0 6px; text-align: center; border-radius: var(--radius-pill); background: var(--c-surface-2); font-size: var(--fs-sm); font-weight: 800; }
.mid { display: grid; gap: var(--space-2); }
.members { font-size: var(--fs-sm); }
@media (max-width: 1100px) { .layout { grid-template-columns: 180px minmax(0, 1fr); } .read { grid-column: 1 / -1; } .folders { position: static; } }
@media (max-width: 720px) {
  .layout { grid-template-columns: minmax(0, 1fr); }
  .folders { position: static; }
  .folders ul { grid-auto-flow: column; grid-auto-columns: max-content; overflow-x: auto; }
}
</style>
