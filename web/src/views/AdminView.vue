<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../api'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseCard from '../components/ui/BaseCard.vue'
import Callout from '../components/ui/Callout.vue'
import AdminAudit from '../components/admin/AdminAudit.vue'
import AdminHealth from '../components/admin/AdminHealth.vue'
import AdminUsers from '../components/admin/AdminUsers.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import RadioGroup from '../components/ui/RadioGroup.vue'
import SwitchField from '../components/ui/SwitchField.vue'
import TextField from '../components/ui/TextField.vue'
import type { InstanceConfig } from '../domain/types'
import { useInstanceStore } from '../stores/instance'
import { useUiStore } from '../stores/ui'

/**
 * Administration de l'instance : réglages globaux. Réservé à l'administrateur.
 * Il gère l'outil, jamais le contenu : aucun mail, aucun agenda, aucune tâche d'un utilisateur n'est lisible d'ici.
 */
const { t } = useI18n()
const ui = useUiStore()
const instance = useInstanceStore()
type Tab = 'settings' | 'users' | 'health' | 'audit'
const tab = ref<Tab>('settings')
const tabs = computed(() => (['settings', 'users', 'health', 'audit'] as Tab[]).map((v) => ({ value: v, label: t(`admin.tabs.${v}`) })))
const loaded = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const form = reactive<InstanceConfig & { mb: string }>({ instanceName: '', registration: 'closed', supportEmail: '', legalUrl: '', privacyUrl: '', defaultAttachmentMb: 10, aiExternalAllowed: true, setupDone: true, mb: '10' })

const regOptions = computed(() => (['closed', 'open'] as const).map((v) => ({ value: v, label: t(`setup.registration.${v}`) })))

onMounted(async () => {
  try {
    const c = await api.instance.config()
    Object.assign(form, c, { mb: String(c.defaultAttachmentMb) })
  } catch { ui.notify(t('common.actionFailed'), { tone: 'error' }) }
  loaded.value = true
})

async function save() {
  errors.value = {}
  const mb = Number(form.mb)
  busy.value = true
  try {
    const c = await api.instance.updateConfig({
      instanceName: form.instanceName.trim(), registration: form.registration, supportEmail: form.supportEmail.trim(), legalUrl: form.legalUrl.trim(), privacyUrl: form.privacyUrl.trim(),
      defaultAttachmentMb: Number.isInteger(mb) ? mb : -1, aiExternalAllowed: form.aiExternalAllowed,
    })
    Object.assign(form, c, { mb: String(c.defaultAttachmentMb) })
    await instance.load() // le nom et les liens se mettent à jour partout
    ui.notify(t('admin.saved'))
  } catch (e) {
    const fields = (e instanceof ApiError ? (e.data as { fields?: Record<string, string> }).fields : undefined) ?? {}
    if (Object.keys(fields).length) errors.value = { ...fields, mb: fields.defaultAttachmentMb ?? '' }
    else ui.notify(e instanceof Error && e.message ? e.message : t('common.actionFailed'), { tone: 'error' })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PageHeader :title="t('admin.title')" :subtitle="t('admin.subtitle')" />
  <Callout tone="info" icon="lock" :level="2" :title="t('admin.promiseTitle')"><p>{{ t('admin.promise') }}</p></Callout>

  <div class="tabs"><SegmentedControl v-model="tab" :options="tabs" :label="t('admin.tabsLabel')" /></div>
  <AdminUsers v-if="tab === 'users'" class="admin" />
  <AdminHealth v-else-if="tab === 'health'" class="admin" />
  <AdminAudit v-else-if="tab === 'audit'" class="admin" />
  <form v-else-if="loaded" class="admin" novalidate @submit.prevent="save">
    <BaseCard class="stack" aria-labelledby="adm-id">
      <h2 id="adm-id">{{ t('admin.identity') }}</h2>
      <TextField id="a-name" v-model="form.instanceName" :label="t('setup.instanceName')" :hint="t('admin.nameHint')" :error="errors.instanceName" required />
      <TextField id="a-support" v-model="form.supportEmail" type="email" :label="t('setup.supportEmail')" :hint="t('setup.supportHint')" :error="errors.supportEmail" />
      <TextField id="a-legal" v-model="form.legalUrl" type="url" :label="t('admin.legalUrl')" :hint="t('admin.urlHint')" :error="errors.legalUrl" />
      <TextField id="a-privacy" v-model="form.privacyUrl" type="url" :label="t('admin.privacyUrl')" :hint="t('admin.urlHint')" :error="errors.privacyUrl" />
    </BaseCard>

    <BaseCard class="stack" aria-labelledby="adm-acc">
      <h2 id="adm-acc">{{ t('admin.access') }}</h2>
      <RadioGroup v-model="form.registration" name="a-reg" :legend="t('setup.registrationLegend')" :options="regOptions" />
      <p class="hint">{{ t('setup.registrationHint') }}</p>
    </BaseCard>

    <BaseCard class="stack" aria-labelledby="adm-lim">
      <h2 id="adm-lim">{{ t('admin.limits') }}</h2>
      <TextField id="a-mb" v-model="form.mb" type="number" min="1" max="25" :label="t('admin.defaultMb')" :hint="t('admin.defaultMbHint')" :error="errors.mb" />
      <SwitchField v-model="form.aiExternalAllowed" :label="t('admin.aiExternal')" :help="t('admin.aiExternalHelp')" />
    </BaseCard>

    <div><BaseButton type="submit" variant="primary" :disabled="busy" :label="t('common.save')" /></div>
  </form>
</template>

<style scoped>
.tabs { margin-top: var(--space-4); }
.admin { display: grid; gap: var(--space-4); max-width: 820px; margin-top: var(--space-4); }
h2 { font-size: var(--fs-md); margin: 0; }
</style>
