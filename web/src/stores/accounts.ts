import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import type { CalendarAccount, MailAccount, NewCalendarAccount, NewMailAccount, SharedMailboxInfo } from '../domain/types'

/** Comptes de messagerie, agendas et boîtes partagées de l'utilisateur. Les mots de passe ne reviennent jamais de l'API. */
export const useAccountsStore = defineStore('accounts', () => {
  const mail = ref<MailAccount[]>([])
  const calendar = ref<CalendarAccount[]>([])
  const shared = ref<SharedMailboxInfo[]>([])
  const loaded = ref(false)

  async function load() {
    try {
      ;[mail.value, calendar.value, shared.value] = await Promise.all([api.accounts.mail.list(), api.accounts.calendar.list(), api.accounts.shared.list()])
    } finally {
      loaded.value = true
    }
  }
  async function addMail(a: NewMailAccount) { mail.value = [...mail.value, await api.accounts.mail.create(a)] }
  async function removeMail(id: string) { await api.accounts.mail.remove(id); mail.value = mail.value.filter((a) => a.id !== id) }
  const testMail = (id: string) => api.accounts.mail.test(id)
  async function addCalendar(a: NewCalendarAccount) { calendar.value = [...calendar.value, await api.accounts.calendar.create(a)] }
  async function removeCalendar(id: string) { await api.accounts.calendar.remove(id); calendar.value = calendar.value.filter((a) => a.id !== id) }

  return { mail, calendar, shared, loaded, load, addMail, removeMail, testMail, addCalendar, removeCalendar }
})
