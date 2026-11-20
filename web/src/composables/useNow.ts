import { onMounted, onUnmounted, ref } from 'vue'
import { clock } from '../domain/clock'

/** Heure courante réactive, rafraîchie chaque minute (encart « Prochain RDV », signaux de retard). */
export function useNow() {
  const now = ref(clock.now())
  let id: ReturnType<typeof setInterval> | undefined
  onMounted(() => { id = setInterval(() => { now.value = clock.now() }, 60_000) })
  onUnmounted(() => clearInterval(id))
  return now
}
