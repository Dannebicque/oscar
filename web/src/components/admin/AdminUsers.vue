<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api, session } from '../../api'
import { useFormat } from '../../composables/useFormat'
import type { AdminUser } from '../../domain/types'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import TextField from '../ui/TextField.vue'
import ToneChip from '../ui/ToneChip.vue'

/**
 * Comptes de l'instance. On y voit qui existe et dans quel état, jamais ce qu'ils contiennent. Le lien de réinitialisation part par e-mail
 * à l'utilisateur seulement : l'administrateur ne peut donc jamais prendre la main sur un compte.
 */
const { t } = useI18n()
const fmt = useFormat()
const ui = useUiStore()
const users = ref<AdminUser[]>([])
const loaded = ref(false)
const query = ref('')

const shown = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? users.value.filter((u) => `${u.name} ${u.email ?? ''} ${u.username}`.toLowerCase().includes(q)) : users.value
})
const label = (u: AdminUser) => u.email ?? u.username
const isMe = (u: AdminUser) => u.username === session.username

async function load() {
  try { users.value = await api.instance.users() } catch { ui.notify(t('common.actionFailed'), { tone: 'error' }) } finally { loaded.value = true }
}
onMounted(load)
function fail(e: unknown) { ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' }) }

async function toggle(u: AdminUser) {
  try { await api.instance.setDisabled(u.id, !u.disabled); ui.notify(t(u.disabled ? 'adminUsers.enabled' : 'adminUsers.disabledDone', { who: label(u) })); await load() } catch (e) { fail(e) }
}
async function makeAdmin(u: AdminUser) {
  try { await api.instance.setAdmin(u.id, !u.admin); ui.notify(t(u.admin ? 'adminUsers.adminRemoved' : 'adminUsers.adminGiven', { who: label(u) })); await load() } catch (e) { fail(e) }
}
async function resetLink(u: AdminUser) {
  try {
    const r = await api.instance.resetLink(u.id)
    ui.notify(r.emailSent ? t('adminUsers.resetSent', { who: label(u) }) : t('adminUsers.mailFailed'), { tone: r.emailSent ? 'success' : 'error' })
  } catch (e) { fail(e) }
}

// Inviter
const inviting = ref(false)
const invite = reactive({ name: '', email: '' })
const inviteErrors = ref<Record<string, string>>({})
const busy = ref(false)
function openInvite() { Object.assign(invite, { name: '', email: '' }); inviteErrors.value = {}; inviting.value = true }
async function sendInvite() {
  inviteErrors.value = {}
  busy.value = true
  try {
    const r = await api.instance.invite(invite.name.trim(), invite.email.trim())
    ui.notify(r.emailSent ? t('adminUsers.invited', { who: invite.email.trim() }) : t('adminUsers.invitedNoMail', { who: invite.email.trim() }), { tone: r.emailSent ? 'success' : 'info' })
    inviting.value = false
    await load()
  } catch (e) {
    const f = e instanceof ApiError ? (e.data as { field?: string }).field : undefined
    if (f) inviteErrors.value = { [f]: (e as Error).message }
    else fail(e)
  } finally {
    busy.value = false
  }
}

// Supprimer
const removing = ref<AdminUser | null>(null)
const confirmText = ref('')
const removeError = ref('')
function openRemove(u: AdminUser) { removing.value = u; confirmText.value = ''; removeError.value = '' }
async function remove() {
  const u = removing.value
  if (!u) return
  busy.value = true
  try {
    await api.instance.deleteUser(u.id, confirmText.value)
    ui.notify(t('adminUsers.deleted', { who: label(u) }), { tone: 'info' })
    removing.value = null
    await load()
  } catch (e) {
    removeError.value = e instanceof Error && e.message ? e.message : t('common.actionFailed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <BaseCard class="stack" aria-labelledby="au-h">
    <div class="head">
      <h2 id="au-h">{{ t('adminUsers.title') }}</h2>
      <BaseButton icon="plus" :label="t('adminUsers.invite')" @click="openInvite" />
    </div>
    <p class="muted">{{ t('adminUsers.help') }}</p>
    <div class="field"><label for="au-q" class="sr-only">{{ t('adminUsers.search') }}</label><input id="au-q" v-model="query" type="search" class="input" :placeholder="t('adminUsers.search')" /></div>

    <p v-if="!loaded" class="muted">{{ t('common.loading') }}</p>
    <ul v-else class="list">
      <li v-for="u in shown" :key="u.id" class="item">
        <div class="info">
          <strong>{{ u.name }}</strong> <span class="addr">{{ label(u) }}</span>
          <span class="chips">
            <ToneChip v-if="u.admin" tone="info" icon="lock" :label="t('adminUsers.admin')" />
            <ToneChip v-if="u.disabled" tone="warn" icon="pause" :label="t('adminUsers.disabled')" />
            <ToneChip tone="neutral" :icon="u.local ? 'user' : 'users'" :label="u.local ? t('adminUsers.local') : t('adminUsers.institution')" />
          </span>
          <span class="muted line">{{ u.lastLoginAt ? t('adminUsers.lastLogin', { when: fmt.dateTime(u.lastLoginAt) }) : t('adminUsers.neverLoggedIn') }} · {{ t('adminUsers.mailAccounts', { n: u.mailAccounts }, u.mailAccounts) }}</span>
        </div>
        <div class="acts">
          <BaseButton v-if="!isMe(u)" :label="u.disabled ? t('adminUsers.enable') : t('adminUsers.disable')" @click="toggle(u)" />
          <BaseButton v-if="u.local" variant="ghost" :label="t('adminUsers.resetLink')" @click="resetLink(u)" />
          <BaseButton v-if="!isMe(u)" variant="ghost" :label="u.admin ? t('adminUsers.removeAdmin') : t('adminUsers.makeAdmin')" @click="makeAdmin(u)" />
          <BaseButton v-if="!isMe(u)" variant="ghost" icon="trash" :label="t('adminUsers.delete')" @click="openRemove(u)" />
        </div>
      </li>
      <li v-if="shown.length === 0" class="muted">{{ t('adminUsers.none') }}</li>
    </ul>
  </BaseCard>

  <BaseDialog :open="inviting" :title="t('adminUsers.invite')" :description="t('adminUsers.inviteHelp')" @close="inviting = false">
    <form id="invite-form" class="stack" novalidate @submit.prevent="sendInvite">
      <TextField id="inv-name" v-model="invite.name" :label="t('auth.name')" :error="inviteErrors.name" required />
      <TextField id="inv-email" v-model="invite.email" type="email" :label="t('auth.email')" :error="inviteErrors.email" required />
    </form>
    <template #footer>
      <BaseButton variant="primary" type="submit" form="invite-form" icon="send" :disabled="busy" :label="t('adminUsers.sendInvite')" />
      <BaseButton :label="t('common.cancel')" @click="inviting = false" />
    </template>
  </BaseDialog>

  <BaseDialog :open="removing !== null" :title="t('adminUsers.deleteTitle', { who: removing ? label(removing) : '' })" @close="removing = null">
    <div class="stack">
      <p>{{ t('adminUsers.deleteText') }}</p>
      <TextField id="del-confirm" v-model="confirmText" :label="t('adminUsers.confirmLabel', { who: removing ? label(removing) : '' })" :error="removeError" />
    </div>
    <template #footer>
      <BaseButton variant="danger" icon="trash" :disabled="busy || !confirmText" :label="t('adminUsers.delete')" @click="remove" />
      <BaseButton :label="t('common.cancel')" @click="removing = null" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
h2 { font-size: var(--fs-md); margin: 0; }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.item { display: flex; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; padding: var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface-2); }
.info { display: grid; gap: var(--space-1); min-width: 0; flex: 1 1 280px; }
.addr { color: var(--c-text-muted); overflow-wrap: anywhere; }
.chips { display: flex; gap: var(--space-1); flex-wrap: wrap; }
.line { font-size: var(--fs-sm); }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: flex-start; }
</style>
