<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
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
import { useSettingsStore } from '../stores/settings'
import { useSystemStore } from '../stores/system'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const { s } = useSettingsStore()
const system = useSystemStore()
const ui = useUiStore()

const sections = ['accounts', 'relances', 'recap', 'ai', 'look', 'journal'] as const
const themeOpts = computed(() => THEMES.map((x) => ({ value: x.id, label: t(`settings.look.themes.${x.id}`), icon: x.icon })))
const densityOpts = computed(() => DENSITIES.map((d) => ({ value: d, label: t(`settings.look.densities.${d}`) })))
const langOpts = computed(() => LOCALES.map((l) => ({ value: l, label: l === 'fr' ? 'Français' : 'English' })))
const delayOpts = computed(() => ([24, 48, 72, 168] as const).map((h) => ({ value: h, label: t(`settings.relances.h${h}`) })))
const capOpts = [3, 5, 10].map((n) => ({ value: n as 3 | 5 | 10, label: String(n) }))
const aiOpts = computed(() => (['university', 'external', 'off'] as const).map((m) => ({ value: m, label: t(`settings.ai.${m}`), help: t(`settings.ai.${m}Help`) })))
const connState = (id: string) => system.connections.find((c) => c.id === id)?.state

function testMail() {
  ui.notify(t('settings.accounts.testOk'))
}
</script>

<template>
  <PageHeader :title="t('settings.title')" />
  <div class="layout">
    <nav class="toc" :aria-label="t('settings.sections')">
      <ul><li v-for="k in sections" :key="k"><a :href="`#set-${k}`">{{ t(`settings.nav.${k}`) }}</a></li></ul>
    </nav>

    <div class="sections">
      <section id="set-accounts" aria-labelledby="h-accounts">
        <h2 id="h-accounts">{{ t('settings.nav.accounts') }}</h2>
        <div class="card">
          <div class="head"><h3>{{ t('settings.accounts.mailTitle') }}</h3><ToneChip :tone="connState('mail') === 'ok' ? 'success' : 'danger'" :icon="connState('mail') === 'ok' ? 'checkCircle' : 'alert'" :label="t(`status.${connState('mail') ?? 'ok'}`)" /></div>
          <p class="muted">{{ t('settings.accounts.mailHelp') }}</p>
          <BaseButton icon="refresh" :label="t('settings.accounts.test')" @click="testMail" />
        </div>
        <div class="card">
          <div class="head"><h3>{{ t('settings.accounts.sharedTitle') }}</h3><ToneChip tone="info" icon="users" :label="t('settings.accounts.alias')" /></div>
          <p class="muted">{{ t('settings.accounts.sharedHelp') }}</p>
        </div>
        <div class="card">
          <h3>{{ t('settings.accounts.calendars') }}</h3>
          <p class="line"><strong>{{ t('settings.accounts.caldav') }}</strong> <ToneChip tone="success" icon="checkCircle" :label="t('status.ok')" /></p>
          <p class="muted">{{ t('settings.accounts.caldavHelp') }}</p>
          <p class="line"><strong>{{ t('settings.accounts.google') }}</strong> <ToneChip :tone="connState('google') === 'error' ? 'danger' : 'success'" :icon="connState('google') === 'error' ? 'alert' : 'checkCircle'" :label="t(`status.${connState('google') ?? 'ok'}`)" /></p>
          <p class="muted">{{ t('settings.accounts.googleHelp') }}</p>
          <SwitchField v-model="s.includePersonalCalendar" :label="t('settings.accounts.includePersonal')" :help="t('settings.accounts.includePersonalHelp')" />
          <SwitchField v-model="s.shareBusy" :label="t('settings.accounts.shareBusy')" :help="t('settings.accounts.shareBusyHelp')" />
        </div>
      </section>

      <section id="set-relances" aria-labelledby="h-relances">
        <h2 id="h-relances">{{ t('settings.nav.relances') }}</h2>
        <div class="card">
          <RadioGroup v-model="s.relanceHours" name="delay" :legend="t('settings.relances.delay')" :options="delayOpts" />
          <p class="hint">{{ t('settings.relances.delayHelp') }}</p>
          <SwitchField v-model="s.businessDays" :label="t('settings.relances.businessDays')" :help="t('settings.relances.businessDaysHelp')" />
          <RadioGroup v-model="s.dailyCap" name="cap" :legend="t('settings.relances.cap')" :options="capOpts" />
          <p class="notice">{{ t('settings.relances.never') }}</p>
        </div>
      </section>

      <section id="set-recap" aria-labelledby="h-recap">
        <h2 id="h-recap">{{ t('settings.nav.recap') }}</h2>
        <div class="card">
          <div class="two">
            <div class="field"><label for="rm">{{ t('settings.recap.morning') }}</label><input id="rm" v-model="s.recapMorning" type="time" class="input" /></div>
            <div class="field"><label for="re">{{ t('settings.recap.evening') }}</label><input id="re" v-model="s.recapEvening" type="time" class="input" /></div>
          </div>
          <SwitchField v-model="s.recapEmail" :label="t('settings.recap.email')" :help="t('settings.recap.emailHelp')" />
          <SwitchField v-model="s.recapHome" :label="t('settings.recap.home')" :help="t('settings.recap.homeHelp')" />
          <RouterLink to="/recap">{{ t('settings.recap.example') }}</RouterLink>
        </div>
      </section>

      <section id="set-ai" aria-labelledby="h-ai">
        <h2 id="h-ai">{{ t('settings.nav.ai') }} <AiBadge /></h2>
        <div class="card">
          <RadioGroup v-model="s.aiMode" name="ai" :legend="t('settings.ai.mode')" :options="aiOpts" />
          <p v-if="s.aiMode === 'external'" class="notice warn" role="status">{{ t('settings.ai.externalNote') }}</p>
          <p class="hint">{{ t('settings.ai.calendarNote') }}</p>
          <h3>{{ t('settings.ai.canDo') }}</h3>
          <SwitchField v-model="s.aiSuggestFollow" :label="t('settings.ai.suggestFollow')" :help="t('settings.ai.suggestFollowHelp')" />
          <SwitchField v-model="s.aiSuggestTasks" :label="t('settings.ai.suggestTasks')" :help="t('settings.ai.suggestTasksHelp')" />
          <SwitchField v-model="s.aiDraftRelance" :label="t('settings.ai.draftRelance')" :help="t('settings.ai.draftRelanceHelp')" />
          <p class="notice">{{ t('settings.ai.never') }}</p>
        </div>
      </section>

      <section id="set-look" aria-labelledby="h-look">
        <h2 id="h-look">{{ t('settings.nav.look') }}</h2>
        <div class="card">
          <RadioGroup v-model="s.theme" name="theme" :legend="t('settings.look.theme')" :options="themeOpts" />
          <p class="hint">{{ t('settings.look.themeHelp') }}</p>
          <RadioGroup v-model="s.density" name="density" :legend="t('settings.look.density')" :options="densityOpts" />
          <RadioGroup v-model="s.locale" name="locale" :legend="t('settings.look.language')" :options="langOpts" />
          <p class="hint">{{ t('settings.look.languageHelp') }}</p>
          <div class="preview" role="group" :aria-label="t('settings.look.preview')">
            <p>{{ t('settings.look.previewText') }}</p>
            <p class="chips"><StatusChip status="inProgress" /><FlagChip flag="relanceDue" /><FlagChip flag="late" /><AiBadge /><StatusChip status="done" /></p>
          </div>
        </div>
      </section>

      <section id="set-journal" aria-labelledby="h-journal">
        <h2 id="h-journal">{{ t('settings.nav.journal') }}</h2>
        <div class="card">
          <ul class="conns"><li v-for="c in system.connections" :key="c.id"><ToneChip :tone="c.state === 'error' ? 'danger' : 'success'" :icon="c.state === 'error' ? 'alert' : 'checkCircle'" :label="`${t(`status.${c.id}`)} : ${t(`status.${c.state}`)}`" /></li></ul>
          <RouterLink to="/journal">{{ t('settings.journalLink') }}</RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: var(--space-5); align-items: start; }
