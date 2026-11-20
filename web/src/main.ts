import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import './styles/index.css'
import { i18n } from './i18n'
import { router } from './router'
import { useSettingsStore } from './stores/settings'

const app = createApp(App).use(createPinia()).use(router).use(i18n)
useSettingsStore() // applique thème, densité et langue avant le premier affichage
app.mount('#app')
