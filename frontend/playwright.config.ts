import { defineConfig, devices } from '@playwright/test';

/*
 * Fase 5 del Plan de Mejora — end-to-end con Playwright (ver MEMORIA §34.8 /
 * INSTRUCCIONES_fase5_playwright.md).
 *
 * Corre contra el entorno real de desarrollo, no contra una BD aislada:
 *   - Backend: XAMPP/Apache sirviendo el repo en `htdocs/sistemacaces`, en
 *     http://localhost/sistemacaces/api/... (mismo host:puerto hardcodeado
 *     que ya usan los `fetch()` del frontend — ver src/services/auth.ts).
 *     Playwright NO lo levanta: debe estar corriendo de antemano, igual que
 *     cuando el equipo desarrolla a mano.
 *   - Frontend: Playwright levanta `npm run dev` (Vite, puerto 5173) si no
 *     hay uno ya corriendo — mismo puerto para el que ya está configurado
 *     CORS_ALLOWED_ORIGIN en el backend.
 *
 * Credenciales: por defecto usa el usuario demo de db/seeds/UsuariosSeeder.php
 * (mismos datos de ejemplo que ya usa el equipo en local). Si tu BD de
 * desarrollo tiene otro usuario, sobreescribí con variables de entorno — ver
 * e2e/helpers/auth.ts y e2e/.env.e2e.example.
 */

const FRONTEND_URL = process.env.E2E_BASE_URL ?? 'http://localhost:5173';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false, // navegan la misma BD real de desarrollo; evita carreras entre specs
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  timeout: 30_000,

  use: {
    baseURL: FRONTEND_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Solo levanta el frontend. El backend (XAMPP) es responsabilidad del
  // usuario, igual que en desarrollo normal — ver INSTRUCCIONES_fase5_playwright.md.
  webServer: {
    command: 'npm run dev',
    url: FRONTEND_URL,
    reuseExistingServer: true,
    timeout: 60_000,
  },
});
