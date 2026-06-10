import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const servingFromLaravel = mode === 'laravel'

  return {
    plugins: [react()],
    base: servingFromLaravel ? '/frontend-web/' : '/',
    build: {
      outDir: servingFromLaravel ? '../backend-laravel/public/frontend-web' : 'dist',
      emptyOutDir: true,
    },
    server: {
      host: '0.0.0.0',
      port: 5175,
    },
  }
})
