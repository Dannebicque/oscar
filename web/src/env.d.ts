/// <reference types="vite/client" />
declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<object, object, unknown>
  export default component
}

interface ImportMetaEnv {
  readonly VITE_API_URL?: string
  /** « true » : démo publique (données fictives, bandeau, pas de service worker). */
  readonly VITE_DEMO?: string
  /** Adresse du site de présentation, pour le lien « Installer » de la démo. */
  readonly VITE_DEMO_LINK?: string
}
