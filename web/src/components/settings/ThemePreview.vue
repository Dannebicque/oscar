<script setup lang="ts">
import { computed } from 'vue'
import { buildTokens, type PaletteId, type ResolvedMode, type StyleId } from '../../themes/engine'

/** Aperçu fidèle d'un thème : mêmes jetons que l'application, appliqués localement (aucun effet sur le reste de la page). */
const props = defineProps<{ style: StyleId; palette: PaletteId; mode: ResolvedMode; customAccent: string }>()
const vars = computed(() => buildTokens({ style: props.style, palette: props.palette, mode: props.mode, customAccent: props.customAccent }))
</script>

<template>
  <div class="tp" :style="vars" :data-style="style" :data-theme="mode" aria-hidden="true">
    <div class="tp-rail"><span class="tp-dot" /><span class="tp-bar on" /><span class="tp-bar" /><span class="tp-bar" /></div>
    <div class="tp-main">
      <div class="tp-card">
        <b>Aa</b>
        <span class="tp-chip">Réponse</span>
        <span class="tp-chip ai">IA</span>
      </div>
      <div class="tp-actions"><span class="tp-btn">Clore</span><span class="tp-btn ghost">Ouvrir</span></div>
    </div>
  </div>
</template>

<style scoped>
.tp { display: flex; width: 100%; height: 96px; overflow: hidden; border: var(--bw) solid var(--c-border-strong); border-radius: var(--radius-md); background-color: var(--c-bg); background-image: var(--bg-image); background-size: var(--bg-size); font-family: var(--font-sans); color: var(--c-text); }
.tp-rail { width: 26px; background: var(--c-surface); border-right: var(--bw) solid var(--c-border); display: grid; align-content: start; gap: 6px; padding: 8px 6px; }
.tp-dot { width: 14px; height: 14px; border-radius: 4px; background: var(--c-primary); }
.tp-bar { height: 5px; border-radius: 3px; background: var(--c-border-strong); opacity: 0.45; }
.tp-bar.on { background: var(--c-accent); opacity: 1; }
.tp-main { flex: 1; display: grid; align-content: center; gap: 8px; padding: 8px 10px; }
.tp-card { display: flex; align-items: center; gap: 6px; padding: 6px 8px; background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-sm); box-shadow: var(--shadow-1); font-size: 12px; }
.tp-chip { padding: 0 7px; font-size: 10px; font-weight: 700; border-radius: var(--radius-pill); background: var(--c-primary-soft); color: var(--c-on-primary-soft); }
.tp-chip.ai { background: var(--c-ai-soft); color: var(--c-ai); }
.tp-actions { display: flex; gap: 6px; }
.tp-btn { padding: 2px 10px; font-size: 10px; font-weight: 700; border-radius: var(--radius-sm); background: var(--c-primary); color: var(--c-on-primary); border: var(--bw) solid var(--c-primary); box-shadow: var(--glow); }
.tp-btn.ghost { background: transparent; color: var(--c-text); border-color: var(--c-border-strong); box-shadow: none; }
</style>
