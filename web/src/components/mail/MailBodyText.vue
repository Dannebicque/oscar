<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { MAIL_SANDBOX, mailDocument } from '../../domain/mailFrame'
import type { Mail } from '../../domain/types'
import { useMailsStore } from '../../stores/mails'
import BaseButton from '../ui/BaseButton.vue'
import Callout from '../ui/Callout.vue'

/** Le corps d'un message : mis en forme dans un cadre isolé quand il l'est, sinon en texte. « Texte seul » reste toujours possible. */
const props = defineProps<{ mail: Mail }>()
const { t } = useI18n()
const mails = useMailsStore()
const textOnly = ref(false)
const showHtml = computed(() => !!props.mail.html && !textOnly.value)
const doc = computed(() => (props.mail.html ? mailDocument(props.mail.html, !!props.mail.remoteImages) : ''))

// Le cadre prend la hauteur de son contenu : un seul défilement, celui du lecteur.
const frame = ref<HTMLIFrameElement | null>(null)
const height = ref(240)
let observer: ResizeObserver | null = null
function fit() {
  const body = frame.value?.contentDocument?.body
  // 20 px de plus : la barre de défilement horizontale des tableaux trop larges.
  if (body?.scrollHeight) height.value = body.scrollHeight + 20
}
function loaded() {
  observer?.disconnect()
  fit()
  const body = frame.value?.contentDocument?.body
  if (body && typeof ResizeObserver === 'function') { observer = new ResizeObserver(fit); observer.observe(body) }
}
onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <Callout v-if="showHtml && mail.blockedImages" tone="neutral" icon="eye">
    <p>{{ t('mail.html.blocked', { n: mail.blockedImages }, mail.blockedImages) }}</p>
    <template #actions><BaseButton :label="t('mail.html.showImages')" :disabled="mails.loadingBody.has(mail.id)" @click="mails.showImages(mail.id)" /></template>
  </Callout>
  <div v-if="mail.html && mail.paragraphs.length" class="switch">
    <BaseButton variant="ghost" :label="t('mail.html.textOnly')" :pressed="textOnly" @click="textOnly = !textOnly" />
  </div>
  <iframe
    v-if="showHtml" ref="frame" class="mail-frame" :sandbox="MAIL_SANDBOX" referrerpolicy="no-referrer"
    :srcdoc="doc" :title="t('mail.html.frameTitle', { subject: mail.subject })" :style="{ height: `${height}px` }" @load="loaded"
  />
  <template v-else><p v-for="(p, i) in mail.paragraphs" :key="i" class="para">{{ p }}</p></template>
</template>

<style scoped>
.switch { justify-self: end; }
.mail-frame { display: block; width: 100%; min-width: 0; border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); }
.para { white-space: pre-line; overflow-wrap: anywhere; margin: 0; }
</style>
