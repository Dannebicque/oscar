<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ScheduledList from '../components/mail/ScheduledList.vue'
import MailList from '../components/mail/MailList.vue'
import MailReader from '../components/mail/MailReader.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { SHORTCUT_EVENTS } from '../composables/useShortcuts'
import type { CustomFolder, MailFolder, MoveTarget } from '../domain/types'
import { customOf, folderTree, isCustom, pathOf, prettyPath } from '../domain/folders'
import FolderDialog from '../components/mail/FolderDialog.vue'
import MoveDialog from '../components/mail/MoveDialog.vue'
import { useAccountsStore } from '../stores/accounts'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'
import BaseDialog from '../components/ui/BaseDialog.vue'
import ConfirmTrashDialog from '../components/mail/ConfirmTrashDialog.vue'
import { useSuivisStore } from '../stores/suivis'

/** Webmail à trois volets (dossiers, liste, lecture), comme Gmail, Outlook et Thunderbird : l'écran entier est l'application. */
const props = defineProps<{ id?: string }>()
const { t } = useI18n()
const router = useRouter()
const mails = useMailsStore()
const suivis = useSuivisStore()
const accounts = useAccountsStore()

type Box = { mailbox: string; folder: MailFolder | CustomFolder | 'suivis' | 'scheduled' }
const box = ref<Box>({ mailbox: 'personal', folder: 'inbox' })
const compact = ref(false)
const listRef = ref<InstanceType<typeof MailList> | null>(null)
onMounted(() => Promise.all([mails.load(), suivis.load(), accounts.load(), mails.loadScheduled(), mails.loadFolders()]))

const FOLDERS: MailFolder[] = ['inbox', 'sent', 'drafts', 'archive', 'trash']
const ICON: Record<MailFolder, string> = { inbox: 'inbox', sent: 'send', drafts: 'draft', archive: 'archive', trash: 'trash' }
const list = computed(() => {
  const all = mails.items.filter((m) => m.mailbox === box.value.mailbox)
  const base = box.value.folder === 'suivis' ? all.filter((m) => m.suivi && suivis.byId(m.suivi)?.status !== 'done') : all.filter((m) => m.folder === box.value.folder)
  return [...base].sort((a, b) => b.at.localeCompare(a.at))
})
const selected = computed(() => mails.byId(props.id ?? '') ?? null)
const sharedBox = computed(() => box.value.mailbox !== 'personal')
const currentShared = computed(() => accounts.shared.find((b) => b.slug === box.value.mailbox))
const folderTitle = computed(() => (box.value.mailbox !== 'personal' ? `${box.value.mailbox}@` : isCustom(box.value.folder) ? prettyPath(pathOf(box.value.folder)) : t(`mail.folder.${box.value.folder}`)))
/** Dossiers personnels du serveur (sous-dossiers compris) et leur gestion. */
const myFolders = computed(() => folderTree(mails.folders))
const customBox = computed(() => (box.value.mailbox === 'personal' && isCustom(box.value.folder) ? pathOf(box.value.folder) : null))
const dlg = ref<{ mode: 'create' | 'rename'; path?: string; parent?: string } | null>(null)
const askDelete = ref(false)
async function openCustom(path: string) {
  pick('personal', customOf(path))
  await mails.openFolder(path)
}
/** Après une création ou un renommage, on se place dans le dossier concerné. */
function folderDone(path: string) {
  dlg.value = null
  box.value = { mailbox: 'personal', folder: customOf(path) }
}
async function deleteFolder() {
  const path = customBox.value
  askDelete.value = false
  if (!path) return
  try {
    await mails.removeFolder(path)
    ui.notify(t('folders.deleted', { name: prettyPath(path) }))
    box.value = { mailbox: 'personal', folder: 'inbox' }
  } catch (e) {
    ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  }
}

// Un lien direct vers un mail (depuis Suivis ou l'accueil) place le panneau sur la bonne boîte.
// Le dossier affiché est celui du message ouvert : un suivi d'un mail envoyé ouvre « Envoyés », pas la réception.
watch(() => [props.id, !!(props.id && mails.byId(props.id))], () => {
  const m = props.id ? mails.byId(props.id) : null
  if (!m) return
  if (!list.value.some((x) => x.id === m.id)) box.value = { mailbox: m.mailbox, folder: m.folder }
  mails.markRead(m.id)
}, { immediate: true })

function pick(mailbox: string, folder: Box['folder']) {
  box.value = { mailbox, folder }
  if (props.id) router.push({ name: 'mails' })
}
const select = (id: string) => router.push({ name: 'mails', params: { id } })
const count = (mailbox: string) => mails.unread(mailbox)
const suivisCount = computed(() => suivis.mine.filter((s) => s.status !== 'done' && s.mailId).length)