.toc { position: sticky; top: var(--space-4); }
.toc ul { display: grid; gap: 2px; }
.toc a { display: flex; align-items: center; min-height: var(--target); padding: 0 var(--space-3); border-radius: var(--radius-sm); text-decoration: none; color: var(--c-text); }
.toc a:hover { background: var(--c-surface-2); }
.sections { display: grid; gap: var(--space-6); max-width: 760px; }
section { display: grid; gap: var(--space-3); scroll-margin-top: var(--space-4); }
h2 { font-size: var(--fs-lg); display: flex; gap: var(--space-2); align-items: center; }
h3 { font-size: var(--fs-md); }
.card { display: grid; gap: var(--space-3); padding: var(--space-4) var(--space-5); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-lg); justify-items: start; }
.card > :deep(*) { max-width: 100%; }
.head, .line { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
.notice { padding: var(--space-3) var(--space-4); border-radius: var(--radius-sm); background: var(--c-surface-2); font-size: var(--fs-sm); }
.notice.warn { background: var(--c-warn-soft); color: var(--c-text); border: 1px solid var(--c-warn); }
.preview { padding: var(--space-3) var(--space-4); border: 1px dashed var(--c-border); border-radius: var(--radius-md); display: grid; gap: var(--space-2); }
.chips, .conns { display: flex; gap: var(--space-2); flex-wrap: wrap; }
@media (max-width: 900px) { .layout { grid-template-columns: minmax(0, 1fr); } .toc { position: static; } .toc ul { grid-auto-flow: column; overflow-x: auto; } }
</style>
