import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    // jsdom (en vez de 'node') porque a partir de esta sesión también hay
    // tests de componentes con React Testing Library (ver
    // src/app/components/*.test.tsx), que necesitan un DOM real para poder
    // renderizar. Los tests .ts existentes (funciones puras, sin DOM) siguen
    // corriendo igual bajo jsdom -- no hay ningún caso en el repo que
    // dependa de estar en un entorno sin `document`/`window`.
    environment: 'jsdom',
    setupFiles: ['./src/test-setup.ts'],
    include: ['src/**/*.test.ts', 'src/**/*.test.tsx'],
  },
});
