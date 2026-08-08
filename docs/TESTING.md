# Testing — guía del proyecto

Este documento junta en un solo lugar lo que hoy está disperso entre `README.md` (solo comandos),
`docs/INSTRUCCIONES_fase5_playwright.md` (solo e2e) y los docblocks de cada archivo de test: **qué
es cada suite, para qué sirve, qué cubre y cuándo correrla.**

Hay **4 suites de test** en el proyecto, repartidas entre backend y frontend, más 2 checks
estáticos (typecheck, lint) que no son tests pero suelen correr junto con ellos.

| Suite | Motor | Dónde | Comando | Tests |
|---|---|---|---|---|
| Unit (backend) | PHPUnit | raíz del repo | `composer test` | 128 |
| Integration (backend) | PHPUnit + servidor PHP embebido | raíz del repo | `composer test:integration` | 167 |
| Unit (frontend) | Vitest | `frontend/` | `npm run test` | 92 |
| E2E (frontend) | Playwright | `frontend/` | `npm run test:e2e` | 5 |

---

## 1. Backend — Unit (`composer test`, PHPUnit, `tests/Unit/`)

**Qué son:** funciones/métodos **puros** — sin conexión a MySQL, sin llamadas a Google Drive, sin
red. Se testean directo contra la clase, vía el autoload PSR-4 de Composer. Son las más rápidas y
no requieren nada levantado (ni XAMPP, ni BD).

**Para qué sirven:** blindar la lógica de negocio/cálculo (parseo de PDF/CSV, fórmulas de
valoración, escalas de color) contra regresiones, sin pagar el costo de un servidor+BD real en cada
corrida.

**128 tests en 12 archivos**, agrupados por indicador/área:

### `Almacenamiento/`
- **`AlmacenamientoLocalServiceTest.php`** (11 tests) — el servicio que guarda evidencia en disco
  local (alternativa a Drive, interruptor por carrera). Cubre: creación del árbol
  carrera/cohorte/PAO/asignatura, reemplazo de contenido al subir dos veces, descarga de contenido
  ya subido, sanitización de segmentos de ruta peligrosos, validación de tipo/tamaño de PDF y XLSX
  (incluye rechazo de XLSX >25MB y de mime inválido). Usa una raíz temporal aislada por test, nunca
  toca `storage/evidencias/` real.
- **`EvidenciaStorageResolverTest.php`** (4 tests) — decide si una URL de evidencia apunta a Google
  Drive o a almacenamiento local, solo por la forma de la URL (http/https vs ruta local, incluida
  ruta estilo Windows). La otra mitad del resolver (que sí consulta `carreras.modo_almacenamiento`
  en BD) se cubre en integración, no acá.

### `GoogleDrive/`
- **`GoogleDriveCarpetasTest.php`** (5 tests) — construcción del árbol de carpetas en Drive
  (Carrera → Cohorte, anidado), obtener-o-crear carpeta, y escape de caracteres especiales en
  queries de búsqueda de Drive. Mockea el recurso `files` de la librería de Google, sin red real.
- **`GoogleDriveClienteAutorizadoTest.php`** (6 tests) — manejo del token OAuth: detecta token
  ausente/sin `access_token`, no renueva si sigue vigente, renueva con `refresh_token` si venció,
  lanza excepción clara si no hay refresh token o si Google devuelve error al renovar.
- **`GoogleDriveClienteFactoryTest.php`** (4 tests) — construcción del cliente de Google (scope
  `drive.file`, redirect URI real, uso del archivo de credenciales por variable de entorno,
  excepción si el archivo de credenciales no existe). Usa un fixture de credenciales de prueba.
- **`GoogleDriveServiceTest.php`** (2 tests) — que la subida de archivo al catálogo (I1/I4/I5) use
  el seam de `APP_ENV=testing` (sin pegarle a Drive real) y el mime-type PDF por defecto.

### `SeguimientoSyllabus/` (I2)
- **`CalculoTest.php`** (2 tests, con data provider) — `calcularEscala()` (mapeo % → etiqueta/color)
  y las 8 etiquetas de tipo de evidencia. Solo la parte sin mysqli de
  `SeguimientoSyllabusCalculoService`; el cálculo de resultado por asignatura/general (que sí toca
  BD) se cubre en integración.
- **`EncuestaCalculoTest.php`** (13 tests) — parseo del CSV de encuesta (comillas, comas internas,
  CSV vacío), detección de columnas de pregunta (que P1 no confunda con P10), extracción de texto de
  pregunta entre corchetes, y el cálculo de EF1-EF5 desde las filas parseadas (incluye caso vacío,
  sin filas de datos, y valores fuera del mapa de puntaje). Existe específicamente para blindar
  contra un bug real que ya ocurrió en producción (código que asumía 3 dígitos donde a veces hay
  menos).

