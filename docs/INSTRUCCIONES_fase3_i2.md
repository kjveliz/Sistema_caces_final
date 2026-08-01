# Fase 3 (POC) — I2 migrado a Slim Framework

Segundo indicador migrado a la arquitectura en capas de la Fase 3 del Plan
de Mejora: `Controllers/ → Services/ → Repositories/ → DTOs/`, detrás del
mismo `public/index.php` con Slim que ya usaba I3. I2 es bastante más
grande que I3 (8 endpoints reales + integración de Drive con descarga y
caché, no solo subida) — ver el detalle abajo.

**A diferencia de la migración de I3, en esta sí hubo PHP disponible en el
sandbox** (`apt-get install php-cli php-mysqli php-mbstring php-curl`,
domino `archive.ubuntu.com` permitido) — todo el PHP nuevo pasó `php -l`
sin errores, y la lógica pura (escalas, etiquetas, `calcularEfDesdeFilas`,
`buscarColumnasPregunta`, `textoPregunta`, `parseCsvString`,
`calcularResultadoAsignatura` con repositorio/encuesta fake) se verificó
manualmente contra los mismos casos que ya cubrían
`tests/Unit/SeguimientoSyllabus/{CalculoTest,EncuestaCalculoTest}.php` y
`tests/Integration/SeguimientoSyllabus/ResultadoAsignaturaTest.php` — todos
dieron el resultado esperado. **Lo que sigue sin poder correr acá es
`composer install` (sin acceso a `packagist.org`) y cualquier cosa que
necesite MySQL/MariaDB real o credenciales de Google Drive** — eso queda
pendiente de confirmar en tu máquina.

## 1. Qué cambió

- **Nuevo:**
  `src/Controllers/SeguimientoSyllabusController.php`,
  `src/Services/{SeguimientoSyllabusCalculoService,EncuestaCalculoService,EncuestaEvidenciaService}.php`,
  `src/Repositories/SeguimientoSyllabusRepository.php`,
  `src/DTOs/{ResultadoAsignaturaSeguimientoDTO,ResultadoCohorteSeguimientoDTO,EvidenciaSeguimientoItemDTO}.php`,
  `tests/Integration/router-testing.php`.
- **Eliminado** (reemplazado 1:1 por lo de arriba, misma lógica), toda la
  carpeta `api/seguimiento_syllabus/`: `_calculo.php`, `_encuesta.php`,
  `_google_drive.php`, `_helpers.php`, `asignaturas.php`,
  `encuesta_detalle.php`, `evidencia_asignatura_listar.php`,
  `evidencia_asignatura_subir.php`, `periodos.php`, `resultado_asignatura.php`,
  `resultado_cohorte.php`.
- **`materias_encuesta.php` NO se migró — se eliminó directamente.** Ya
  estaba deprecado (devolvía una lista vacía sin lógica real, ver MEMORIA
  v18) y confirmé con `grep` que ningún archivo del frontend lo llama.
- **`src/Services/GoogleDriveService.php` (extendido, no solo movido):**
  hasta ahora era un wrapper delgado que hacía `require_once` de
  `api/seguimiento_syllabus/_google_drive.php` (I3 lo necesitaba pero no
  quiso tocar ese archivo, que era de I2 y todavía no se había migrado). Esa
  lógica (subida, validación PDF) ahora vive DENTRO de la clase, y se
  agregan 2 métodos nuevos que I2 necesita y I3 no usa:
  `validarCsv()` (el slot `encuesta_csv` sube CSV, no PDF) y
  `descargarContenidoDrive()` (I2 necesita leer de vuelta ese CSV para
  calcular EF1/EF4, algo que I3 nunca necesitó). Los métodos que ya usaba
  I3 (`subirArchivo`, `validarArchivoSubido`) mantienen exactamente la misma
  firma — I3 no debería notar el cambio.
- **`public/index.php`:** ahora registra las rutas de I2 además de las de
  I3 (mismo archivo, ambos indicadores conviven). Además, el `basePath` de
  Slim ahora es configurable vía `APP_BASE_PATH` (por defecto
  `/sistemacaces/public`, igual que antes) — esto resuelve el pendiente que
  había quedado abierto en la migración de I3 (MEMORIA v64/§41.1) y permite
  que los tests de integración le peguen a Slim.
