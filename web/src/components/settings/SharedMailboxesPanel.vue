<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, session } from '../../api'
import type { SharedMailboxInfo } from '../../domain/types'
import { useAccountsStore } from '../../stores/accounts'
import { useMailsStore } from '../../stores/mails'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SelectField from '../ui/SelectField.vue'
import SwitchField from '../ui/SwitchField.vue'
import TextField from '../ui/TextField.vue'
import ToneChip from '../ui/ToneChip.vue'

/** Boîtes partagées (F-40) : créer, gérer les membres, quitter. Aucun responsable n'est désigné ici (F-67). */
const { t } = useI18n()
const accounts = useAccountsStore()
const ui = useUiStore()
const mails = useMailsStore()
const busy = ref(false)

const creating = ref(false)
const form = reactive({ address: '', name: '' })
const createError = ref('')
const invite = reactive<Record<string, { who: string; role: 'member' | 'admin'; error: string }>>({})
const inviteOf = (id: string) => (invite[id] ??= { who: '', role: 'member', error: '' })
const roleOptions = computed(() => [{ value: 'member' as const, label: t('settings.accounts.roleMember') }, { value: 'admin' as const, label: t('settings.accounts.roleAdmin') }])
type Confirm = { kind: 'leave' | 'delete'; box: SharedMailboxInfo } | { kind: 'remove'; box: SharedMailboxInfo; username: string; name: string }
const confirm = ref<Confirm | null>(null)
const confirmTitle = computed(() => {
  const c = confirm.value
  if (!c) return ''
  if (c.kind === 'remove') return t('settings.accounts.sharedBox.removeTitle', { name: c.name, address: c.box.address })
  return t(`settings.accounts.sharedBox.${c.kind}Title`, { address: c.box.address })
})
const confirmLabel = computed(() => {
  const c = confirm.value
  if (!c) return ''
  return c.kind === 'remove' ? t('settings.accounts.sharedBox.remove', { name: c.name }) : t(`settings.accounts.sharedBox.${c.kind}`)
})
const me = () => session.username
const message = (e: unknown) => (e instanceof ApiError && e.message ? e.message : t('common.actionFailed'))

async function create() {
  createError.value = ''
  busy.value = true
  try {
    const box = await accounts.createShared({ address: form.address.trim(), name: form.name.trim() })
    ui.notify(t('settings.accounts.sharedBox.created', { address: box.address }))
    creating.value = false
    Object.assign(form, { address: '', name: '' })
  } catch (e) { createError.value = message(e) } finally { busy.value = false }
}
async function add(box: SharedMailboxInfo) {
  const f = inviteOf(box.id)
  f.error = ''
  try {
    await accounts.addSharedMember(box.id, f.who.trim(), f.role)
    ui.notify(t('settings.accounts.sharedBox.added', { name: f.who.trim() }))
    f.who = ''
  } catch (e) { f.error = message(e) }
}
async function attempt(action: () => Promise<unknown>, done?: string) {
  try { await action(); if (done) ui.notify(done, { tone: 'info' }) } catch (e) { ui.notify(message(e), { tone: 'error' }) }
}
async function confirmed() {
  const c = confirm.value
  confirm.value = null
  if (!c) return
  if (c.kind === 'delete') await attempt(() => accounts.removeShared(c.box.id), t('settings.accounts.sharedBox.deleted', { address: c.box.address }))
  else if (c.kind === 'leave') await attempt(() => accounts.removeSharedMember(c.box.id, me()), t('settings.accounts.sharedBox.left', { address: c.box.address }))
  else if ('username' in c) {
    const { box, username, name } = c
    await attempt(() => accounts.removeSharedMember(box.id, username), t('settings.accounts.sharedBox.removed', { name }))
  }
  // Quitter ou supprimer remet des mails dans la boîte personnelle : la liste doit le montrer.
  if (c.kind !== 'remove') await mails.load()
}
</script>