### `TasaDesercion/` (I4) y `TasaTitulacion/` (I5)
- **`CalculoTest.php`** de cada uno (8 y 6 tests) — extracción de datos desde texto de PDF: detectar
  el total reportado explícitamente, reconocer variantes de frase ("Total de estudiantes:", etc.),
  respaldo contando cédulas únicas de 10 dígitos si no hay total explícito, excepción si no se
  detecta ningún estudiante, detección de cohorte y período en distintos formatos. I5 además
  distingue frases de "matriculados" vs "graduados" (no deben cruzarse entre sí).

### `TutoriasAcademicas/` (I3)
- **`CalculoTest.php`** (11 tests) — cálculo de porcentaje por puntos cumplidos sobre distintas
  bases (2, 3, 4 puntos posibles, con fallback genérico), casos límite (0 cumplidos, cumplidos
  negativo), `calcularEscala()`, que los pesos de EF sumen 1, y consistencia entre los mapas de
  tipo↔EF.
- **`CsvParserTest.php`** (9 tests) — parseo de CSV de tutorías en CP850 (no latin-1, confirmado
  byte a byte), extracción tolerante a typos del nombre de asignatura desde el título, y el cálculo
  de cumplimiento de EF1 (planeación, con contexto de cohorte/PAO), EF2 (horas, fracción completa
  vs incompleta) y EF3 (credenciales, cruzado contra la BD).

---

## 2. Backend — Integration (`composer test:integration`, PHPUnit, `tests/Integration/`)

**Qué son:** tests que levantan un **servidor PHP real embebido** (`php -S`) y le pegan por HTTP a
los endpoints tal cual los usa el frontend — no mockean nada del lado del framework. Corren contra
una BD MySQL/MariaDB de prueba real (`evaluacion_caces_test`), migrada y sembrada con Phinx antes de
cada corrida (`phinx migrate -e testing` + `phinx seed:run -e testing`, mismos 8 seeders que usa el
equipo en local). El servidor se levanta con `APP_ENV=testing`, que activa "seams" para no llamar a
Google Drive real durante la subida de archivos.

**Para qué sirven:** validar contrato HTTP completo de cada endpoint — status codes, validación de
input, autenticación/autorización por rol, y persistencia real en BD — sin necesidad de haber
migrado antes todo el backend a un framework con inyección de dependencias.

**Requiere:** PHP + MySQL alcanzables con las credenciales de la sección `testing` de `phinx.php`
(por defecto root sin contraseña en localhost). Sin BD disponible, fallan con mensaje claro en vez
de error críptico.

**167 tests en 30 archivos**, agrupados por área:

### `Auth/` — login, logout, sesión (13 tests)
- **`LoginTest.php`** (5): credenciales válidas devuelven usuario+sesión; contraseña incorrecta y
  correo inexistente devuelven 401; sin correo/contraseña 400; método GET 405.
- **`LogoutTest.php`** (3): destruye la sesión (confirmado con `/me` después), funciona incluso sin
  sesión activa, método GET 405.
- **`MeTest.php`** (4): 401 sin sesión, devuelve el usuario logueado con sesión, 401 si el usuario
  fue desactivado *después* de loguearse, método POST 405.

### `Administracion/` — gestión de usuarios y cohortes (43 tests)
- **`UsuariosCrearTest.php`** (10), **`UsuariosListarTest.php`** (5), **`UsuariosCambiarEstadoTest.php`**
  (10): CRUD de usuarios con control de rol (solo administrador), validación de campos (rol
  inválido, correo inválido, contraseña corta, correo duplicado → 409), reglas de negocio
  específicas (nadie puede desactivar su propia cuenta, pero sí puede reactivarse a sí mismo).
- **`CohortesCrearTest.php`** (8), **`CohortesListarTest.php`** (3), **`CohortesCambiarEstadoTest.php`**
  (8): creación de cohorte + su evaluación asociada, normalización de nombre (minúsculas
  descartadas), validación de fechas (fin antes que inicio → 400), carrera inexistente → 404.

### `Carreras/` — `AlmacenamientoTest.php` (6 tests)
Migración del modo de almacenamiento de una carrera (Drive ↔ local): control de rol (solo
administrador/coordinador), migración exitosa actualiza carrera y evidencia, migrar al mismo modo
sin cambios → 422, archivo de origen inexistente revierte la migración → 422.

### `Catalogo/` — `ObtenerEvidenciasTest.php` (6 tests)
Catálogo de tipos de evidencia por indicador: lista vacía si el indicador no tiene catálogo, orden
correcto, excluye catálogo inactivo.

### `Evaluaciones/` — `ObtenerEvaluacionTest.php` (7 tests)
Resolución de evaluación por carrera+cohorte (la que dispara los fallos e2e de la sesión anterior si
no hay datos): 404 si no existe, insensible a mayúsculas/espacios en la cohorte, devuelve la más
reciente si hay varias.

