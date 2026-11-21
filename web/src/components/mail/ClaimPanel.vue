<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { session } from '../../api'
import { collisionWith } from '../../domain/claim'
import type { Mail } from '../../domain/types'
import BaseButton from '../ui/BaseButton.vue'
import Callout from '../ui/Callout.vue'

/** Boîte partagée (F-67, F-68) : « nouveau / pris par X / traité par X », jamais un responsable désigné d'office. */
const props = defineProps<{ mail: Mail }>()
const emit = defineEmits<{ take: []; release: []; handle: []; reopen: []; takeOver: []; reply: [] }>()
const { t } = useI18n()
const state = computed(() => props.mail.claim?.state ?? 'new')
const mine = computed(() => props.mail.claim?.by === session.name)
const other = computed(() => collisionWith(props.mail, session.name))
const tone = computed(() => (state.value === 'handled' ? 'success' : state.value === 'taken' ? 'warn' : 'info'))
const icon = computed(() => (state.value === 'handled' ? 'checkCircle' : state.value === 'taken' ? 'user' : 'star'))
const title = computed(() => (state.value === 'new' ? t('mail.claim.bannerNew') : t(`mail.claim.${state.value}`, { who: props.mail.claim?.by ?? '' })))
</script>

<template>
  <Callout :tone="tone" :icon="icon" :title="title" :aria-label="t('mail.claim.' + state, { who: mail.claim?.by ?? '' })">
    <p v-if="state === 'new'">{{ t('mail.claim.bannerNewText') }}</p>
    <p v-if="mail.composing && mail.composing !== session.name">{{ t('mail.claim.warnComposing', { who: mail.composing }) }}</p>
    <template #actions>
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
    </template>
  </Callout>
</template>
