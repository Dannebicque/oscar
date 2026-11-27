import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import './styles/index.css'
import { ApiError } from './api'
import { reportFailure } from './errors'
import { i18n } from './i18n'
import { router } from './router'
import { restoreDesktopSession } from './api/session'
import { registerServiceWorker } from './pwa/register'
import { useSettingsStore } from './stores/settings'

const app = createApp(App).use(createPinia()).use(router).use(i18n)
// Gestionnaires d'événements et de cycle de vie, puis promesses lancées sans attente.
app.config.errorHandler = (error) => reportFailure(error)
window.addEventListener('unhandledrejection', (e) => { if (e.reason instanceof ApiError) { e.preventDefault(); reportFailure(e.reason) } })
useSettingsStore() // applique thème, densité et langue avant le premier affichage
registerServiceWorker()
void restoreDesktopSession().finally(() => app.mount('#app'))
