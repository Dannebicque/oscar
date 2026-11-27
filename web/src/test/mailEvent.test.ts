import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import MailReader from '../components/mail/MailReader.vue'
import type { Mail } from '../domain/types'
import { useAgendaStore } from '../stores/agenda'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

const event = { title: 'Lemoine', start: '2026-12-01T14:00:00+01:00', end: '2026-12-01T15:00:00+01:00', quote: 'mardi à 14 h' }
const invitation = { uid: 'u', title: 'Soutenance', start: '2026-11-30T14:00:00+01:00', end: '2026-11-30T15:00:00+01:00', location: 'B12', organizerName: 'Claire', organizerAddress: 'c@x.fr' }

describe('rendez-vous depuis un mail (F-20, F-25)', () => {
  afterEach(() => vi.restoreAllMocks())

  async function open(patch: Partial<Mail>) {
    await mountApp({ template: '<div/>' })
    const mail = { ...(await api.mails.list())[0], id: 'e1', invitation: undefined, hints: { event }, ...patch } as Mail
    const { wrapper } = await mountApp(MailReader, '/', { mail }, { keepApi: true })
    const mails = useMailsStore()
    mails.items = [mail]
    const agenda = useAgendaStore()
    agenda.sources = [{ id: 'cal-reel', label: 'Mon agenda', personal: false, kind: 'caldav' } as never]
    await flushPromises()
    const add = () => wrapper.findAll('button').find((b) => b.text() === 'Ajouter à l’agenda')
    return { wrapper, agenda, add }
  }

  it('ajoute dans un agenda de l’utilisateur, pas dans un identifiant de démonstration', async () => {
    const { add } = await open({})
    const create = vi.spyOn(api.agenda, 'create').mockImplementation(async (e) => ({ ...e, id: 'n' }))
    await add()!.trigger('click')
    await flushPromises()
    expect(create).toHaveBeenCalledWith(expect.objectContaining({ calendar: 'cal-reel', title: 'Lemoine' }))
  })

  it('un rendez-vous déjà dans l’agenda n’est pas reproposé', async () => {
    const { agenda, add } = await open({})
    agenda.events = [{ id: 'z', calendar: 'cal-reel', title: 'Lemoine', start: '2026-12-01T13:00:00+00:00', end: '2026-12-01T14:00:00+00:00', location: null }]
    await flushPromises()
    expect(add()).toBeUndefined()
  })

  it('un refus de l’agenda se dit et la suggestion reste', async () => {
    const { add } = await open({})
    vi.spyOn(api.agenda, 'create').mockRejectedValue(new Error('500'))
    await add()!.trigger('click')
    await flushPromises()
    expect(add()).toBeTruthy()
  })

  it('signale un chevauchement même écrit dans un autre fuseau', async () => {
    const { wrapper, agenda } = await open({ invitation })
    // 13:30 UTC = 14:30 à Paris : pendant la soutenance de 14 h à 15 h, bien que le texte « 13:30 » précède « 14:00 ».
    agenda.events = [{ id: 'x', calendar: 'cal-reel', title: 'Conseil', start: '2026-11-30T13:30:00+00:00', end: '2026-11-30T13:45:00+00:00', location: null }]
    await flushPromises()
    expect(wrapper.text()).toContain('Chevauche « Conseil »')
  })

  it('une invitation déjà inscrite ne se chevauche pas elle-même', async () => {
    const { wrapper, agenda } = await open({ invitation })
    agenda.events = [{ id: 'y', calendar: 'cal-reel', title: 'Soutenance', start: '2026-11-30T13:00:00+00:00', end: '2026-11-30T14:00:00+00:00', location: 'B12' }]
    await flushPromises()
    expect(wrapper.text()).not.toContain('Chevauche')
  })

  it('« Ajouter sans répondre » inscrit sans noter de réponse', async () => {
    const { wrapper } = await open({ invitation })
    const reply = vi.spyOn(api.mails, 'replyInvitation').mockResolvedValue({ added: false, inCalendar: true })
    await wrapper.findAll('button').find((b) => b.text() === 'Accepter')!.trigger('click')
    await flushPromises()
    ;[...document.body.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Ajouter sans répondre')!.click()
    await flushPromises()
    expect(reply).toHaveBeenCalledWith('e1', expect.objectContaining({ send: false, calendarId: 'cal-reel', message: undefined }))
    expect(useMailsStore().byId('e1')?.invitationResponse).toBeUndefined()
  })

  it('avec une invitation jointe, pas de second rendez-vous suggéré', async () => {
    const { wrapper, add } = await open({ invitation })
    expect(wrapper.text()).toContain('Soutenance')
    expect(add()).toBeUndefined()
  })
})
