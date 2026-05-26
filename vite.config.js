import { defineConfig } from 'vite';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

export default defineConfig({
  css: {
    postcss: {
      plugins: [tailwindcss, autoprefixer],
    },
  },
  build: {
    outDir: 'dist',
    rollupOptions: {
      input: 'src/input.css',
      output: {
        assetFileNames: 'styles.css',
      },
    },
  },
});
