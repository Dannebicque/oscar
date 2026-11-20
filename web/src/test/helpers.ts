import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Component } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { resetApi } from '../api'
import { createMockApi } from '../api/mock'
import { i18n, setLocale } from '../i18n'

/** Monte un composant avec Pinia, i18n et un routeur mémoire, sur une API simulée fraîche. */
export async function mountApp(component: Component, path = '/', props: Record<string, unknown> = {}, opts: { host?: boolean } = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)
  resetApi(createMockApi())
  setLocale('fr')
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div/>' } },
      { path: '/mails/:id?', name: 'mails', component: { template: '<div/>' } },
      { path: '/:rest(.*)*', component: { template: '<div/>' } },
    ],
  })
  router.push(path)
  await router.isReady()
  // Les vues vivent dans <main> dans l'app : on reproduit ce repère pour l'audit.
  const host = opts.host === false ? document.body : document.createElement('main')
  if (opts.host !== false) document.body.append(host)
  const wrapper = mount(component, { props, global: { plugins: [pinia, router, i18n] }, attachTo: host })
  await flushPromises()
  return { wrapper, router, pinia }
}
