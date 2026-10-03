import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Dev only: forward API calls to the NestJS container, like nginx does in prod
    proxy: {
      '/api': 'http://backend:3000',
    },
  },
})
