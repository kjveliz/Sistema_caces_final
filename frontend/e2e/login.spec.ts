import { test, expect } from '@playwright/test';
import { login, E2E_EMAIL } from './helpers/auth';

test.describe('Login', () => {
  test('inicia sesión con credenciales válidas y llega a Carreras', async ({ page }) => {
    await login(page);

    // Post-login queda en CareersView: se ve el nombre/rol del usuario y "Salir".
    await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible();
  });

  test('muestra un error con credenciales inválidas y no navega', async ({ page }) => {
    await page.goto('/');

    await page.locator('input[type="email"]').fill(E2E_EMAIL);
    await page.locator('input[type="password"]').fill('contraseña-incorrecta-e2e');
    await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

    // El formulario de login (LoginView.tsx) muestra el mensaje de error
    // devuelto por el backend y se queda en la misma vista.
    await expect(page.getByText(/no se pudo|credenciales|inválid/i)).toBeVisible({
      timeout: 10_000,
    });
    await expect(page.getByRole('button', { name: 'Salir' })).not.toBeVisible();
  });
});