- **`tests/Integration/IntegrationTestCase.php` +
  `tests/Integration/router-testing.php` (nuevo):** el servidor embebido de
  PHP (`php -S`) ahora arranca con un router script que sirve archivos
  reales tal cual (ej. `api/auth/Login.php`, que sigue sin migrar) y delega
  todo lo demás a `public/index.php` (Slim), con `APP_BASE_PATH=''` para el
  entorno de testing. Sin esto, los tests de integración de I2/I3 no podían
  pegarle a ninguna ruta de Slim.
- **Los 2 tests de integración de I2 que YA EXISTÍAN antes de esta
  migración** (`ResultadoAsignaturaTest.php`, `EvidenciaAsignaturaSubirTest.php`
  — de la Fase 5, apuntaban directo a los `.php` sueltos) se actualizaron
  para pegarle a las rutas nuevas de Slim. Mismos asserts, sin cambios de
  comportamiento esperado.
- **Los 2 tests unitarios de I2 que YA EXISTÍAN** (`CalculoTest.php`,
  `EncuestaCalculoTest.php`) se actualizaron para llamar a
  `SeguimientoSyllabusCalculoService::` / `EncuestaCalculoService::` (ahora
  estáticos) en vez de `require_once` + funciones sueltas. Mismos asserts.
- **`frontend/src/services/seguimientoSyllabus.ts`:** cambia la constante
  `BASE` (de `api/seguimiento_syllabus` a `public/seguimiento-syllabus`) y
  los 7 nombres de endpoint (snake_case `.php` → kebab-case sin extensión).
  Las funciones exportadas tienen la misma firma de siempre.

## 2. Un bug corregido de paso (mismo patrón que ya se encontró en I3)

`evidencia_asignatura_listar.php` original consultaba
`evidencia_asignatura` filtrando solo por `id_asignatura` y `vigente=1`,
**sin filtrar por tipo** — igual que el bug que ya se había encontrado y
corregido al migrar I3 (esa tabla es compartida entre indicadores: I3 la
usa con sus propios 4 tipos). `SeguimientoSyllabusRepository::evidenciasVigentesPorAsignatura()`
ahora sí filtra explícitamente por los 4 tipos de I2
(`SeguimientoSyllabusCalculoService::TIPOS_POR_ASIGNATURA`), para no
arrastrar filas de otros indicadores.

## 3. Qué NO cambió (a propósito)

- Todas las fórmulas (EF1 = promedio de 5 componentes / 5, pesos
  0.33/0.27/0.20/0.13/0.07, escala por rangos de 25%, TTL de caché del CSV
  de 60s, criterio exacto de degradación si Drive falla) son puerto 1:1.
- La integración con `api/google_drive/drive_helpers.php` y
  `cliente_autorizado.php` no se tocó — sigue siendo la base compartida,
  fuera de alcance.
- I1, I4, I5 siguen exactamente igual, como archivos `.php` sueltos.

## 4. Instalación

```bash
composer install    # no agrega dependencias nuevas respecto a I3 (ya estaban slim/slim, slim/psr7)
```

## 5. Verificar que responde

Con Apache/MySQL corriendo (mismo `AllowOverride All` que ya se configuró
para I3 en `public/`, ver `INSTRUCCIONES_fase3_i3.md` §4):

```bash
curl "http://localhost/sistemacaces/public/seguimiento-syllabus/resultado-cohorte?id_cohorte=1&id_evaluacion=1"
```

Debería devolver el mismo JSON `{ok, mensaje, datos}` que devolvía antes
`api/seguimiento_syllabus/resultado_cohorte.php` con los mismos parámetros.

## 6. Correr los tests

```bash
composer test               # unitarios (incluye CalculoTest/EncuestaCalculoTest ya migrados)
composer test:integration   # requiere MySQL/MariaDB real, ver IntegrationTestCase.php
```

Los tests de integración de I2 y I3 ahora deberían poder correr contra
Slim gracias al router-testing.php nuevo — esto no se pudo confirmar en
vivo en este sandbox (sin MySQL disponible), es lo primero a validar.

## 7. Qué falta (fuera de alcance de esta entrega)

- Tests nuevos para el resto del `SeguimientoSyllabusController` (solo
  había integración previa para `resultado-asignatura` y `evidencia-subir`;
  `periodos`, `asignaturas`, `resultado-cohorte`, `evidencia-listar`,
  `encuesta-detalle` quedan sin test de integración propio).
- Documentación OpenAPI de las rutas de I2+I3.
- Migrar I1, I4, I5 con el mismo patrón.
- Middleware de CORS compartido para el resto de archivos sueltos que
  todavía repiten el bloque (I1, I4, I5) — sigue fuera de alcance.
