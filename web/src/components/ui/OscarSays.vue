<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useOscar } from '../../oscar/useOscar'
import { isError, type OscarTone, type Situation } from '../../oscar/situations'
import OscarMark from '../OscarMark.vue'
import SuggestBadge from './SuggestBadge.vue'

/**
 * Une intervention d'Oscar : toujours reconnaissable (visage, nom, pastille quand il propose),
 * toujours dite par la voix centralisée (`useOscar`), jamais une phrase écrite dans un écran. Le ton léger disparaît seul en cas d'erreur.
 * Aspect : bandeau partagé (.callout, teinte IA) ; en cas d'erreur, teinte danger.
 */
const props = withDefaults(defineProps<{ situation: Situation; params?: Record<string, string | number>; tone?: OscarTone; suggestion?: boolean; ai?: boolean; level?: 2 | 3 }>(), { suggestion: false, ai: false, level: 2 })
const { t } = useI18n()
const { say } = useOscar()
const text = computed(() => say(props.situation, props.params ?? {}, props.tone))
const error = computed(() => isError(props.situation))
</script>

<template>
  <!-- role="group" et non une région : plusieurs interventions peuvent coexister sur une page sans doublonner les repères. -->
  <div class="callout oscar" :data-tone="error ? 'danger' : 'ai'" :role="error ? 'alert' : 'group'" :aria-label="t('oscar.name')">
    <span class="face" aria-hidden="true"><OscarMark /></span>
    <div class="callout-body">
      <component :is="`h${level}`" class="callout-title who">{{ t('oscar.name') }}<SuggestBadge v-if="suggestion && !error" :ai="ai" /></component>
      <p class="line"><slot>{{ text }}</slot></p>
      <slot name="more" />
    </div>
    <div v-if="$slots.actions" class="callout-actions"><slot name="actions" /></div>
  </div>
</template>

<style scoped>
.face { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; flex: none; background: var(--c-surface); color: var(--tone-fg); border: var(--bw) solid var(--tone); box-shadow: var(--glow); }
.who { display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-md); }
.line { font-size: var(--fs-md); }
</style>
