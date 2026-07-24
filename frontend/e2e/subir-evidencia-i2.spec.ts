import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';
import { login, goToFirstCareerDashboard } from './helpers/auth';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PDF_FIXTURE = path.join(__dirname, 'fixtures', 'normativa-institucional.pdf');

/*
 * Sube el slot "Normativa Institucional" de I2: es evaluation-wide (no pide
 * asignatura, ver EvidenceUploadView.tsx `I2_SLOT_TIPO`) y no tiene
 * `sharedKey`, así que no depende de qué otras evidencias ya existan en la
 * BD real de desarrollo. Sube el mismo archivo dos veces sin problema
 * (reemplaza el anterior), así que este test se puede correr repetidamente.
 *
 * Requiere que el usuario logueado sea administrador o coordinador
 * (`puedeCargar` en App.tsx) — ver e2e/helpers/auth.ts.
 *
 * Nota: esto sube de verdad a Google Drive y a la BD real de desarrollo
 * (correo/contraseña de E2E_EMAIL/E2E_PASSWORD) — no es una BD de prueba
 * aislada. Ver INSTRUCCIONES_fase5_playwright.md.
 */
test('subir evidencia de I2 (Normativa Institucional)', async ({ page }) => {
  await login(page);
  await goToFirstCareerDashboard(page);

  const cardI2 = page
    .locator('div')
    .filter({ has: page.getByText('I2', { exact: true }) })
    .filter({ has: page.getByText('Seguimiento de Syllabus', { exact: true }) })
    .last();

  await cardI2.getByRole('button').first().click();

  const botonCargar = page.getByRole('button', { name: 'Cargar evidencias' });
  await expect(botonCargar).toBeVisible({ timeout: 15_000 });
  await botonCargar.click();

  // Paso "Configurar período": cohorte ya viene preseleccionada
  // (preselectedCohort); solo falta PAO, módulo y materia (los 3 son datos
  // estáticos del frontend, no dependen de la BD real — ver
  // src/data/academic.ts).
  await expect(page.getByText('Seleccionar período', { exact: true })).toBeVisible({
    timeout: 15_000,
  });

  await page.getByRole('button', { name: 'PAO 1', exact: true }).click();
  await page.getByRole('button', { name: 'A', exact: true }).click();

  const materiaSelect = page.locator('select').nth(1);
  await materiaSelect.selectOption({ index: 1 });

  await page.getByRole('button', { name: 'Continuar a carga de archivos →' }).click();

  // Paso "Cargar archivos": el input real está oculto (PdfZone.tsx sube por
  // click en un botón "Subir" que dispara el input); Playwright puede setear
  // el archivo directamente sobre el input aunque esté oculto.
  const zonaNormativa = page.locator(
    'xpath=//p[normalize-space(text())="Normativa Institucional"]/ancestor::div[.//input[@type="file"]][1]',
  );
  await expect(zonaNormativa).toBeVisible({ timeout: 15_000 });
  await zonaNormativa.locator('input[type="file"]').setInputFiles(PDF_FIXTURE);

  // Sube a Google Drive + guarda en MySQL (procesarPdf, camino genérico para
  // slots evaluation-wide) — puede tardar unos segundos.
  await expect(page.getByText('PDF guardado correctamente')).toBeVisible({ timeout: 30_000 });

  await page.getByRole('button', { name: 'Guardar y volver →' }).click();
  await expect(page.getByText('Cambios guardados correctamente')).toBeVisible({
    timeout: 10_000,
  });
});
