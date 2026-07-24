import { test, expect } from '@playwright/test';
import { login, goToFirstCareerDashboard } from './helpers/auth';

test('ver resultado de I2 (Seguimiento de Syllabus)', async ({ page }) => {
  await login(page);
  await goToFirstCareerDashboard(page);

  // Card de I2 en el Dashboard (PaoGroupCard): cada PAO es un <button> propio.
  // Se toma el primero disponible, sea cual sea su estado ("Sin datos" o con %).
  const cardI2 = page
    .locator('div')
    .filter({ has: page.getByText('I2', { exact: true }) })
    .filter({ has: page.getByText('Seguimiento de Syllabus', { exact: true }) })
    .last();

  await cardI2.getByRole('button').first().click();

  // IndicatorView: título del indicador y el panel de "Asignaturas" (lista de
  // materias, con la primera seleccionada por defecto).
  await expect(page.getByText('Seguimiento de Syllabus', { exact: true }).first()).toBeVisible({
    timeout: 15_000,
  });
  await expect(page.getByText('Asignaturas', { exact: true })).toBeVisible();

  // El resultado se muestra como "—" (sin datos) o como un porcentaje ("NN%");
  // cualquiera de los dos confirma que la vista de resultado cargó de verdad.
  await expect(page.getByText(/^(—|\d{1,3}%)$/).first()).toBeVisible();
});
