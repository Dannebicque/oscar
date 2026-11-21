<script setup lang="ts">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { contrast, isHex } from '../../themes/color'
import { PALETTES, buildTokens } from '../../themes/engine'
import { DENSITIES, PALETTE_IDS, STYLES, THEMES, resolveTheme, type PaletteId, type StyleId } from '../../themes'
import { LOCALES } from '../../i18n'
import { useSettingsStore } from '../../stores/settings'
import BaseButton from '../ui/BaseButton.vue'
import RadioGroup from '../ui/RadioGroup.vue'
import StatusChip from '../ui/StatusChip.vue'
import SwitchField from '../ui/SwitchField.vue'
import FlagChip from '../ui/FlagChip.vue'
import AiBadge from '../ui/AiBadge.vue'
import ThemePreview from './ThemePreview.vue'

/** Apparence : style (Moderne, Rétro, Néon) × palette × mode, accent libre corrigé pour rester lisible, densité, langue. */
const { t } = useI18n()
const { s } = useSettingsStore()
const prefersDark = typeof matchMedia === 'function' && matchMedia('(prefers-color-scheme: dark)').matches
const mode = computed(() => resolveTheme(s.theme, prefersDark))

const modeOpts = computed(() => THEMES.map((x) => ({ value: x.id, label: t(`settings.look.themes.${x.id}`), icon: x.icon })))
const densityOpts = computed(() => DENSITIES.map((d) => ({ value: d, label: t(`settings.look.densities.${d}`) })))
const langOpts = computed(() => LOCALES.map((l) => ({ value: l, label: l === 'fr' ? 'Français' : 'English' })))

const swatch = (p: PaletteId) => (p === 'custom' ? s.customAccent : PALETTES[p as keyof typeof PALETTES][mode.value === 'light' ? 'light' : 'dark'])
/** Ce que l'accent choisi devient réellement une fois corrigé pour le contraste. */
const effective = computed(() => buildTokens({ style: s.style, palette: 'custom', mode: mode.value, customAccent: s.customAccent })['--c-primary'])
const adjusted = computed(() => isHex(s.customAccent) && effective.value.toLowerCase() !== s.customAccent.toLowerCase())
const ratio = computed(() => contrast(effective.value, buildTokens({ style: s.style, palette: 'custom', mode: mode.value, customAccent: s.customAccent })['--c-surface']).toFixed(1))

function pickStyle(v: unknown) { if (typeof v === 'string') s.style = v as StyleId }
function pickPalette(v: unknown) { if (typeof v === 'string') s.palette = v as PaletteId }
function onHex(e: Event) {
  const v = (e.target as HTMLInputElement).value.trim()
  const hex = v.startsWith('#') ? v : `#${v}`
  if (isHex(hex)) { s.customAccent = hex.toLowerCase(); s.palette = 'custom' }
}
</script>

<template>
  <div class="ap">
    <section aria-labelledby="ap-style">
      <h3 id="ap-style">{{ t('settings.look.style') }}</h3>
      <p class="hint">{{ t('settings.look.styleHelp') }}</p>
      <RadioGroupRoot class="styles" :model-value="s.style" :aria-labelledby="'ap-style'" @update:model-value="pickStyle">
        <label v-for="st in STYLES" :key="st.id" class="style" :class="{ on: s.style === st.id }">
          <RadioGroupItem class="sr-radio" :value="st.id" :aria-label="t(`settings.look.styles.${st.id}`)" />
          <ThemePreview :style="st.id" :palette="s.palette" :mode="mode" :custom-accent="s.customAccent" />
          <strong>{{ t(`settings.look.styles.${st.id}`) }}</strong>
          <span class="muted">{{ t(`settings.look.styleDesc.${st.id}`) }}</span>
        </label>
      </RadioGroupRoot>
    </section>

    <section aria-labelledby="ap-pal">
      <h3 id="ap-pal">{{ t('settings.look.palette') }}</h3>
      <RadioGroupRoot class="palettes" :model-value="s.palette" :aria-labelledby="'ap-pal'" @update:model-value="pickPalette">
        <label v-for="p in PALETTE_IDS" :key="p" class="pal" :class="{ on: s.palette === p }">
          <RadioGroupItem class="sr-radio" :value="p" :aria-label="t(`settings.look.palettes.${p}`)" />
          <span class="sw" :style="{ background: swatch(p) }" />
          <span>{{ t(`settings.look.palettes.${p}`) }}</span>
        </label>
      </RadioGroupRoot>
      <div v-if="s.palette === 'custom'" class="custom">
        <div class="field">
          <label for="ap-color">{{ t('settings.look.customColor') }}</label>
          <div class="cluster">
            <input id="ap-color" type="color" class="color" :value="s.customAccent" @input="onHex" />
            <input :aria-label="t('settings.look.customHex')" class="input hex" :value="s.customAccent" maxlength="7" spellcheck="false" @change="onHex" />
          </div>
        </div>
        <p class="hint" role="status">{{ adjusted ? t('settings.look.adjusted', { color: effective, ratio }) : t('settings.look.readable', { ratio }) }}</p>
      </div>
    </section>

    <RadioGroup v-model="s.theme" name="theme" :legend="t('settings.look.theme')" :options="modeOpts" />
    <RadioGroup v-model="s.density" name="density" :legend="t('settings.look.density')" :options="densityOpts" />
    <RadioGroup v-model="s.locale" name="locale" :legend="t('settings.look.language')" :options="langOpts" />

    <SwitchField v-model="s.shortcuts" :label="t('settings.look.shortcuts')" :help="t('settings.look.shortcutsHelp')" />

    <div class="preview" role="group" :aria-label="t('settings.look.preview')">
      <p>{{ t('settings.look.previewText') }}</p>
      <p class="cluster"><StatusChip status="inProgress" /><FlagChip flag="relanceDue" /><FlagChip flag="late" /><AiBadge /><StatusChip status="done" /></p>
      <p class="cluster"><BaseButton variant="primary" :label="t('settings.look.sampleAction')" /><BaseButton :label="t('settings.look.sampleSecondary')" /></p>
    </div>
  </div>
</template>

<style scoped>
.ap { display: grid; gap: var(--space-5); width: 100%; }
h3 { margin-bottom: var(--space-1); }
.styles { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3); margin-top: var(--space-3); }
.style, .pal { position: relative; display: grid; gap: var(--space-2); padding: var(--space-3); border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-md); background: var(--c-surface); cursor: pointer; }
.style.on, .pal.on { border-color: var(--c-accent); box-shadow: 0 0 0 2px var(--c-accent); }
.style:has(.sr-radio:focus-visible), .pal:has(.sr-radio:focus-visible) { outline: 3px solid var(--c-focus); outline-offset: 3px; }
.sr-radio { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.palettes { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-2); }
.pal { grid-auto-flow: column; align-items: center; min-height: var(--target); padding: var(--space-2) var(--space-4) var(--space-2) var(--space-3); border-radius: var(--radius-pill); }
.sw { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--c-surface); box-shadow: 0 0 0 1px var(--c-border-strong); }
.custom { display: grid; gap: var(--space-2); margin-top: var(--space-3); }
.color { width: var(--target); height: var(--target); padding: 2px; border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-sm); background: var(--c-surface); }
.hex { width: 9ch; font-family: ui-monospace, monospace; }
.preview { display: grid; gap: var(--space-2); padding: var(--space-3) var(--space-4); border: 1px dashed var(--c-border-strong); border-radius: var(--radius-md); }
</style>
