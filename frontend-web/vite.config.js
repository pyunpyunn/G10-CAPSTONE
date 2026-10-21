import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import process from 'node:process'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const servingFromLaravel = mode === 'laravel'
  const publicWebUrl = loadEnv(mode, process.cwd(), 'VITE_').VITE_PUBLIC_WEB_URL
  const publicWebHost = publicWebUrl ? new URL(publicWebUrl).hostname : null

  return {
    plugins: [react()],
    base: servingFromLaravel ? '/frontend-web/' : '/',
    build: {
      outDir: servingFromLaravel ? '../backend-laravel/public/frontend-web' : 'dist',
      emptyOutDir: true,
    },
    server: {
      allowedHosts: ['mc15a.tail1e9a11.ts.net', ...(publicWebHost ? [publicWebHost] : [])],
      host: '0.0.0.0',
      port: 5175,
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
        '/storage': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
