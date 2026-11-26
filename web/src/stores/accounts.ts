import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import type { CalendarAccount, Identity, IdentityInput, MailAccount, NewCalendarAccount, NewMailAccount, SharedMailboxInfo } from '../domain/types'

/** Comptes de messagerie, agendas et boîtes partagées de l'utilisateur. Les mots de passe ne reviennent jamais de l'API. */
export const useAccountsStore = defineStore('accounts', () => {
  const mail = ref<MailAccount[]>([])
  const calendar = ref<CalendarAccount[]>([])
  /** Boîtes dont on est membre. Les invitations en attente sont à part : on ne peut pas encore s'en servir. */
  const shared = ref<SharedMailboxInfo[]>([])
  const invitations = ref<SharedMailboxInfo[]>([])
  const setShared = (all: SharedMailboxInfo[]) => { shared.value = all.filter((b) => !b.invited); invitations.value = all.filter((b) => b.invited) }
  const identities = ref<Identity[]>([])
  const loaded = ref(false)

  async function load() {
    try {
      const [m, c, boxes, ids] = await Promise.all([api.accounts.mail.list(), api.accounts.calendar.list(), api.accounts.shared.list(), api.accounts.identities.list().catch(() => [])])
      mail.value = m; calendar.value = c; identities.value = ids; setShared(boxes)
    } finally {
      loaded.value = true
    }
  }
  async function addMail(a: NewMailAccount) { mail.value = [...mail.value, await api.accounts.mail.create(a)] }
  async function removeMail(id: string) { await api.accounts.mail.remove(id); mail.value = mail.value.filter((a) => a.id !== id) }
  async function updateMail(id: string, patch: Parameters<typeof api.accounts.mail.update>[1]) { const a = await api.accounts.mail.update(id, patch); mail.value = mail.value.map((x) => (x.id === id ? { ...x, ...a } : x)) }
  const testMail = (id: string) => api.accounts.mail.test(id)
  async function addCalendar(a: NewCalendarAccount) { calendar.value = [...calendar.value, await api.accounts.calendar.create(a)] }
  async function removeCalendar(id: string) { await api.accounts.calendar.remove(id); calendar.value = calendar.value.filter((a) => a.id !== id) }

  /** Identité d'expéditeur par défaut (toujours une identité personnelle), sinon la première. */
  const defaultIdentity = () => identities.value.find((i) => i.isDefault) ?? identities.value[0]
  /**
   * Tout ce dont on peut écrire : les identités personnelles, puis celles des boîtes partagées dont on est membre (« De : contact@ »).
   * Une boîte partagée s'envoie avec le premier compte de messagerie de l'utilisateur.
   */
  const senders = computed<Identity[]>(() => [
    ...identities.value,
    ...shared.value.filter((b) => b.identityId && mail.value[0]).map((b): Identity => ({
      id: b.identityId!, accountId: mail.value[0].id, name: b.senderName || b.name, address: b.address, replyTo: null, signature: b.signature ?? null,
      signatureHtml: null, isDefault: false, primary: false, shared: true,
    })),
  ])
  const identityOf = (id: string | null | undefined) => senders.value.find((i) => i.id === id)
  const sharedSenderOf = (slug: string) => senders.value.find((i) => i.shared && shared.value.find((b) => b.identityId === i.id)?.slug === slug)
  async function updateSharedSender(id: string, p: { senderName: string | null; signature: string | null }) {
    const b = await api.accounts.shared.updateSender(id, p)
    shared.value = shared.value.map((x) => (x.id === id ? { ...x, ...b } : x))
  }
  /** Après tout changement de boîte, on relit la liste : le serveur décide du rôle et des membres. */
  async function changeShared<T>(action: () => Promise<T>): Promise<T> {
    const out = await action()
    setShared(await api.accounts.shared.list())
    return out
  }
  const createShared = (p: { address: string; name: string }) => changeShared(() => api.accounts.shared.create(p))
  const updateShared = (id: string, p: { autoFollow?: boolean; aiEnabled?: boolean }) => changeShared(() => api.accounts.shared.update(id, p))
  const addSharedMember = (id: string, username: string, role: 'member' | 'admin') => changeShared(() => api.accounts.shared.addMember(id, username, role))
  const removeSharedMember = (id: string, username: string) => changeShared(() => api.accounts.shared.removeMember(id, username))
  const acceptShared = (id: string) => changeShared(() => api.accounts.shared.accept(id))
  const removeShared = (id: string) => changeShared(() => api.accounts.shared.remove(id))
  const reloadIdentities = async () => { identities.value = await api.accounts.identities.list() }
  async function addIdentity(i: IdentityInput & { accountId: string }) { const n = await api.accounts.identities.create(i); await reloadIdentities(); return n }
  async function saveIdentity(id: string, i: IdentityInput) { const n = await api.accounts.identities.update(id, i); await reloadIdentities(); return n }
  async function makeDefaultIdentity(id: string) { await api.accounts.identities.setDefault(id); await reloadIdentities() }
  async function removeIdentity(id: string) { await api.accounts.identities.remove(id); await reloadIdentities() }

  return { invitations, acceptShared, createShared, updateShared, addSharedMember, removeSharedMember, removeShared, identities, senders, sharedSenderOf, updateSharedSender, defaultIdentity, identityOf, addIdentity, saveIdentity, makeDefaultIdentity, removeIdentity, mail, calendar, shared, loaded, load, addMail, updateMail, removeMail, testMail, addCalendar, removeCalendar }
})
