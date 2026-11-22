<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AccountsPanel from '../components/settings/AccountsPanel.vue'
import IdentitiesPanel from '../components/settings/IdentitiesPanel.vue'
import TemplatesPanel from '../components/settings/TemplatesPanel.vue'
import DirectoryPanel from '../components/settings/DirectoryPanel.vue'
import SortRulesPanel from '../components/settings/SortRulesPanel.vue'
import FollowRulesPanel from '../components/settings/FollowRulesPanel.vue'
import MyAccountPanel from '../components/settings/MyAccountPanel.vue'
import AiProviderPanel from '../components/settings/AiProviderPanel.vue'
import AppearancePanel from '../components/settings/AppearancePanel.vue'
import BaseCard from '../components/ui/BaseCard.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import RadioGroup from '../components/ui/RadioGroup.vue'
import SwitchField from '../components/ui/SwitchField.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import FlagChip from '../components/ui/FlagChip.vue'
import StatusChip from '../components/ui/StatusChip.vue'
import { LOCALES } from '../i18n'
import { DENSITIES, THEMES } from '../themes'
import { useOscar } from '../oscar/useOscar'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { useSettingsStore } from '../stores/settings'
import { useSystemStore } from '../stores/system'

const { t } = useI18n()
const settingsStore = useSettingsStore()
const { s } = settingsStore
const fmt = useFormat()
const now = clock.now()
/** Le dernier jour ne peut pas précéder le premier : sinon le serveur refuserait le réglage. */
const vacationInvalid = computed(() => !!s.vacationFrom && !!s.vacationTo && s.vacationTo < s.vacationFrom)
const system = useSystemStore()
const { say } = useOscar()

const sections = ['myaccount', 'accounts', 'relances', 'recap', 'ai', 'look', 'journal'] as const
const active = ref<string>(sections[0])
let observer: IntersectionObserver | null = null
onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') return
  observer = new IntersectionObserver((entries) => { const hit = entries.find((e) => e.isIntersecting); if (hit) active.value = hit.target.id.replace('set-', '') }, { rootMargin: '-10% 0px -70% 0px' })
  for (const k of sections) { const el = document.getElementById(`set-${k}`); if (el) observer.observe(el) }
})
onBeforeUnmount(() => observer?.disconnect())
const themeOpts = computed(() => THEMES.map((x) => ({ value: x.id, label: t(`settings.look.themes.${x.id}`), icon: x.icon })))
const densityOpts = computed(() => DENSITIES.map((d) => ({ value: d, label: t(`settings.look.densities.${d}`) })))
const langOpts = computed(() => LOCALES.map((l) => ({ value: l, label: l === 'fr' ? 'Français' : 'English' })))
const delayOpts = computed(() => ([24, 48, 72, 168] as const).map((h) => ({ value: h, label: t(`settings.relances.h${h}`) })))
const undoOpts = computed(() => ([0, 5, 10, 20, 30] as const).map((n) => ({ value: n, label: n === 0 ? t('settings.undoSend.off') : t('settings.undoSend.seconds', { n }) })))
const leadOpts = computed(() => ([0, 5, 10, 15, 30] as const).map((n) => ({ value: n, label: n === 0 ? t('settings.meeting.off') : t('settings.meeting.minutes', { n }) })))
const capOpts = [3, 5, 10].map((n) => ({ value: n as 3 | 5 | 10, label: String(n) }))
const toneOpts = computed(() => (['light', 'neutral'] as const).map((k) => ({ value: k, label: t(`oscar.tones.${k}`), help: say('noMeeting', {}, k) })))
const aiOpts = computed(() => (['university', 'external', 'off'] as const).map((m) => ({ value: m, label: t(`settings.ai.${m}`), help: t(`settings.ai.${m}Help`) })))
</script>

