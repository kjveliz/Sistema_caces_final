import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/*
 * Credenciales del usuario demo sembrado por db/seeds/UsuariosSeeder.php
 * ("mismos datos de ejemplo que ya usa el equipo en local"). Se usa
 * "administrador" por defecto porque es el único rol que puede completar
 * los 4 flujos (login, ver resultado, exportar PDF y subir evidencia — esta
 * última requiere administrador o coordinador, ver App.tsx `puedeCargar`).
 *
 * Si tu BD de desarrollo no tiene este usuario (o le cambiaste la
 * contraseña), sobreescribí con variables de entorno antes de correr los
 * tests — ver e2e/.env.e2e.example.
 */
export const E2E_EMAIL = process.env.E2E_EMAIL ?? 'administrador@demo.local';
export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? 'CacesDemo2026!';

/*
 * "Primera carrera clickeable" no alcanza: CareersView marca clickable=true
 * para TODAS las carreras activas (ver CareersView.tsx), pero no todas tienen
 * una fila en `evaluaciones` -- si se elige a ciegas, el Dashboard puede caer
 * en una carrera sin evaluación creada para la cohorte por defecto y los
 * tests de resultado/PDF/evidencia nunca van a tener nada que mostrar.
 * Se apunta a una carrera con evaluación real confirmada (Desarrollo de
 * Software, dump `evaluacion_caces`), overridable si tu BD cambia.
 */
export const E2E_CAREER = process.env.E2E_CAREER ?? 'Desarrollo de Software';

/** Completa el formulario de login y espera a que la sesión quede activa (vista "careers"). */
export async function login(
  page: Page,
  email: string = E2E_EMAIL,
  password: string = E2E_PASSWORD,
) {
  await page.goto('/');

  // LoginView.tsx no asocia el <label> con el <input> (ni `htmlFor`/`id`, ni
  // anidamiento) -- getByLabel no puede resolverlos. Cada campo es el único
  // de su `type` en la pantalla, así que se targetean directo por ahí.
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

  // Tras un login exitoso, App.tsx pasa a la vista "careers": se ve el botón
  // "Salir" (CareersView.tsx, onLogout).
  await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible({
    timeout: 15_000,
  });
}

/**
 * Navega desde la vista "careers" (post-login) hasta el Dashboard de una
 * carrera: entra a la carrera con evaluación real confirmada (E2E_CAREER,
 * ver nota arriba) y a "Docencia" (el único criterio clickeable — ver
 * CriteriaView.tsx).
 */
export async function goToFirstCareerDashboard(page: Page, career: string = E2E_CAREER) {
  // Cada fila de carrera en CareersView es un <div onClick> con un <span>
  // de texto exacto adentro; el click en el texto burbujea al div.
  const filaCarrera = page.getByText(career, { exact: true });
  await expect(filaCarrera).toBeVisible({ timeout: 15_000 });
  await filaCarrera.click();

  await page.getByText('Docencia', { exact: true }).click();

  // Dashboard: se reconoce por el botón para volver a "Carreras" (onBackToCareers).
  await expect(page.getByText('I2', { exact: true })).toBeVisible({ timeout: 15_000 });
}
