import { useI18n } from 'vue-i18n'
import { parseDay } from '../domain/dates'
import type { JournalEntry } from '../domain/types'

/** Formats de date et libellés du journal, dans la langue courante. */
export function useFormat() {
  const { t, locale } = useI18n()
  const asDate = (v: string | Date) => (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) ? parseDay(v) : new Date(v))

  const date = (v: string | Date) => asDate(v).toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric', month: 'short' })
  const dateLong = (v: string | Date) => asDate(v).toLocaleDateString(locale.value, { weekday: 'long', day: 'numeric', month: 'long' })
  const time = (v: string | Date) => asDate(v).toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })
  const dateTime = (v: string | Date) => `${date(v)}, ${time(v)}`

  /** « 2 h », « 3 min » : délai lisible avant un instant. */
  function delay(target: string | Date, now = new Date()): string {
    const mins = Math.max(0, Math.round((asDate(target).getTime() - now.getTime()) / 60_000))
    if (mins < 60) return `${mins} min`
    const h = Math.floor(mins / 60)
    return mins % 60 === 0 ? `${h} h` : `${h} h ${mins % 60}`
  }

  function journal(e: JournalEntry): string {
    const [action, arg] = e.action.split(':')
    const key = `mail.activity.${action}`
    if (action === 'status') return t(key, { status: t(`states.${arg}`) })
    return t(key, { who: arg ?? '' })
  }

  return { date, dateLong, time, dateTime, delay, journal }
}
