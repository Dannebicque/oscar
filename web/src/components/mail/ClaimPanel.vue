<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import { collisionWith } from '../../domain/claim'
import type { Mail } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import BaseIcon from '../ui/BaseIcon.vue'

/** Boîte partagée (F-67, F-68) : « nouveau / pris par X / traité par X », jamais un responsable désigné d'office. */
const props = defineProps<{ mail: Mail }>()
const emit = defineEmits<{ take: []; release: []; handle: []; reopen: []; takeOver: []; reply: [] }>()
const { t } = useI18n()
const state = computed(() => props.mail.claim?.state ?? 'new')
const mine = computed(() => props.mail.claim?.by === session.name)
const other = computed(() => collisionWith(props.mail, session.name))
</script>

<template>
  <section class="claim" :class="state" :aria-label="t('mail.claim.' + state, { who: mail.claim?.by ?? '' })">
    <BaseIcon :name="state === 'handled' ? 'checkCircle' : state === 'taken' ? 'user' : 'star'" :size="22" />
    <div class="txt">
      <h3 v-if="state === 'new'">{{ t('mail.claim.bannerNew') }}</h3>
      <h3 v-else>{{ t(`mail.claim.${state}`, { who: mail.claim?.by ?? '' }) }}</h3>
      <p v-if="state === 'new'">{{ t('mail.claim.bannerNewText') }}</p>
      <p v-if="mail.composing && mail.composing !== session.name">{{ t('mail.claim.warnComposing', { who: mail.composing }) }}</p>
    </div>
    <div class="actions">
      <template v-if="state === 'new'">
        <BaseButton variant="primary" icon="user" :label="t('mail.claim.take')" @click="emit('take')" />
        <BaseButton icon="reply" :label="t('mail.reply')" @click="emit('reply')" />
      </template>
      <template v-else-if="state === 'taken' && mine">
        <BaseButton icon="reply" :label="t('mail.reply')" @click="emit('reply')" />
        <BaseButton variant="primary" icon="check" :label="t('mail.claim.handle')" @click="emit('handle')" />
        <BaseButton :label="t('mail.claim.release')" @click="emit('release')" />
      </template>
      <template v-else-if="state === 'taken'">
        <BaseButton icon="reply" :label="t('mail.reply')" @click="emit('reply')" />
        <BaseButton variant="primary" :label="t('mail.claim.takeOver')" @click="emit('takeOver')" />
        <BaseButton v-if="other" icon="check" :label="t('mail.claim.handle')" @click="emit('handle')" />
      </template>
      <BaseButton v-else :label="t('mail.claim.reopen')" @click="emit('reopen')" />
    </div>
  </section>
</template>

<style scoped>
.claim { display: flex; gap: var(--space-3); align-items: center; flex-wrap: wrap; padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--c-border); background: var(--c-surface-2); }
.claim.new { background: var(--c-primary-soft); border-color: var(--c-primary); color: var(--c-on-primary-soft); }
.claim.taken { background: var(--c-warn-soft); border-color: var(--c-warn); color: var(--c-warn); }
.claim.handled { background: var(--c-success-soft); border-color: var(--c-success); color: var(--c-success); }
.txt { flex: 1; min-width: 200px; }
.txt p { color: var(--c-text); }
h3 { font-size: var(--fs-md); }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
