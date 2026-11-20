/** Date locale AAAA-MM-JJ (jamais d'UTC : une échéance est un jour civil). */
export function isoDay(d: Date): string {
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}
export function addDays(d: Date, n: number): Date {
  const c = new Date(d)
  c.setDate(c.getDate() + n)
  return c
}
export function atTime(d: Date, h: number, m = 0): Date {
  const c = new Date(d)
  c.setHours(h, m, 0, 0)
  return c
}
/** Lundi de la semaine de d. */
export function startOfWeek(d: Date): Date {
  const c = new Date(d)
  c.setHours(0, 0, 0, 0)
  c.setDate(c.getDate() - ((c.getDay() + 6) % 7))
  return c
}
export function sameDay(a: Date, b: Date): boolean {
  return isoDay(a) === isoDay(b)
}
/** Interprète AAAA-MM-JJ comme minuit local. */
export function parseDay(s: string): Date {
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}
export function daysBetween(fromDay: string, toDay: string): number {
  return Math.round((parseDay(toDay).getTime() - parseDay(fromDay).getTime()) / 86_400_000)
}