<template>
  <BaseCard class="stack">
    <div class="head">
      <h3>{{ t('settings.accounts.sharedBoxes') }}</h3>
      <BaseButton icon="plus" :label="t('settings.accounts.sharedBox.add')" @click="creating = true; createError = ''" />
    </div>
    <ul v-if="accounts.invitations.length" class="boxes" :aria-label="t('settings.accounts.sharedBox.invitations')">
      <li v-for="b in accounts.invitations" :key="b.id" class="box invite-card" :data-invitation="b.address">
        <ToneChip tone="warn" icon="users" :label="t('settings.accounts.sharedBox.invitation')" />
        <strong class="addr">{{ b.name }} · {{ b.address }}</strong>
        <p>{{ t('settings.accounts.sharedBox.invitationText', { names: b.members.filter((m) => !m.pending).map((m) => m.displayName).join(', ') }) }}</p>
        <div class="acts">
          <BaseButton variant="primary" :label="t('settings.accounts.sharedBox.accept')" @click="attempt(async () => { await accounts.acceptShared(b.id); await mails.load() }, t('settings.accounts.sharedBox.accepted', { address: b.address }))" />
          <BaseButton :label="t('settings.accounts.sharedBox.decline')" @click="attempt(() => accounts.removeSharedMember(b.id, me()), t('settings.accounts.sharedBox.declined', { address: b.address }))" />
        </div>
      </li>
    </ul>
    <p v-if="accounts.shared.length === 0 && accounts.invitations.length === 0" class="muted">{{ t('settings.accounts.noShared') }}</p>
    <ul v-if="accounts.shared.length" class="boxes">
      <li v-for="b in accounts.shared" :key="b.id" class="box" :data-box="b.address">
        <div class="head">
          <strong class="addr">{{ b.name }} · {{ b.address }}</strong>
          <ToneChip tone="info" icon="users" :label="b.role === 'admin' ? t('settings.accounts.roleAdmin') : t('settings.accounts.roleMember')" />
        </div>
        <ul class="members" :aria-label="t('settings.accounts.sharedBox.members', { address: b.address })">
          <li v-for="m in b.members" :key="m.username" class="member">
            <span class="who">{{ m.displayName }} <span class="muted">· {{ m.role === 'admin' ? t('settings.accounts.roleAdmin') : t('settings.accounts.roleMember') }}<template v-if="m.pending"> · {{ t('settings.accounts.sharedBox.pending') }}</template></span></span>
            <span v-if="b.role === 'admin' && m.username !== me()" class="acts">
              <BaseButton variant="ghost" icon="user" :label="t(m.role === 'admin' ? 'settings.accounts.sharedBox.makeMember' : 'settings.accounts.sharedBox.makeAdmin', { name: m.displayName })" icon-only @click="attempt(() => accounts.addSharedMember(b.id, m.username, m.role === 'admin' ? 'member' : 'admin'))" />
              <BaseButton variant="ghost" icon="trash" icon-only :label="t('settings.accounts.sharedBox.remove', { name: m.displayName })" @click="confirm = { kind: 'remove', box: b, username: m.username, name: m.displayName }" />
            </span>
          </li>
        </ul>
        <template v-if="b.role === 'admin'">
          <form class="invite" novalidate @submit.prevent="add(b)">
            <TextField :id="`who-${b.id}`" v-model="inviteOf(b.id).who" :label="t('settings.accounts.sharedBox.member')" :hint="t('settings.accounts.sharedBox.memberHint')" :error="inviteOf(b.id).error" />
            <SelectField :id="`role-${b.id}`" v-model="inviteOf(b.id).role" :label="t('settings.accounts.sharedBox.role')" :options="roleOptions" />
            <div class="go"><BaseButton type="submit" variant="primary" icon="plus" :disabled="!inviteOf(b.id).who.trim()" :label="t('settings.accounts.sharedBox.addMember')" /></div>
          </form>
          <SwitchField :model-value="b.autoFollow ?? true" :label="t('settings.accounts.sharedBox.autoFollow')" :help="t('settings.accounts.sharedBox.autoFollowHelp')" @update:model-value="(v: boolean) => attempt(() => accounts.updateShared(b.id, { autoFollow: v }))" />
          <SwitchField :model-value="b.aiEnabled" :label="t('settings.accounts.sharedBox.ai')" :help="t('settings.accounts.sharedBox.aiHelp')" @update:model-value="(v: boolean) => attempt(() => accounts.updateShared(b.id, { aiEnabled: v }))" />
        </template>
        <div class="acts">
          <BaseButton :label="t('settings.accounts.sharedBox.leave')" @click="confirm = { kind: 'leave', box: b }" />
          <BaseButton v-if="b.role === 'admin'" variant="danger" icon="trash" :label="t('settings.accounts.sharedBox.delete')" @click="confirm = { kind: 'delete', box: b }" />
        </div>
      </li>
    </ul>
    <p class="hint">{{ t('settings.accounts.sharedHelp') }}</p>
  </BaseCard>

  <BaseDialog :open="creating" :title="t('settings.accounts.sharedBox.add')" :description="t('settings.accounts.sharedBox.privacy')" @close="creating = false">
    <form id="box-form" class="stack" novalidate @submit.prevent="create">
      <TextField id="box-address" v-model="form.address" type="email" :label="t('settings.accounts.sharedBox.address')" :hint="t('settings.accounts.sharedBox.addressHint')" :error="createError" required />
      <TextField id="box-name" v-model="form.name" :label="t('settings.accounts.sharedBox.name')" required />
    </form>
    <template #footer>
      <BaseButton variant="primary" type="submit" form="box-form" :disabled="busy || !form.address.trim() || !form.name.trim()" :label="t('settings.accounts.sharedBox.create')" />
      <BaseButton :label="t('common.cancel')" @click="creating = false" />
    </template>
  </BaseDialog>

  <BaseDialog :open="confirm !== null" :title="confirmTitle" @close="confirm = null">
    <p v-if="confirm">{{ t(`settings.accounts.sharedBox.${confirm.kind}Text`) }}</p>
    <template #footer>
      <BaseButton v-if="confirm" variant="danger" :label="confirmLabel" @click="confirmed" />
      <BaseButton :label="t('common.cancel')" @click="confirm = null" />
    </template>
  </BaseDialog>
</template>

<style scoped>
h3 { font-size: var(--fs-md); }
.head, .acts { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
.head { justify-content: space-between; }
.addr { overflow-wrap: anywhere; min-width: 0; }
.boxes, .members { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.box { display: grid; gap: var(--space-3); padding: var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface-2); min-width: 0; }
.member { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); min-height: var(--target); flex-wrap: wrap; }
.who { overflow-wrap: anywhere; }
.invite { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto; gap: var(--space-3); align-items: start; }
.go { padding-top: 28px; }
@media (max-width: 900px) { .invite { grid-template-columns: minmax(0, 1fr); } .go { padding-top: 0; } }
</style>
