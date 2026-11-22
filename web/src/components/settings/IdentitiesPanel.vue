<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '../../api'
import type { Identity } from '../../domain/types'
import { useAccountsStore } from '../../stores/accounts'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseDialog from '../ui/BaseDialog.vue'
import SelectField from '../ui/SelectField.vue'
import TextareaField from '../ui/TextareaField.vue'
import TextField from '../ui/TextField.vue'
import ToneChip from '../ui/ToneChip.vue'

/**
 * Identités et signatures (F-19) : le nom affiché, l'adresse « De », l'adresse « Répondre à » et la signature de chaque identité.
 * Chaque compte a une identité principale ; on en ajoute pour un alias ou une fonction. Le choix se fait ensuite dans l'éditeur.
 */
const { t } = useI18n()
const accounts = useAccountsStore()
const ui = useUiStore()

const editing = ref<Identity | 'new' | null>(null)
const form = reactive({ name: '', address: '', replyTo: '', signature: '', accountId: '' })
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const toDelete = ref<Identity | null>(null)

const accountOptions = computed(() => accounts.mail.map((a) => ({ value: a.id, label: a.label })))
const accountLabel = (id: string) => accounts.mail.find((a) => a.id === id)?.label ?? ''
const isNew = computed(() => editing.value === 'new')
const primary = computed(() => (editing.value && editing.value !== 'new' ? editing.value.primary : false))
const firstLines = (s: string | null) => (s ?? '').split('\n').filter((l) => l.trim() && l.trim() !== '--').slice(0, 2).join(' · ')

