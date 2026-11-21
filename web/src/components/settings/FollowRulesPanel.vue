<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../../api'
import type { FollowRule } from '../../api/types'
import { useAccountsStore } from '../../stores/accounts'
import { useUiStore } from '../../stores/ui'
import BaseButton from '../ui/BaseButton.vue'
import BaseCard from '../ui/BaseCard.vue'
import BaseIcon from '../ui/BaseIcon.vue'
import SelectField from '../ui/SelectField.vue'
import SwitchField from '../ui/SwitchField.vue'
import TextField from '../ui/TextField.vue'

/** Création automatique de suivis (F-66) : chaque règle est visible, désactivable et supprimable. L'IA, elle, suggère et tu valides. */
const { t } = useI18n()
const ui = useUiStore()
const accounts = useAccountsStore()
const rules = ref<FollowRule[]>([])
const form = reactive({ kind: 'domain' as FollowRule['kind'], value: '' })
const error = ref('')
const busy = ref(false)
const kindOptions = computed(() => (['domain', 'sender'] as const).map((k) => ({ value: k, label: t(`settings.rules.kind.${k}`) })))
const boxes = computed(() => accounts.shared)

onMounted(async () => {
  rules.value = await api.settings.followRules.list().catch(() => [])
  if (!accounts.loaded) await accounts.load().catch(() => undefined)
})
async function add() {
  error.value = ''
  busy.value = true
  try {
    rules.value = [...rules.value, await api.settings.followRules.create(form.kind, form.value)]
    form.value = ''
    ui.notify(t('settings.rules.added'))
  } catch (e) { error.value = e instanceof ApiError ? e.message : t('common.actionFailed') } finally { busy.value = false }
}
async function toggle(r: FollowRule, enabled: boolean) {
  const n = await api.settings.followRules.setEnabled(r.id, enabled)
  rules.value = rules.value.map((x) => (x.id === r.id ? n : x))
}
async function remove(r: FollowRule) {
  await api.settings.followRules.remove(r.id)
  rules.value = rules.value.filter((x) => x.id !== r.id)
  ui.notify(t('settings.rules.removed'), { tone: 'info' })
}
const label = (r: FollowRule) => t(`settings.rules.when.${r.kind}`, { value: r.value })
</script>

<template>
  <BaseCard class="stack">
    <h3>{{ t('settings.rules.title') }}</h3>
    <p class="muted">{{ t('settings.rules.help') }}</p>

    <ul v-if="rules.length || boxes.length" class="list">
      <li v-for="b in boxes" :key="b.id" class="rule box">
        <BaseIcon name="inbox" :size="18" />
        <span class="txt">{{ t('settings.rules.alias', { address: b.address }) }}</span>
        <span class="muted">{{ b.autoFollow ? t('settings.rules.on') : t('settings.rules.off') }}</span>
      </li>
      <li v-for="r in rules" :key="r.id" class="rule">
        <SwitchField :model-value="r.enabled" :label="label(r)" @update:model-value="(v: boolean) => toggle(r, v)" />
        <BaseButton variant="ghost" icon="trash" icon-only :label="t('settings.rules.remove', { value: r.value })" @click="remove(r)" />
      </li>
    </ul>
    <p v-else class="muted">{{ t('settings.rules.none') }}</p>

    <form class="add" novalidate @submit.prevent="add">
      <SelectField id="rule-kind" v-model="form.kind" :label="t('settings.rules.kindLabel')" :options="kindOptions" />
      <TextField id="rule-value" v-model="form.value" :label="t('settings.rules.valueLabel')" :placeholder="form.kind === 'domain' ? 'univ-reims.fr' : 'sophie@univ-reims.fr'" :error="error" required />
      <div class="go"><BaseButton type="submit" variant="primary" icon="plus" :disabled="busy || !form.value" :label="t('settings.rules.add')" /></div>
    </form>
    <p class="hint">{{ t('settings.rules.never') }}</p>
  </BaseCard>
</template>

<style scoped>
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-2); }
.rule { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-1) var(--space-3); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.rule.box { background: var(--c-surface-2); min-height: var(--target); }
.txt { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.add { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) auto; gap: var(--space-3); align-items: start; }
.go { padding-top: 28px; }
@media (max-width: 720px) { .add { grid-template-columns: minmax(0, 1fr); } .go { padding-top: 0; } }
h3 { font-size: var(--fs-md); }
</style>
