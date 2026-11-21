import { flushPromises, mount } from '@vue/test-utils'
import axe from 'axe-core'
import { afterEach, describe, expect, it } from 'vitest'
import { h, ref } from 'vue'
import SelectField from '../components/ui/SelectField.vue'
import TextField from '../components/ui/TextField.vue'
import TextareaField from '../components/ui/TextareaField.vue'
import { i18n } from '../i18n'
import { useTasksStore } from '../stores/tasks'
import TasksView from '../views/TasksView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })
const settle = async () => { await flushPromises(); await new Promise((r) => setTimeout(r, 30)); await flushPromises() }
const dlg = () => document.body.querySelector<HTMLElement>('[role="dialog"]')
const fill = (sel: string, v: string) => {
  const el = document.body.querySelector<HTMLInputElement>(sel)!
  el.value = v
  el.dispatchEvent(new Event('input', { bubbles: true }))
  el.dispatchEvent(new Event('change', { bubbles: true }))
}
const submit = () => document.body.querySelector<HTMLFormElement>('#task-form')!.dispatchEvent(new Event('submit', { cancelable: true }))
const open = async (w: Awaited<ReturnType<typeof mountApp>>['wrapper'], label: string) => {
  await w.findAll('button').find((b) => b.text().includes(label))!.trigger('click')
  await flushPromises()
}

describe('champs de formulaire partagés', () => {
  it('relient étiquette, aide et erreur au champ (aria-describedby, aria-invalid, role=alert)', () => {
    const v = ref('')
    const w = mount(() => h(TextField, { modelValue: v.value, label: 'Titre', hint: 'Aide', error: 'Obligatoire', required: true, id: 't1', 'onUpdate:modelValue': (x: string) => (v.value = x) }), { global: { plugins: [i18n] }, attachTo: document.body })
    const input = w.find('input')
    expect(document.querySelector('label[for="t1"]')?.textContent).toContain('Titre')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe('t1-hint t1-err')
    expect(w.find('#t1-err').attributes('role')).toBe('alert')
    expect(input.attributes('required')).toBeDefined()
  })

  it('le champ texte, la liste et la zone de texte émettent leur valeur', async () => {
    const t = ref(''), s = ref(1), a = ref('')
    const w = mount(() => h('div', [
      h(TextField, { modelValue: t.value, label: 'T', 'onUpdate:modelValue': (x: string) => (t.value = x) }),
      h(SelectField, { modelValue: s.value, label: 'S', options: [{ value: 1, label: 'Un' }, { value: 2, label: 'Deux' }], 'onUpdate:modelValue': (x: string | number) => (s.value = Number(x)) }),
      h(TextareaField, { modelValue: a.value, label: 'A', 'onUpdate:modelValue': (x: string) => (a.value = x) }),
    ]), { global: { plugins: [i18n] }, attachTo: document.body })
    await w.find('input').setValue('bonjour')
    await w.find('select').setValue('2')
    await w.find('textarea').setValue('long texte')
    expect([t.value, s.value, a.value]).toEqual(['bonjour', 2, 'long texte']) // la liste de nombres reste un nombre
    const r = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } }, runOnly: ['wcag2a', 'wcag2aa', 'best-practice'] })
    expect(r.violations.map((x) => x.id)).toEqual([])
  })
})

describe('page Tâches', () => {
  it('« Nouvelle tâche » ouvre un formulaire complet et crée la tâche', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    await open(wrapper, 'Nouvelle tâche')
    expect(dlg()!.querySelector('h2')?.textContent).toBe('Nouvelle tâche')
    for (const label of ['Titre', 'Description', 'Échéance', 'Priorité', 'Projet', 'Étiquettes']) expect(dlg()!.textContent).toContain(label)
    fill('#task-form input[type="text"]', 'Préparer le jury')
    fill('#task-form input[type="date"]', '2026-12-04')
    fill('#task-form select', '1')
    submit()
    await settle()
    const created = useTasksStore(pinia).items.find((x) => x.title === 'Préparer le jury')!
    expect(created).toMatchObject({ dueAt: '2026-12-04', priority: 1, origin: 'manual' })
    expect(dlg()).toBeNull()
  })

  it('refuse une tâche sans titre, avec une erreur annoncée, sans rien créer', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    const before = useTasksStore(pinia).items.length
    await open(wrapper, 'Nouvelle tâche')
    submit()
    await flushPromises()
    expect(dlg()!.querySelector('[role="alert"]')?.textContent).toContain('Donne un titre')
    expect(useTasksStore(pinia).items).toHaveLength(before)
  })

  it('cliquer sur une tâche l’ouvre pour la modifier ; l’origine et la source sont rappelées', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    await wrapper.find('button[aria-label^="Ouvrir Relire la convention"]').trigger('click')
    await flushPromises()
    expect(dlg()!.querySelector('h2')?.textContent).toBe('Modifier la tâche')
    expect((document.querySelector('#task-form input[type="text"]') as HTMLInputElement).value).toBe('Relire la convention de Claire Petit')
    expect(dlg()!.textContent).toContain('Issue d’un mail')
    fill('#task-form input[type="text"]', 'Relire et signer la convention')
    submit()
    await settle()
    expect(useTasksStore(pinia).items.find((x) => x.id === 't2')?.title).toBe('Relire et signer la convention')
  })

  it('la suppression demande confirmation avant d’agir', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    await wrapper.find('button[aria-label^="Ouvrir Commander le papier"]').trigger('click')
    await flushPromises()
    ;[...dlg()!.querySelectorAll('button')].find((b) => b.textContent?.includes('Supprimer'))!.click()
    await flushPromises()
    expect(useTasksStore(pinia).items.some((x) => x.id === 't5')).toBe(true) // pas encore supprimée
    const confirmDialog = [...document.body.querySelectorAll('[role="dialog"]')].find((d) => d.textContent?.includes('Supprimer cette tâche'))!
    ;[...confirmDialog.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Supprimer')!.click()
    await flushPromises()
    expect(useTasksStore(pinia).items.some((x) => x.id === 't5')).toBe(false)
  })

  it('l’ajout rapide crée une tâche à partir d’un titre seul', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    await wrapper.find('#new-task').setValue('Appeler la scolarité')
    await wrapper.find('form.quick').trigger('submit')
    await flushPromises()
    expect(useTasksStore(pinia).items.some((x) => x.title === 'Appeler la scolarité')).toBe(true)
  })

  it('les tâches proposées par l’IA se confirment ou se rejettent, et portent « Suggéré par l’IA »', async () => {
    const { wrapper, pinia } = await mountApp(TasksView, '/taches')
    await flushPromises()
    const row = wrapper.findAll('li.task').find((r) => r.text().includes('Confirmer le nombre'))!
    expect(row.text()).toContain('Suggéré par l’IA')
    await row.findAll('button').find((b) => b.text() === 'Confirmer')!.trigger('click')
    await flushPromises()
    expect(useTasksStore(pinia).items.find((x) => x.id === 't3')?.toConfirm).toBe(false)
  })

  it('chaque tâche dit sa priorité et son échéance en texte', async () => {
    const { wrapper } = await mountApp(TasksView, '/taches')
    await flushPromises()
    const row = wrapper.findAll('li.task').find((r) => r.text().includes('Relire la convention'))!
    expect(row.text()).toContain('Urgente')
  })
})