### `EvidenciaAsignatura/` — `VerTest.php` (6 tests)
Ver contenido de una evidencia puntual de asignatura (I2), tanto en local como en Drive (vía seam de
testing), 404 si el id no existe.

### `Evidencias/` — genéricas de I1/I4/I5 (30 tests)
- **`CompartidasTest.php`** (6) / **`GuardadasTest.php`** (5): listado de evidencias ya guardadas o
  compartidas entre indicadores, cruzadas con su catálogo/indicador de origen.
- **`GuardarTest.php`** (6): inserta evidencia y la relaciona con su indicador de origen; si hay
  regla de compartición activa, comparte automáticamente; misma evaluación+catálogo actualiza en vez
  de duplicar.
- **`LeerMatriculadosTest.php`** (6): extracción de matriculados/período/cohorte desde PDF real,
  incluidas variantes de frase de "total"; respaldo por cédulas únicas.
- **`PrepararPdfTest.php`** (7): validación de PDF (o CSV para el slot `DOC.SEG.05`), generación de
  nombre técnico, catálogo inexistente → 404.

### `GoogleDrive/` — OAuth y subida de archivo (23 tests)
- **`CallbackTest.php`** (7): callback de OAuth vía seam de testing, escribe `token.json`, conserva
  el refresh token anterior si Google no lo repite, no requiere sesión.
- **`ConectarTest.php`** (3): redirect 302 hacia Google vía seam de testing.
- **`SubirArchivoTest.php`** (8): subida exitosa en local (árbol de 5 niveles) y en Drive (vía
  seam), validación de extensión.
- **`VerArchivoTest.php`** (8): igual a `EvidenciaAsignatura/VerTest.php` pero para el archivo
  genérico de catálogo.

### `SeguimientoSyllabus/` — I2 completo (26 tests)
El indicador más grande del proyecto — cohorte → período → asignatura → evidencia → resultado:
- **`AsignaturaCrearTest.php`** (4) / **`AsignaturasListarTest.php`** (3) / **`PeriodosTest.php`**
  (3): CRUD de la malla curricular real (Parte del plan de malla curricular xlsx).
- **`EvidenciaAsignaturaSubirTest.php`** (5): subida de evidencia (usa el seam de Drive), la subida
  nueva marca la anterior como no vigente.
- **`EvidenciaListarTest.php`** (3): los 4 tipos de evidencia con flag de subido/no subido.
- **`EncuestaDetalleTest.php`** (4): detalle de encuesta EF1/EF4 parseado desde CSV cacheado, 502 si
  no hay CSV subido.
- **`ResultadoAsignaturaTest.php`** (5) / **`ResultadoCohorteTest.php`** (4): el cálculo final de
  resultado (el que consume `TabResultsI2.tsx` en el frontend), incluido que se guarde el snapshot
  en `resultados_seguimiento`.

---

## 3. Frontend — Unit (`npm run test`, Vitest, `src/**/*.test.{ts,tsx}`)

**Qué son:** tests de componentes React (con React Testing Library) y funciones puras de utilidades
del frontend. Corren en Node/jsdom, sin backend ni red real.

**Para qué sirven:** blindar comportamiento visual/lógico de piezas reutilizables (badges de estado,
anillos de progreso, semáforos, tarjetas de indicador) y de utilidades de parseo/cálculo que corren
en el navegador (CSV, malla curricular xlsx, generación de PDF del lado del cliente).

**92 tests en 13 archivos:**

- **`Breadcrumb.test.tsx`** (6) — orden de ítems, separadores, ítem "actual" resaltado.
- **`IndCard.test.tsx`** (5) — tarjeta de indicador: % agregado sumando cohortes (no promediando),
  "Sin datos" si 0 matriculados, badge de estado según %, `onClick`.
