<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { addFiles, limitBytes, MAX_FILES, sizeLabel } from '../../domain/attachments'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/**
 * Pièces jointes : glisser-déposer de fichiers (n'importe où sur la page de rédaction) ou « Parcourir » pour en choisir un ou plusieurs.
 * La limite est celle du compte d'envoi ; elle est affichée en permanence avec ce qui est déjà joint. Rien n'est lu avant l'envoi.
 */
const props = defineProps<{ limitMb: number | null | undefined }>()
const files = defineModel<File[]>({ required: true })
const { t } = useI18n()

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)
const message = ref('')
const problems = ref<string[]>([])
let depth = 0

const max = computed(() => limitBytes(props.limitMb))
const used = computed(() => files.value.reduce((n, f) => n + f.size, 0))
const pct = computed(() => Math.min(100, Math.round((used.value / max.value) * 100)))
const left = computed(() => Math.max(0, max.value - used.value))
const full = computed(() => used.value >= max.value * 0.9)

function take(list: File[]) {
  const r = addFiles(files.value, list, max.value)
  files.value = r.files
  problems.value = r.refused.map((x) => (x.reason === 'size' ? t('attach.tooBig', { name: x.name, left: sizeLabel(left.value), max: sizeLabel(max.value) }) : x.reason === 'count' ? t('attach.tooMany', { name: x.name, n: MAX_FILES }) : t('attach.empty', { name: x.name })))
  message.value = r.added.length ? t('attach.added', { n: r.added.length }, r.added.length) : ''
}
function pick(e: Event) {
  const el = e.target as HTMLInputElement
  take([...(el.files ?? [])])
  el.value = '' // permet de rechoisir le même fichier
}
const remove = (i: number) => { const f = files.value[i]; files.value = files.value.filter((_, n) => n !== i); problems.value = []; message.value = t('attach.removed', { name: f.name }) }

const hasFiles = (e: DragEvent) => !!e.dataTransfer && [...e.dataTransfer.types].includes('Files')
function enter(e: DragEvent) { if (hasFiles(e)) { depth++; dragging.value = true } }
function over(e: DragEvent) { if (hasFiles(e)) { e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy' } }
function leave(e: DragEvent) { if (hasFiles(e) && --depth <= 0) { depth = 0; dragging.value = false } }
function drop(e: DragEvent) {
  if (!hasFiles(e)) return
  e.preventDefault() // sinon le navigateur ouvre le fichier à la place de la page
  depth = 0
  dragging.value = false
  take([...(e.dataTransfer?.files ?? [])])
}
// Déposer n'importe où sur la page : la zone n'est qu'un repère visuel.
onMounted(() => { document.addEventListener('dragenter', enter); document.addEventListener('dragover', over); document.addEventListener('dragleave', leave); document.addEventListener('drop', drop) })
onBeforeUnmount(() => { document.removeEventListener('dragenter', enter); document.removeEventListener('dragover', over); document.removeEventListener('dragleave', leave); document.removeEventListener('drop', drop) })
</script>

<template>
  <section class="drop" :class="{ over: dragging, full }" :aria-label="t('attach.title')">
    <div class="zone">
      <BaseIcon name="paperclip" :size="22" />
      <div class="txt">
        <strong>{{ dragging ? t('attach.dropNow') : t('attach.drag') }}</strong>
        <span class="muted">{{ t('attach.limit', { max: sizeLabel(max), n: MAX_FILES }) }}</span>
      </div>
      <BaseButton icon="plus" :label="t('attach.browse')" @click="input?.click()" />
      <input ref="input" type="file" multiple class="sr-only" tabindex="-1" :aria-label="t('attach.browse')" @change="pick" />
    </div>

    <div v-if="files.length" class="meter">
      <div class="bar" role="progressbar" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100" :aria-label="t('attach.usage', { used: sizeLabel(used), max: sizeLabel(max) })"><span class="fill" :style="{ width: `${pct}%` }" /></div>
      <p class="usage" :class="{ warn: full }">{{ t('attach.usage', { used: sizeLabel(used), max: sizeLabel(max) }) }} · {{ t('attach.count', { n: files.length }, files.length) }}</p>
    </div>

    <ul v-if="files.length" class="flist">
      <li v-for="(f, i) in files" :key="f.name + f.size + f.lastModified">
        <BaseIcon name="paperclip" :size="16" /><span class="fn">{{ f.name }}</span><span class="muted">{{ sizeLabel(f.size) }}</span>
        <BaseButton variant="ghost" icon="x" icon-only :title="t('compose.removeFile', { name: f.name })" :label="t('compose.removeFile', { name: f.name })" @click="remove(i)" />
      </li>
    </ul>

    <p class="sr-only" role="status" aria-live="polite">{{ message }}</p>
    <ul v-if="problems.length" class="problems" role="alert"><li v-for="p in problems" :key="p">{{ p }}</li></ul>
  </section>
</template>

<style scoped>
.drop { display: grid; gap: var(--space-2); padding: var(--space-3); border: 2px dashed var(--c-border-strong); border-radius: var(--radius-lg); background: var(--c-surface-2); transition: background 0.15s, border-color 0.15s; }
.drop.over { border-color: var(--c-accent); background: var(--c-primary-soft); }
.zone { display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.txt { display: grid; flex: 1 1 200px; min-width: 0; }
.meter { display: grid; gap: 2px; }
.bar { height: 10px; border-radius: 999px; background: var(--c-surface); border: var(--bw) solid var(--c-border); overflow: hidden; }
.fill { display: block; height: 100%; background: var(--c-primary); border-radius: 999px; }
.full .fill { background: var(--c-warn); }
.usage { margin: 0; font-size: var(--fs-sm); }
.usage.warn { font-weight: 700; }
.flist { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
.flist li { display: flex; align-items: center; gap: var(--space-2); min-height: var(--target); }
.fn { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.problems { margin: 0; padding-inline-start: var(--space-5); color: var(--c-danger); font-weight: 600; }
</style>
