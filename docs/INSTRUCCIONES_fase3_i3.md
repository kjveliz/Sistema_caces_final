# Fase 3 (POC) — I3 migrado a Slim Framework

Primer indicador migrado de "archivo PHP suelto" a la arquitectura en capas
que pide la Fase 3 del Plan de Mejora: `Controllers/ → Services/ →
Repositories/ → DTOs/`, detrás de un único punto de entrada
(`public/index.php`) con Slim Framework, en vez de 6 archivos accesibles
directo por URL.

**Importante — esto NO se pudo instalar ni correr en el sandbox de Claude**
(sin acceso a `packagist.org`, y sin PHP instalado siquiera para correr
`php -l`). A diferencia de otras sesiones, ni la sintaxis se pudo verificar
acá — todo lo de abajo hay que confirmarlo en tu máquina real antes de dar
esto por cerrado.

## 1. Qué cambió

- **Nuevo:** `src/Controllers/TutoriasAcademicasController.php`,
  `src/Services/{TutoriasCalculoService,TutoriasValidacionPdfService,GoogleDriveService}.php`,
  `src/Repositories/TutoriasRepository.php`,
  `src/DTOs/{ResultadoAsignaturaTutoriasDTO,ResultadoCohorteTutoriasDTO,EvidenciaTutoriasItemDTO}.php`,
  `src/Middleware/{CorsMiddleware,SessionAuthMiddleware}.php`,
  `src/Infra/Database.php`, `public/index.php`, `public/.htaccess`.
- **Eliminado** (reemplazado 1:1 por lo de arriba, misma lógica):
  `api/tutorias_academicas/_calculo.php`, `_validacion_pdf.php`,
  `evidencia_listar.php`, `evidencia_subir.php`, `resultado_asignatura.php`,
  `resultado_cohorte.php`. Confirmé que ningún otro archivo del backend
  dependía de estos 6 (`grep -rl` sobre todo `api/` antes de tocar nada).
- **`composer.json`:** se agregan `slim/slim` y `slim/psr7` a `require`, y
  un autoload PSR-4 `App\` → `src/`.
- **`frontend/src/services/tutoriasAcademicas.ts`:** solo cambia la
  constante `BASE` y los 4 nombres de endpoint (snake_case `.php` → kebab-case
  sin extensión). Las funciones exportadas (`obtenerEvidenciaTutorias`,
  `subirEvidenciaTutorias`, etc.) tienen la misma firma de siempre — nada
  más en el frontend debería necesitar cambios.

## 2. Qué NO cambió (a propósito)

- La lógica de negocio de I3 (fórmulas de `%`, regex de validación de PDF,
  regla de "EF2 se topa a 100% si iguala EF1", forma exacta del JSON de
  respuesta) es un puerto 1:1 — no se reescribió nada de eso.
- La integración con Google Drive (`api/seguimiento_syllabus/_google_drive.php`)
  NO se tocó ni se duplicó — `GoogleDriveService` es un wrapper de clase
  fino sobre esas mismas funciones globales, compartidas con I2.
- Los otros 4 indicadores (I1, I2, I4, I5) siguen exactamente igual que
  antes, como archivos `.php` sueltos. Fase 3 se hace indicador por
  indicador, como recomienda el plan.

## 3. Instalación

```bash
composer install          # instala slim/slim y slim/psr7 nuevos
```

Si `composer install` da conflictos de versión con `google/apiclient` u
otra dependencia existente, probablemente haga falta ajustar el rango de
versión de `slim/slim`/`slim/psr7` en `composer.json` — no pude probar la
resolución de dependencias real acá.

## 4. Configurar Apache (XAMPP) — paso nuevo, no existía antes

Slim necesita que las peticiones a `public/*` (salvo archivos reales) se
reescriban hacia `public/index.php`. Eso lo hace `public/.htaccess`, pero
**solo funciona si `AllowOverride All` está habilitado** para esa carpeta
en la configuración de Apache — por defecto XAMPP suele traer
`AllowOverride None` para `htdocs`. Si `resultado-cohorte` u otras rutas
dan 404 en vez de JSON, este es el primer sospechoso.

En `httpd.conf` (o el `.conf` del vhost que uses), algo como:

```apache
<Directory "C:/xampp/htdocs/sistemacaces/public">
    AllowOverride All
    Require all granted
</Directory>
```

(ajustá la ruta a donde tengas el repo real). Reiniciá Apache después del
cambio.

## 5. Verificar que responde

Con Apache/MySQL corriendo y `composer install` hecho:

```bash
curl "http://localhost/sistemacaces/public/tutorias-academicas/resultado-cohorte?id_cohorte=1&id_evaluacion=1"
```

Debería devolver el mismo JSON `{ok, mensaje, datos}` que devolvía antes
`api/tutorias_academicas/resultado_cohorte.php` con los mismos parámetros —
es un buen primer chequeo de que el router y la conexión a BD están bien,
antes de probar contra el frontend real.

## 6. Qué falta (fuera de alcance de esta entrega)

- Tests para el `TutoriasAcademicasController`/servicios nuevos (I3 no
  tenía tests de PHPUnit ni antes de esta migración — se puede agregar en
  una próxima sesión, siguiendo el patrón de Fase 5).
- Documentación OpenAPI de estas 4 rutas (lo pide el criterio de "hecho" de
  Fase 3, pero recién tiene sentido generarlo cuando haya más de un
  indicador migrado).
- Migrar I2, I4, I5, I1 con el mismo patrón.
- El middleware de CORS compartido para los otros 27 archivos que todavía
  repiten el bloque — quedó fuera de esta entrega (se decidió explícitamente
  "solo I3" para esta sesión).