- **`PaoGroupCard.test.tsx`** (7) — tarjeta con un botón por PAO: caso especial I1 (fuerza "Sin
  datos" en los 3 PAO aunque haya datos), resto de indicadores usa datos reales.
- **`EvidenceHeader.test.tsx`** (5) — título, subtítulo opcional, botón de volver (`type="button"`,
  no dispara submit).
- **`SemLight.test.tsx`** (7) / **`Ring.test.tsx`** (9) — semáforo y anillo de progreso: cortes
  exactos de color (75/50/25%), tamaño configurable, `strokeDasharray` proporcional al %.
- **`PdfZone.test.tsx`** (9) — zona de subida de archivo: valida PDF o CSV según `acceptedType`,
  rechaza tipo incorrecto, deshabilitado mientras carga, "Evidencia compartida" cuando viene de otro
  indicador.
- **`carreras.test.ts`** (6) — validación de malla curricular XLSX antes de subir: rechaza PDF,
  rechaza sin extensión .xlsx, acepta por mime aunque falte extensión y viceversa, rechaza >25MB.
- **`exportarPdfIndicador2.test.ts`** (16) — funciones puras del generador de PDF de I2 en cliente
  (jsPDF): conversión hex→RGB, mapeo de valor a estado (nodata/ok/cuasi/poco/def) en los cortes
  exactos, opción dominante de encuesta con desempate y redondeo.
- **`asignaturas.test.ts`** (7) — matching de nombre de asignatura tolerante a mayúsculas/espacios,
  sin match parcial por substring.
- **`csv.test.ts`** (6) — parser CSV genérico: comillas, comas internas, comillas escapadas, saltos
  de línea dentro de campo.
- **`mallaCurricular.test.ts`** (8) — parseo del Excel de malla curricular real (SheetJS): detecta
  los 3 PAO, cuenta total de asignaturas, excluye "Práctica Laboral"/"Servicio Comunitario", error
  descriptivo si el layout no coincide.
- **`StepConfigSyllabus.test.tsx`** (3) — cohortes/materias reales por carrera (Parte G/3.5, el
  reemplazo del mock de `academic.ts`): filtra cohortes por `codigo_carrera`, materias por módulo,
  limpia materias al cambiar de módulo.

---

## 4. Frontend — E2E (`npm run test:e2e`, Playwright, `frontend/e2e/*.spec.ts`)

**Qué son:** los únicos tests que corren contra el **entorno real completo** — backend en XAMPP,
MySQL real, frontend levantado por Playwright (Vite, puerto 5173) — no una BD ni backend aislados de
prueba. Documentados con más detalle en `docs/INSTRUCCIONES_fase5_playwright.md`.

**Para qué sirven:** confirmar que los 4 flujos críticos de usuario funcionan de punta a punta tal
como los usaría alguien real, incluida la integración real con Google Drive para evidencia.

**Requiere:** backend arriba (XAMPP), usuario demo válido en BD (`administrador@demo.local` por
defecto, overridable en `frontend/e2e/.env.e2e`), al menos una carrera con datos reales cargados
(cohorte + evaluación + malla + evidencia), credenciales reales de Google Drive configuradas.

**5 tests en 4 archivos:**

- **`login.spec.ts`** (2) — login con credenciales válidas llega a "Carreras"; con credenciales
  inválidas muestra error y no navega.
- **`ver-resultado-i2.spec.ts`** (1) — login → carrera → Docencia → Dashboard → primer PAO de la
  card de I2 → confirma que carga la vista de resultado (título, panel "Asignaturas", % o "—").
- **`exportar-pdf-i2.spec.ts`** (1) — misma navegación, exporta el PDF (generado 100% en cliente vía
  jsPDF), confirma la descarga y que el archivo empieza con la cabecera `%PDF-`. Se salta (`test.skip`)
  si no hay ninguna asignatura con resultado para exportar en esa carrera/cohorte/PAO.
- **`subir-evidencia-i2.spec.ts`** (1) — sube el slot "Normativa Institucional" de I2 (evidencia a
  nivel evaluación, no de asignatura): configura período (PAO/módulo/materia reales, vía
  `StepConfigSyllabus`), sube el PDF (input real oculto tras un botón), espera éxito o error
  explícito, confirma el guardado final. Sube de verdad a Google Drive y a la BD real de desarrollo
  — no es una BD de prueba aislada.

---

## 5. Checks estáticos (no son tests, pero se corren junto con ellos)

| Comando | Qué hace |
|---|---|
| `npm run typecheck` | `tsc --noEmit` sobre el código de la app + `tsconfig.e2e.json` sobre los specs de e2e. Solo verifica tipos, no ejecuta nada. |
| `npm run typecheck:e2e` | Igual, pero solo la parte e2e. |
| `npm run lint` | ESLint sobre todo `frontend/`. Baseline conocida: **0 errores, 35 warnings preexistentes** (`react-hooks/set-state-in-effect`, `exhaustive-deps`, `no-unused-vars`, `no-explicit-any`, `react-refresh/only-export-components` — bajadas a `warn` a propósito, documentado en `eslint.config.js`; no bloquean, pero no deberían subir de número con cambios nuevos). |

---

## 6. Correr todo de una

```bash
# Backend, desde la raíz del repo (requiere BD de testing migrada/sembrada)
composer test               # Unit — 128 tests, sin BD/red
composer test:integration   # Integration — 167 tests, requiere MySQL

# Frontend, desde frontend/
npm run typecheck            # tipos
npm run lint                 # estilo (0 errores, 35 warnings esperados)
npm run test                 # Unit (Vitest) — 92 tests, sin backend
npm run test:e2e             # E2E (Playwright) — 5 tests, requiere XAMPP+MySQL+Drive reales
```
