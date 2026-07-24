# Fase 5 — End-to-end con Playwright

Agrega los 4 flujos e2e que pedía el plan de mejora (login, ver resultado,
subir evidencia, exportar PDF de I2), corriendo contra tu entorno **real**
de desarrollo (XAMPP + BD real), no contra una BD de prueba aislada — así lo
pediste en esta sesión.

## 1. Requisitos antes de correrlos

1. **Backend arriba, como siempre trabajás.** XAMPP con Apache sirviendo el
   repo en `htdocs/sistemacaces`, MySQL corriendo, `.env` configurado (ver
   `.env.example`). Playwright **no** levanta esto — asume que ya está
   corriendo en `http://localhost/sistemacaces`, igual que cuando desarrollás
   a mano.
2. **Un usuario administrador o coordinador válido en tu BD real.** Por
   defecto los tests usan `administrador@demo.local` / `CacesDemo2026!` (el
   usuario demo de `db/seeds/UsuariosSeeder.php` — "mismos datos de ejemplo
   que ya usa el equipo en local"). Si tu BD no tiene ese usuario, o le
   cambiaste la contraseña, copiá `frontend/e2e/.env.e2e.example` a
   `frontend/e2e/.env.e2e` (queda fuera de git — `.gitignore` ya lo cubre) y
   completá ahí `E2E_EMAIL`/`E2E_PASSWORD` (y `E2E_CAREER`/`E2E_BASE_URL` si
   hace falta). **`npm run test:e2e` ya carga ese archivo solo** (vía
   `dotenv-cli`, agregado como devDependency) — no hace falta exportar nada a
   mano ni repetirlo en cada terminal nueva. Si el archivo no existe todavía,
   el comando no falla: simplemente corre con los defaults hardcodeados
   (usuario demo), igual que antes.
3. **Al menos una carrera con `clickable: true` y datos cargados** para que
   "ver resultado" y "exportar PDF" tengan algo real que mostrar (si no,
   estos dos tests igual pasan navegando correctamente hasta la vista, pero
   `exportar-pdf-i2.spec.ts` se salta — `test.skip` — si el botón de
   exportar está deshabilitado por falta de una asignatura con resultado).
4. **Google Drive configurado de verdad** (`api/google_drive/credenciales.json`
   con credenciales reales) para que `subir-evidencia-i2.spec.ts` complete el
   flujo — ese slot sube a Drive antes de guardar la URL en MySQL, igual que
   cuando cargás evidencia a mano.
5. Instalar el browser de Playwright una sola vez: `npx playwright install
   chromium` (dentro de `frontend/`).

## 2. Cómo correrlos

```bash
cd frontend
npm install                 # trae @playwright/test (ya en devDependencies)
npx playwright install chromium
npm run test:e2e
```

Reporte HTML en `frontend/playwright-report/` (`npx playwright show-report`
para abrirlo).

## 3. Qué cubre cada spec

- `e2e/login.spec.ts` — login válido (llega a "Carreras") y login con
  contraseña incorrecta (muestra error, no navega).
- `e2e/ver-resultado-i2.spec.ts` — login → primera carrera habilitada →
  "Docencia" → Dashboard → primer PAO de la card de I2 → confirma que carga
  la vista de resultado (título, panel "Asignaturas", el % o "—").
- `e2e/exportar-pdf-i2.spec.ts` — igual navegación, click en "Exportar PDF",
  confirma la descarga y que el archivo empieza con la cabecera `%PDF-`
  (jsPDF genera el PDF 100% en el cliente, no hay endpoint de backend que
  lo arme).
- `e2e/subir-evidencia-i2.spec.ts` — sube el slot **"Normativa
  Institucional"** de I2 (evaluation-wide, sin necesidad de elegir
  asignatura ni depender de qué otras evidencias ya existan — ver
  `I2_SLOT_TIPO` en `EvidenceUploadView.tsx`), con un PDF fixture mínimo
  (`e2e/fixtures/normativa-institucional.pdf`), y confirma los 2 toasts de
  éxito ("PDF guardado correctamente" y "Cambios guardados correctamente").
  Se puede correr repetidas veces: subir el mismo archivo de nuevo
  simplemente reemplaza el anterior.

Las 3 selecciones estáticas del wizard de carga (cohorte `B 2025`, PAO 1,
módulo A, primera materia) están hardcodeadas a propósito: son datos fijos
del frontend (`src/data/academic.ts`), no dependen de tu BD real, así que el
test es reproducible sin importar qué carreras/asignaturas reales tengas
cargadas.

## 4. Verificado en esta sesión (sin backend real disponible)

Sandbox sin acceso a tu XAMPP/BD/Drive reales y sin poder descargar el
binario de Chromium (red restringida a una allowlist que no incluye
`cdn.playwright.dev`), así que **no se pudo correr `npm run test:e2e` de
punta a punta contra un backend real** en esta sesión. Lo que sí se verificó
localmente contra el repo real clonado:

- `npx playwright test --list` → los 5 tests (4 archivos) parsean
  correctamente.
- `npm run typecheck` (ahora corre `tsc` sobre `src/` **y** sobre `e2e/` +
  `playwright.config.ts` vía `tsconfig.e2e.json` — antes quedaban afuera del
  tsconfig principal, que solo incluye `src`) → sin errores.
- `npm run lint` → 0 errores nuevos (mismos 45 warnings preexistentes de
  siempre, ninguno en los archivos de `e2e/`).
- `npx prettier --check e2e playwright.config.ts tsconfig.e2e.json` → OK.
- `npm run build` → sigue construyendo igual que antes (mismo warning
  preexistente de chunk size).

**Falta correr `npm run test:e2e` de verdad contra tu entorno** para
confirmar que los selectores realmente calzan con los datos reales de tu BD
(por ejemplo, si ninguna carrera tiene `clickable: true` en este momento, o
si el usuario demo no existe en tu BD, los tests van a fallar y hay que
avisar para ajustar).
