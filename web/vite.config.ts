import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  // Démo publique : l'application peut être servie sous un sous-chemin (VITE_BASE=/demo/).
  base: process.env.VITE_BASE || '/',
  plugins: [vue()],
  server: { port: 5173, strictPort: true },
  test: { environment: 'jsdom', setupFiles: ['./src/test/setup.ts'] },
})
