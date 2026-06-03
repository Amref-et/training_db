import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const mobileRoot = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  root: mobileRoot,
  build: {
    chunkSizeWarningLimit: 1500,
    emptyOutDir: true,
    outDir: 'dist',
  },
  plugins: [react()],
});
