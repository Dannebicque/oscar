import { createRouter, createWebHistory } from 'vue-router'
import SettingsView from './views/SettingsView.vue'
import SuivisView from './views/SuivisView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/suivis' },
    { path: '/suivis', name: 'suivis', component: SuivisView },
    { path: '/reglages', name: 'settings', component: SettingsView },
  ],
})
