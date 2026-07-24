import { test, expect } from '@playwright/test';
import { login, goToFirstCareerDashboard } from './helpers/auth';

test('exportar PDF de I2', async ({ page }) => {
  await login(page);
  await goToFirstCareerDashboard(page);

  const cardI2 = page
    .locator('div')
    .filter({ has: page.getByText('I2', { exact: true }) })
    .filter({ has: page.getByText('Seguimiento de Syllabus', { exact: true }) })
    .last();

  await cardI2.getByRole('button').first().click();

  const botonExportar = page.getByRole('button', { name: /Exportar PDF/i });
  await expect(botonExportar).toBeVisible({ timeout: 15_000 });

  // El botón se deshabilita si no hay ninguna asignatura cargada para esta
  // carrera/cohorte/PAO (ver `disabled={exportando || !asig}` en
  // IndicatorView.tsx) — en ese caso no hay PDF que exportar todavía en esta
  // BD de desarrollo y el test se salta en vez de fallar en falso.
  test.skip(
    await botonExportar.isDisabled(),
    'No hay ninguna asignatura con resultado para exportar en esta carrera/cohorte/PAO.',
  );

  // exportarPdfIndicador2 (jsPDF) genera el archivo enteramente en el
  // cliente: no hay red de por medio, solo el evento download del navegador.
  const [descarga] = await Promise.all([
    page.waitForEvent('download', { timeout: 15_000 }),
    botonExportar.click(),
  ]);

  expect(descarga.suggestedFilename().toLowerCase()).toMatch(/\.pdf$/);

  const ruta = await descarga.path();
  expect(ruta).not.toBeNull();

  const fs = await import('node:fs/promises');
  const fh = await fs.open(ruta as string, 'r');
  const buffer = Buffer.alloc(5);
  await fh.read(buffer, 0, 5, 0);
  await fh.close();
  expect(buffer.toString('latin1')).toBe('%PDF-');
});