function openNew() {
  Object.assign(form, { name: '', address: '', replyTo: '', signature: '', accountId: accounts.mail[0]?.id ?? '' })
  errors.value = {}
  editing.value = 'new'
}
function openEdit(i: Identity) {
  Object.assign(form, { name: i.name, address: i.address, replyTo: i.replyTo ?? '', signature: i.signature ?? '', accountId: i.accountId })
  errors.value = {}
  editing.value = i
}
function fail(e: unknown) {
  if (e instanceof ApiError) {
    const f = (e.data as { field?: string }).field
    if (f) { errors.value = { [f]: e.message }; return }
    ui.notify(e.message, { tone: 'error' })
    return
  }
  ui.notify(t('common.actionFailed'), { tone: 'error' })
}
async function save() {
  errors.value = {}
  if (!form.name.trim()) { errors.value = { name: t('identities.nameRequired') }; return }
  busy.value = true
  try {
    const body = { name: form.name.trim(), address: form.address.trim(), replyTo: form.replyTo.trim() || null, signature: form.signature.trim() ? form.signature : null }
    if (editing.value === 'new') await accounts.addIdentity({ ...body, accountId: form.accountId })
    else if (editing.value) await accounts.saveIdentity(editing.value.id, body)
    ui.notify(t('identities.saved', { name: form.name.trim() }))
    editing.value = null
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
async function makeDefault(i: Identity) {
  try { await accounts.makeDefaultIdentity(i.id); ui.notify(t('identities.defaulted', { name: i.name })) } catch (e) { fail(e) }
}
async function remove() {
  const i = toDelete.value
  toDelete.value = null
  if (!i) return
  try { await accounts.removeIdentity(i.id); ui.notify(t('identities.removed', { name: i.name }), { tone: 'info' }) } catch (e) { fail(e) }
}
</script>

<template>
  <BaseCard class="stack" aria-labelledby="ident-h">
    <div class="head"><h3 id="ident-h">{{ t('identities.title') }}</h3><BaseButton icon="plus" :disabled="accounts.mail.length === 0" :label="t('identities.add')" @click="openNew" /></div>
    <p class="muted">{{ t('identities.help') }}</p>
    <p v-if="accounts.identities.length === 0" class="muted">{{ t('identities.none') }}</p>
    <ul v-else class="list">
      <li v-for="i in accounts.identities" :key="i.id" class="item">
        <div class="info">
          <strong>{{ i.name }}</strong> <span class="addr">&lt;{{ i.address }}&gt;</span>
          <span class="chips">
            <ToneChip v-if="i.isDefault" tone="success" icon="checkCircle" :label="t('identities.default')" />
            <ToneChip v-if="i.primary" tone="neutral" icon="mail" :label="t('identities.primary')" />
            <ToneChip v-if="accounts.mail.length > 1" tone="neutral" icon="inbox" :label="accountLabel(i.accountId)" />
          </span>
          <span v-if="i.replyTo" class="muted line">{{ t('identities.replyToShort', { address: i.replyTo }) }}</span>
          <span class="muted line">{{ i.signature ? firstLines(i.signature) : t('identities.noSignature') }}</span>
        </div>
        <div class="acts">
          <BaseButton icon="edit" :label="t('identities.edit')" @click="openEdit(i)" />
          <BaseButton v-if="!i.isDefault" variant="ghost" :label="t('identities.makeDefault')" @click="makeDefault(i)" />
          <BaseButton v-if="!i.primary" variant="ghost" icon="trash" :label="t('identities.delete')" @click="toDelete = i" />
        </div>
      </li>
    </ul>
  </BaseCard>

  <BaseDialog :open="editing !== null" :title="t(isNew ? 'identities.newTitle' : 'identities.editTitle')" @close="editing = null">
    <form id="identity-form" class="stack" novalidate @submit.prevent="save">
      <TextField id="id-name" v-model="form.name" :label="t('identities.name')" :hint="t('identities.nameHint')" :error="errors.name" required />
      <template v-if="isNew || !primary">
        <TextField id="id-address" v-model="form.address" type="email" :label="t('identities.address')" :hint="t('identities.addressHint')" :error="errors.address" required />
      </template>
      <p v-else class="static"><strong>{{ t('identities.address') }}</strong><br />{{ form.address }} <span class="muted">· {{ t('identities.primaryHint') }}</span></p>
      <SelectField v-if="isNew && accountOptions.length > 1" id="id-account" v-model="form.accountId" :label="t('identities.account')" :options="accountOptions" />
      <TextField id="id-reply" v-model="form.replyTo" type="email" :label="t('identities.replyTo')" :hint="t('identities.replyToHint')" :error="errors.replyTo" />
      <TextareaField id="id-sig" v-model="form.signature" :label="t('identities.signature')" :rows="5" :hint="t('identities.signatureHint')" :error="errors.signature" />
      <div v-if="form.signature.trim()" class="preview" aria-live="polite">
        <strong>{{ t('identities.preview') }}</strong>
        <pre>{{ form.signature }}</pre>
      </div>
    </form>
    <template #footer>
      <BaseButton variant="primary" type="submit" form="identity-form" :disabled="busy" :label="t('common.save')" />
      <BaseButton :label="t('common.cancel')" @click="editing = null" />
    </template>
  </BaseDialog>

  <BaseDialog :open="toDelete !== null" :title="t('identities.deleteTitle', { name: toDelete?.name ?? '' })" @close="toDelete = null">
    <p>{{ t('identities.deleteText') }}</p>
    <template #footer>
      <BaseButton variant="danger" icon="trash" :label="t('identities.delete')" @click="remove" />
      <BaseButton :label="t('common.cancel')" @click="toDelete = null" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.item { display: flex; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; padding: var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); background: var(--c-surface-2); }
.info { display: grid; gap: var(--space-1); min-width: 0; flex: 1 1 280px; }
.addr { color: var(--c-text-muted); overflow-wrap: anywhere; }
.chips { display: flex; gap: var(--space-1); flex-wrap: wrap; }
.line { font-size: var(--fs-sm); overflow-wrap: anywhere; }
.acts { display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: flex-start; }
.static { margin: 0; overflow-wrap: anywhere; }
.preview { display: grid; gap: var(--space-1); padding: var(--space-3); background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.preview pre { margin: 0; white-space: pre-wrap; font: inherit; overflow-wrap: anywhere; }
</style>