// Traitement par lot : même geste que pour un seul mail, annulable d'un coup.
const ui = useUiStore()
const settings = useSettingsStore()
const trashAsk = ref<string[] | null>(null)
const trashWithSuivi = computed(() => (trashAsk.value ?? []).filter((i) => mails.byId(i)?.suivi).length)
const moveAsk = ref<string[] | null>(null)
function confirmBulkMove(target: MoveTarget) {
  const ids = moveAsk.value ?? []
  moveAsk.value = null
  void runBulk(target, ids)
}
function bulk(action: 'archive' | 'trash' | 'inbox' | 'move', ids: string[]) {
  if (!ids.length) return
  if (action === 'move') { moveAsk.value = ids; return }
  if (action === 'trash' && settings.s.confirmDelete) { trashAsk.value = ids; return }
  void runBulk(action, ids)
}
function confirmBulkTrash(dontAsk: boolean) {
  const ids = trashAsk.value ?? []
  trashAsk.value = null
  if (dontAsk) settings.s.confirmDelete = false
  void runBulk('trash', ids)
}
async function runBulk(action: MoveTarget, ids: string[]) {
  const results = await Promise.allSettled(ids.map((i) => mails.moveTo(i, action)))
  const undos = results.flatMap((r) => (r.status === 'fulfilled' && r.value ? [r.value] : []))
  const failed = results.length - undos.length
  listRef.value?.clearSelection()
  if (props.id && ids.includes(props.id)) void router.push({ name: 'mails' })
  if (undos.length) {
    ui.notify(t('mail.bulk.moved', { n: undos.length, folder: isCustom(action) ? prettyPath(pathOf(action)) : t(`mail.folder.${action}`) }, undos.length), {
      undo: async () => { await Promise.allSettled(undos.map((u) => u())); ui.notify(t('mail.movedBack')) },
    })
  }
  if (failed) ui.notify(t('mail.bulk.failed', { n: failed }, failed), { tone: 'error' })
}

// j / k : message suivant / précédent dans la liste affichée.
function onMove(e: Event) {
  const items = listRef.value?.visible ?? []
  if (!items.length) return
  const i = items.findIndex((m) => m.id === props.id)
  const next = items[Math.min(items.length - 1, Math.max(0, i + ((e as CustomEvent<number>).detail ?? 1)))]
  if (next) { select(next.id); document.querySelector(`[data-mail="${next.id}"] button`)?.scrollIntoView({ block: 'nearest' }) }
}
// Après archivage ou corbeille : on passe au message suivant de la liste (ou au précédent s'il n'y en a plus), pour traiter à la chaîne.
function onAdvance(e: Event) {
  const step = (e as CustomEvent<{ handled: boolean; id: string }>).detail
  const items = listRef.value?.visible ?? []
  const i = items.findIndex((m) => m.id === step.id)
  const next = items[i + 1] ?? items[i - 1]
  step.handled = true
  if (next && i >= 0) select(next.id)
  else router.push({ name: 'mails' })
}
onMounted(() => window.addEventListener(SHORTCUT_EVENTS.advance, onAdvance))
onBeforeUnmount(() => window.removeEventListener(SHORTCUT_EVENTS.advance, onAdvance))
onMounted(() => window.addEventListener(SHORTCUT_EVENTS.move, onMove))
import { onBeforeUnmount } from 'vue'
onBeforeUnmount(() => window.removeEventListener(SHORTCUT_EVENTS.move, onMove))
</script>

