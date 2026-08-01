# Fase 2 — Migraciones versionadas con Phinx — cómo aplicar

El patch trae **1 commit** (sobre `20af7f68`, el cierre de la Fase 1). Mismo patrón de siempre: yo no
tengo escritura sobre el repo real, armé esto sobre un clon temporal y te lo entrego para que lo apliques.

**Diferencia importante con las Fases 0 y 1:** esta vez no pude correr `phinx migrate` de punta a punta
en mi entorno (no tengo acceso a Packagist para instalar Phinx vía Composer, ni una base MySQL corriendo).
Sí verifiqué que los 10 archivos PHP nuevos pasan `php -l` sin errores de sintaxis. La primera corrida real
tiene que hacerse en tu máquina — y el propio plan pide que sea contra una **base de datos de prueba vacía**,
no contra tu `evaluacion_caces` con datos reales. Seguí los pasos en orden, no te saltees el paso 4.

## 1. Aplicar el patch

Desde la raíz de tu checkout local, con la rama `developer` al día en `20af7f68` (o más nuevo):

```bash
git log --oneline -1        # confirmá que estás en 20af7f68 o más nuevo
git apply --stat fase2_plan_mejora.patch      # solo para ver qué toca, no aplica nada
git am fase2_plan_mejora.patch                # aplica el commit tal cual
```

**Importante — de nuevo:** guardá `fase2_plan_mejora.patch` y este archivo de instrucciones **fuera** de
la carpeta del repo antes de correr `git add -A`.

## 2. Instalar Phinx

```bash
composer require --dev robmorgan/phinx
```

(Ya quedó declarado en `composer.json` por el patch — este comando resuelve la versión exacta y regenera
`composer.lock`, igual que pasó con `vlucas/phpdotenv` en la Fase 0.)

## 3. Crear una base de datos de prueba vacía (NO uses `evaluacion_caces`)

En phpMyAdmin o por línea de comandos, creá una base nueva y vacía llamada exactamente
`evaluacion_caces_test` (ese nombre ya está fijo en el entorno `testing` de `phinx.php`, mismo
host/usuario/clave que tu `.env` normal). No hace falta editar tu `.env` ni crear un `.env.test`
aparte — el entorno `testing` apunta solo a esa base, sin tocar `evaluacion_caces`.

Corré Phinx indicando ese entorno:

```bash
vendor/bin/phinx migrate -e testing
```

## 4. Verificar en vivo — este es el criterio de "hecho" del plan

```bash
vendor/bin/phinx migrate -e testing
vendor/bin/phinx seed:run -e testing
```

Con la base de prueba vacía, esperado:
- `phinx migrate` crea las 20 tablas, sin errores.
- `phinx seed:run` inserta los datos de los 8 seeders, sin errores de FK.
- Entrá a la base con phpMyAdmin y confirmá que las 20 tablas están, con sus foreign keys, y que hay datos
  en `carreras` (3), `indicadores` (5), `usuarios` (3), `cohortes` (2), `catalogo_evidencias` (20),
  `periodo_academico` (3), `asignatura` (5), `evaluaciones` (2).

Si algo falla acá, **no sigas** — pegame el error completo (el mensaje de Phinx suele decir exactamente qué
tabla/columna/constraint no le gustó) y lo corrijo antes de que toques tu base real.

## 5. Si todo salió bien, probá el rollback (opcional pero recomendado)

```bash
vendor/bin/phinx rollback -e testing
```

Debería borrar las 20 tablas en el orden inverso correcto, sin errores. Esto confirma que el `change()` de
la migración es reversible de verdad, no solo que "crea" bien.

## 6. Qué NO se tocó en esta fase

- Tu base de datos real (`evaluacion_caces`) — la migración y los seeders solo corren contra la base que
  vos elijas apuntando `DB_NAME`, nunca se ejecutan solos.
- Ningún archivo de `api/` ni `frontend/` — la Fase 2 es pura infraestructura de BD.
- El SQL viejo en `sql/` (las migraciones ad-hoc de I2/I3/I4 de sesiones anteriores) — esos quedan como
  registro histórico de cómo se hicieron esos cambios antes de tener Phinx. De acá en adelante, todo cambio
  de esquema nuevo va como migración de Phinx, no como `.sql` suelto en esa carpeta.

## 7. Una vez confirmado en la base de prueba: push

```bash
git log --oneline -2   # confirmá el commit nuevo arriba de 20af7f68
git push
```

## 8. Pendiente para más adelante (fuera del alcance de esta fase)

- Migrar tu base de datos real (`evaluacion_caces`, con todos tus datos actuales) para que quede bajo
  control de Phinx sin perder nada, es un paso aparte y más delicado — normalmente se hace marcando la
  migración inicial como "ya aplicada" (`phinx migrate` tiene un flag para eso) sin volver a crear las
  tablas que ya existen. Lo dejamos para cuando confirmes que la Fase 2 funciona bien en limpio.
- Actualizar `README.md` con instrucciones de "cómo levantar el entorno desde cero" usando Phinx — eso es
  parte de la Fase 7 del plan (documentación), no de esta.
