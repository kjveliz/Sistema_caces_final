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

/** Completa el formulario de login y espera a que la sesión quede activa (vista "careers"). */
export async function login(
  page: Page,
  email: string = E2E_EMAIL,
  password: string = E2E_PASSWORD,
) {
  await page.goto('/');

  await page.getByLabel('Correo institucional').fill(email);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

  // Tras un login exitoso, App.tsx pasa a la vista "careers": se ve el botón
  // "Salir" (CareersView.tsx, onLogout).
  await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible({
    timeout: 15_000,
  });
}

/**
 * Navega desde la vista "careers" (post-login) hasta el Dashboard de una
 * carrera: elige la primera carrera habilitada y entra a "Docencia" (el
 * único criterio clickeable — ver CriteriaView.tsx). No asume un nombre de
 * carrera fijo porque corre contra datos reales de desarrollo.
 */
export async function goToFirstCareerDashboard(page: Page) {
  // CareersView marca las carreras seleccionables con una flecha "→" al lado
  // del nombre; las no clickeables no la tienen. Se toma la primera fila que
  // sí la tiene.
  const primeraCarrera = page.locator('span:has-text("→")').first().locator('..');
  await expect(primeraCarrera).toBeVisible({ timeout: 15_000 });
  await primeraCarrera.click();

  await page.getByText('Docencia', { exact: true }).click();

  // Dashboard: se reconoce por el botón para volver a "Carreras" (onBackToCareers).
  await expect(page.getByText('I2', { exact: true })).toBeVisible({ timeout: 15_000 });
}