<template>
  <div class="mail" :class="{ compact, reading: !!selected }">
    <ErrorState v-if="mails.error" class="err" @retry="mails.load()" />
    <template v-else>
      <nav class="folders" :aria-label="t('mail.folders')">
        <BaseButton variant="primary" icon="edit" :icon-only="compact" :label="t('mail.compose')" class="compose" :aria-label="t('mail.compose')" @click="router.push('/ecrire')" />
        <ul>
          <li v-for="f in FOLDERS" :key="f">
            <button type="button" class="fbtn" :aria-current="box.mailbox === 'personal' && box.folder === f ? 'true' : undefined" :aria-label="compact ? t(`mail.folder.${f}`) : undefined" @click="pick('personal', f)">
              <BaseIcon :name="ICON[f]" :size="18" /><span class="lbl">{{ t(`mail.folder.${f}`) }}</span><span v-if="f === 'inbox' && count('personal')" class="n">{{ count('personal') }}</span>
            </button>
          </li>
          <li>
            <button type="button" class="fbtn" :aria-current="box.mailbox === 'personal' && box.folder === 'suivis' ? 'true' : undefined" :aria-label="compact ? t('mail.folder.suivis') : undefined" @click="pick('personal', 'suivis')">
              <BaseIcon name="follow" :size="18" /><span class="lbl">{{ t('mail.folder.suivis') }}</span><span class="n">{{ suivisCount }}</span>
            </button>
          </li>
          <li>
            <button type="button" class="fbtn" :aria-current="box.mailbox === 'personal' && box.folder === 'scheduled' ? 'true' : undefined" :aria-label="compact ? t('mail.folder.scheduled') : undefined" @click="pick('personal', 'scheduled')">
              <BaseIcon name="clock" :size="18" /><span class="lbl">{{ t('mail.folder.scheduled') }}</span><span v-if="mails.scheduled.length" class="n">{{ mails.scheduled.length }}</span>
            </button>
          </li>
        </ul>
        <div class="fh"><h2 class="sec lbl">{{ t('folders.mine') }}</h2><button type="button" class="fadd" :aria-label="t('folders.newTitle')" :title="t('folders.newTitle')" @click="dlg = { mode: 'create' }"><BaseIcon name="plus" :size="16" /></button></div>
        <ul v-if="myFolders.length" class="ftree">
          <li v-for="f in myFolders" :key="f.path">
            <button type="button" class="fbtn" :style="{ paddingInlineStart: `calc(var(--space-3) + ${f.depth * 14}px)` }" :aria-current="box.mailbox === 'personal' && box.folder === f.target ? 'true' : undefined" :aria-label="compact ? prettyPath(f.path, f.delimiter) : undefined" :title="prettyPath(f.path, f.delimiter)" @click="openCustom(f.path)">
              <BaseIcon name="folder" :size="18" /><span class="lbl">{{ f.name }}</span><span v-if="f.count" class="n">{{ f.count }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="muted sec lbl fnone">{{ t('folders.noneYet') }}</p>
        <template v-if="accounts.shared.length">
          <h2 class="sec lbl">{{ t('mail.shared') }}</h2>
          <ul>
            <li v-for="b in accounts.shared" :key="b.id">
              <button type="button" class="fbtn" :aria-current="box.mailbox === b.slug ? 'true' : undefined" :aria-label="compact ? `${b.address.split('@')[0]}@` : undefined" @click="pick(b.slug, 'inbox')">
                <BaseIcon name="users" :size="18" /><span class="lbl">{{ `${b.address.split('@')[0]}@` }}</span><span v-if="count(b.slug)" class="n">{{ count(b.slug) }}</span>
              </button>
            </li>
          </ul>
        </template>
        <BaseButton class="collapse" variant="ghost" :icon="compact ? 'chevronRight' : 'chevronLeft'" :expanded="!compact" :icon-only="compact" :label="compact ? t('mail.expand') : t('mail.collapse')" @click="compact = !compact" />
      </nav>

      <div class="mid">
        <header class="mid-head"><h1>{{ folderTitle }}</h1><span v-if="customBox" class="fact"><BaseButton variant="ghost" icon="plus" icon-only :title="t('folders.newSub')" :label="t('folders.newSub')" @click="dlg = { mode: 'create', parent: customBox }" /><BaseButton variant="ghost" icon="edit" icon-only :title="t('folders.renameTitle')" :label="t('folders.renameTitle')" @click="dlg = { mode: 'rename', path: customBox }" /><BaseButton variant="ghost" icon="trash" icon-only :title="t('folders.delete')" :label="t('folders.delete')" @click="askDelete = true" /></span><p v-if="sharedBox && currentShared" class="hint">{{ t('mail.sharedMembers', { names: currentShared.members.map((m) => m.displayName).join(', ') }) }}</p></header>
        <p v-if="mails.loading" class="muted pad">{{ t('common.loading') }}</p>
        <ScheduledList v-else-if="box.folder === 'scheduled'" />
        <MailList v-else ref="listRef" :mails="list" :selected-id="id ?? null" :shared="sharedBox" :folder="box.folder" @select="select" @bulk="bulk" />
      </div>

      <div class="read">
        <MailReader v-if="selected" :key="selected.id" :mail="selected" show-back @back="router.push({ name: 'mails' })" />
        <EmptyState v-else icon="mail" :text="t('mail.selectOne')" class="none" />
      </div>
    </template>
  </div>
  <FolderDialog :open="dlg !== null" :mode="dlg?.mode ?? 'create'" :path="dlg?.path" :parent="dlg?.parent" @close="dlg = null" @done="folderDone" />
  <BaseDialog :open="askDelete" :title="t('folders.deleteTitle', { name: customBox ? prettyPath(customBox) : '' })" @close="askDelete = false">
    <p>{{ t('folders.deleteText') }}</p>
    <template #footer><BaseButton variant="danger" icon="trash" :label="t('folders.delete')" @click="deleteFolder" /><BaseButton :label="t('common.cancel')" @click="askDelete = false" /></template>
  </BaseDialog>
  <MoveDialog :open="moveAsk !== null" :count="moveAsk?.length ?? 0" @close="moveAsk = null" @pick="confirmBulkMove" />
  <ConfirmTrashDialog :open="trashAsk !== null" :count="trashAsk?.length ?? 0" :with-suivi="trashWithSuivi" @confirm="confirmBulkTrash" @close="trashAsk = null" />
</template>

<style scoped>
.mail { display: grid; grid-template-columns: 224px minmax(300px, 380px) minmax(0, 1fr); height: 100%; min-height: 0; background: var(--c-bg); }
.mail.compact { grid-template-columns: 64px minmax(300px, 380px) minmax(0, 1fr); }
.err { grid-column: 1 / -1; margin: var(--space-5); }
.folders { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); background: var(--c-surface); border-right: var(--bw) solid var(--c-border); min-height: 0; overflow-y: auto; }
.fh { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.fh h2 { margin: 0; }
.compact .fh { justify-content: center; }
.fadd { min-width: var(--target); min-height: var(--target); display: inline-flex; align-items: center; justify-content: center; background: transparent; border: 0; border-radius: var(--radius-sm); color: var(--c-text); }
.fadd:hover { background: var(--c-surface-2); }
.ftree { display: grid; gap: 2px; }
.fnone { margin: 0; }
.fact { display: inline-flex; gap: var(--space-1); }
.compose { width: 100%; margin-bottom: var(--space-2); }
.folders ul { display: grid; gap: 2px; }
.fbtn { width: 100%; display: flex; align-items: center; gap: var(--space-3); min-height: var(--target); padding: 0 var(--space-3); border: var(--bw) solid transparent; background: transparent; border-radius: var(--radius-sm); text-align: left; color: var(--c-text); }
.fbtn:hover { background: var(--c-surface-2); }
.fbtn[aria-current='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; border-color: color-mix(in srgb, var(--c-accent) 35%, transparent); }
.lbl { flex: 1; }
.n { min-width: 24px; padding: 0 6px; text-align: center; border-radius: var(--radius-pill); background: var(--c-surface-2); color: var(--c-text); font-size: var(--fs-sm); font-weight: 800; }
.sec { font-size: var(--fs-sm); color: var(--c-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-top: var(--space-3); padding-inline: var(--space-3); font-weight: 700; }
.collapse { margin-top: auto; align-self: flex-start; }
.compact .lbl, .compact .n, .compact .sec { display: none; }
.compact .fbtn { justify-content: center; padding: 0; }
.mid { display: grid; grid-template-rows: auto minmax(0, 1fr); min-height: 0; background: var(--c-surface); border-right: var(--bw) solid var(--c-border); }
.mid-head { padding: var(--space-3) var(--space-4) 0; display: grid; gap: 2px; }
.mid-head h1 { font-size: var(--fs-lg); }
.mid :deep(.list) { border-right: 0; }
.read { min-width: 0; min-height: 0; display: grid; container-type: inline-size; container-name: reading; }
.none { margin: var(--space-6); align-self: center; }
.pad { padding: var(--space-4); }

@media (max-width: 1100px) { .mail, .mail.compact { grid-template-columns: 64px minmax(280px, 340px) minmax(0, 1fr); } .lbl, .n, .sec { display: none; } .compose { padding: 0; justify-content: center; } .compose :deep(span) { display: none; } .fbtn { justify-content: center; padding: 0; } .collapse { display: none; } }
@media (max-width: 720px) {
  .mail, .mail.compact { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr); }
  .folders { flex-direction: row; overflow-x: auto; overflow-y: hidden; border-right: 0; border-bottom: var(--bw) solid var(--c-border); padding: var(--space-2); align-items: center; }
  .folders ul { display: flex; gap: var(--space-1); }
  .folders .compose { width: auto; margin: 0; }
  .sec, .collapse { display: none; }
  .mail.reading .mid, .mail.reading .folders { display: none; }
  .mail:not(.reading) .read { display: none; }
  .mail.reading { grid-template-rows: minmax(0, 1fr); }
}
</style>