<template>
  <PageHeader :title="t('settings.title')" />
  <div class="layout">
    <nav class="toc card" :aria-label="t('settings.sections')">
      <ul><li v-for="k in sections" :key="k"><a :href="`#set-${k}`" :aria-current="active === k ? 'true' : undefined" @click="active = k">{{ t(`settings.nav.${k}`) }}</a></li></ul>
    </nav>

    <div class="sections">
      <section id="set-myaccount" aria-labelledby="h-myaccount">
        <h2 id="h-myaccount">{{ t('settings.nav.myaccount') }}</h2>
        <MyAccountPanel />
      </section>

      <section id="set-accounts" aria-labelledby="h-accounts">
        <h2 id="h-accounts">{{ t('settings.nav.accounts') }}</h2>
        <AccountsPanel />
        <IdentitiesPanel />
        <TemplatesPanel />
        <DirectoryPanel />
        <div class="card stack">
          <SwitchField v-model="s.includePersonalCalendar" :label="t('settings.accounts.includePersonal')" :help="t('settings.accounts.includePersonalHelp')" />
          <SwitchField v-model="s.shareBusy" :label="t('settings.accounts.shareBusy')" :help="t('settings.accounts.shareBusyHelp')" />
        </div>
        <div class="card stack">
          <RadioGroup v-model="s.undoSendSeconds" name="undo" :legend="t('settings.undoSend.legend')" :options="undoOpts" />
          <p class="hint">{{ t('settings.undoSend.help') }}</p>
          <SwitchField v-model="s.confirmDelete" :label="t('settings.confirmDelete')" :help="t('settings.confirmDeleteHelp')" />
        </div>
      </section>

      <section id="set-relances" aria-labelledby="h-relances">
        <h2 id="h-relances">{{ t('settings.nav.relances') }}</h2>
        <div class="card stack">
          <RadioGroup v-model="s.relanceHours" name="delay" :legend="t('settings.relances.delay')" :options="delayOpts" />
          <p class="hint">{{ t('settings.relances.delayHelp') }}</p>
          <SwitchField v-model="s.businessDays" :label="t('settings.relances.businessDays')" :help="t('settings.relances.businessDaysHelp')" />
          <RadioGroup v-model="s.dailyCap" name="cap" :legend="t('settings.relances.cap')" :options="capOpts" />
          <p class="callout" data-tone="neutral">{{ t('settings.relances.never') }}</p>
        </div>
        <div class="card stack">
          <h3>{{ t('vacation.title') }}</h3>
          <p class="hint">{{ t('vacation.help') }}</p>
          <div class="two">
            <div class="field"><label for="vf">{{ t('vacation.from') }}</label><input id="vf" v-model="s.vacationFrom" type="date" class="input" /></div>
            <div class="field"><label for="vt">{{ t('vacation.to') }}</label><input id="vt" v-model="s.vacationTo" type="date" class="input" :min="s.vacationFrom || undefined" /></div>
          </div>
          <p v-if="vacationInvalid" class="error-text" role="alert">{{ t('vacation.invalid') }}</p>
          <p v-else-if="s.vacationFrom && s.vacationTo" class="hint" aria-live="polite">{{ settingsStore.vacationActive(now) ? t('vacation.active', { to: fmt.dateLong(s.vacationTo) }) : t('vacation.planned', { from: fmt.dateLong(s.vacationFrom), to: fmt.dateLong(s.vacationTo) }) }}</p>
          <div class="field"><label for="vm">{{ t('vacation.message') }}</label><textarea id="vm" v-model="s.vacationMessage" class="input" rows="5" :placeholder="t('vacation.defaultMessage', { from: '…', to: '…' })" /></div>
          <p class="hint">{{ t('vacation.messageHelp') }}</p>
          <BaseButton v-if="s.vacationFrom || s.vacationTo" :label="t('vacation.stop')" @click="settingsStore.stopVacation()" />
        </div>
        <FollowRulesPanel />
        <SortRulesPanel />
      </section>

      <section id="set-recap" aria-labelledby="h-recap">
        <h2 id="h-recap">{{ t('settings.nav.recap') }}</h2>
        <div class="card stack">
          <div class="two">
            <div class="field"><label for="rm">{{ t('settings.recap.morning') }}</label><input id="rm" v-model="s.recapMorning" type="time" class="input" /></div>
            <div class="field"><label for="re">{{ t('settings.recap.evening') }}</label><input id="re" v-model="s.recapEvening" type="time" class="input" /></div>
          </div>
          <SwitchField v-model="s.recapEmail" :label="t('settings.recap.email')" :help="t('settings.recap.emailHelp')" />
          <SwitchField v-model="s.recapHome" :label="t('settings.recap.home')" :help="t('settings.recap.homeHelp')" />
          <RouterLink to="/recap">{{ t('settings.recap.example') }}</RouterLink>
        </div>
        <div class="card stack">
          <RadioGroup v-model="s.meetingLeadMinutes" name="lead" :legend="t('settings.meeting.legend')" :options="leadOpts" />
          <p class="hint">{{ t('settings.meeting.help') }}</p>
        </div>
      </section>

      <section id="set-ai" aria-labelledby="h-ai">
        <h2 id="h-ai">{{ t('settings.nav.ai') }} <AiBadge /></h2>
        <div class="card stack">
          <RadioGroup v-model="s.aiMode" name="ai" :legend="t('settings.ai.mode')" :options="aiOpts" />
          <p v-if="s.aiMode === 'external'" class="notice warn" role="status">{{ t('settings.ai.externalNote') }}</p>
          <p class="hint">{{ t('settings.ai.calendarNote') }}</p>
          <h3>{{ t('settings.ai.canDo') }}</h3>
          <SwitchField v-model="s.aiSuggestFollow" :label="t('settings.ai.suggestFollow')" :help="t('settings.ai.suggestFollowHelp')" />
          <SwitchField v-model="s.aiSuggestTasks" :label="t('settings.ai.suggestTasks')" :help="t('settings.ai.suggestTasksHelp')" />
          <SwitchField v-model="s.aiDraftRelance" :label="t('settings.ai.draftRelance')" :help="t('settings.ai.draftRelanceHelp')" />
          <p class="callout" data-tone="neutral">{{ t('settings.ai.never') }}</p>
        </div>
        <AiProviderPanel v-if="s.aiMode === 'external'" />
      </section>

      <section id="set-look" aria-labelledby="h-look">
        <h2 id="h-look">{{ t('settings.nav.look') }}</h2>
        <BaseCard><AppearancePanel /></BaseCard>
      </section>

      <section id="set-journal" aria-labelledby="h-journal">
        <h2 id="h-journal">{{ t('settings.nav.journal') }}</h2>
        <div class="card stack">
          <ul class="conns"><li v-for="c in system.connections" :key="c.id"><ToneChip :tone="c.state === 'error' ? 'danger' : 'success'" :icon="c.state === 'error' ? 'alert' : 'checkCircle'" :label="`${t(`status.${c.id}`)} : ${t(`status.${c.state}`)}`" /></li></ul>
          <RouterLink to="/journal">{{ t('settings.journalLink') }}</RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: var(--space-5); align-items: start; }
.toc { position: sticky; top: 0; padding: var(--space-2); }
.toc ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
.toc a { display: flex; align-items: center; min-height: var(--target); padding: 0 var(--space-3); border-radius: var(--radius-md); text-decoration: none; color: var(--c-text); }
.toc a:hover { background: var(--c-surface-2); }
.toc a[aria-current='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; }
.sections { display: grid; gap: var(--space-6); min-width: 0; }
section { display: grid; gap: var(--space-3); scroll-margin-top: var(--space-4); }
h2 { font-size: var(--fs-lg); display: flex; gap: var(--space-2); align-items: center; }
h3 { font-size: var(--fs-md); }
.card > :deep(*) { max-width: 100%; }
.head, .line { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
.chips, .conns { display: flex; gap: var(--space-2); flex-wrap: wrap; }
@media (max-width: 900px) { .layout { grid-template-columns: minmax(0, 1fr); } .toc { position: static; } .toc ul { grid-auto-flow: column; overflow-x: auto; } }
</style>
