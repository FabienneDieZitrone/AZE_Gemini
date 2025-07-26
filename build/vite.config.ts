/// <reference types="vitest" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  // KRITISCHE ÄNDERUNG: Der Basispfad ist das Root-Verzeichnis der Subdomain.
  base: '/',
  build: {
    // Stellt sicher, dass der Output-Ordner 'dist' heißt.
    // Der Inhalt dieses Ordners muss auf den Server geladen werden.
    outDir: 'dist',
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    globals: true,
  },
})
