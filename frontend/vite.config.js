import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// En dev, /api est relayé vers l'API Symfony (php -S 127.0.0.1:8000 -t public).
export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: { '/api': 'http://127.0.0.1:8000' },
  },
})
