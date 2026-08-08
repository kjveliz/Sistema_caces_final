# Sistema de Gestión de Evidencias CACES

Sistema web para apoyar el proceso de evaluación institucional del **Modelo de Evaluación
CACES**. Centraliza la carga, organización y evaluación automática de evidencias para 5
indicadores del criterio Docencia, y permite administrar múltiples carreras (no solo Desarrollo
de Software).

Al crear (o editar) una carrera, el sistema parsea la **malla curricular en Excel** subida por el
usuario y genera automáticamente la cohorte, los 3 períodos académicos (PAO) y las asignaturas
reales de cada uno (con su módulo A/B/C), reemplazando la carga manual asignatura por asignatura.
Las evidencias de cada indicador pueden almacenarse en **Google Drive o localmente**, configurable
por carrera.

## Indicadores cubiertos

| # | Indicador | Tipo | Evidencia |
|---|---|---|---|
| I1 | Syllabus (Malla Curricular) | — | Documento de malla curricular por carrera |
| I2 | Seguimiento de Syllabus | Cuantitativo | Encuesta (CSV) + evidencias de cumplimiento por asignatura |
| I3 | Tutorías Académicas | Cualitativo | Planeación/Cumplimiento/Seguimiento (CSV) + Normativas (PDF), por asignatura |
| I4 | Tasa de Deserción | Cuantitativo | Datos por cohorte + evidencia (PDF) |
| I5 | Tasa de Titulación | Cuantitativo | Datos por cohorte + evidencia (PDF) |

Cada indicador calcula automáticamente un porcentaje de cumplimiento y una escala
(Satisfactorio / Cuasi Satisfactorio / Poco Satisfactorio / Deficiente) a partir de las
evidencias cargadas, agregando resultados por asignatura y por cohorte/período académico.

---

## Tecnologías

**Backend**
- PHP 8.3
- [Slim Framework 4](https://www.slimframework.com/) (`slim/slim`, `slim/psr7`) — enrutamiento y middleware de la parte migrada del backend
- MySQL / MariaDB
- [Phinx](https://book.cakephp.org/phinx/) — migraciones y seeders versionados de base de datos
- [PHPUnit 11](https://phpunit.de/) — tests unitarios y de integración
- `google/apiclient` — subida de evidencias a Google Drive
- `smalot/pdfparser` — extracción de texto de PDF para validación automática de evidencias
- `zircote/swagger-php` — generación de `openapi.json` a partir de anotaciones

**Frontend**
- React 18 + TypeScript
- Vite 6
- Tailwind CSS 4
- Radix UI / MUI (componentes)
- Vitest — tests unitarios
- Playwright — tests end-to-end
- ESLint + Prettier

---

## Arquitectura

Todo el backend vive en una única app Slim (arquitectura en capas: Controllers / Repositories /
Services / DTOs), montada en `public/index.php`. Cubre los 5 indicadores (I1–I5), la
administración de Carreras, autenticación, administración de usuarios/cohortes y la integración
con Google Drive.

```
Frontend (React)
      │
      └── fetch → public/index.php     (Slim: rutas + middleware CORS/sesión)
                        │
                        ▼
                   MySQL / MariaDB
```

`api/` ya no contiene endpoints propios: solo queda `api/conexion.php` (conexión mysqli legacy,
reemplazada internamente por `App\Infra\Database::conectar()` a medida que cada llamador se
migró) y una carpeta vacía `api/google_drive/` usada como destino de archivos que no se
versionan (credenciales OAuth, `token.json`).

No hay contenedor de inyección de dependencias: la composición de Controllers/Repositories se
arma a mano en `public/index.php`. Es una decisión deliberada para el tamaño actual del
proyecto (ver comentarios en ese archivo).

---

## Estructura del proyecto

```
.
├── api/
│   ├── conexion.php          Conexión mysqli legacy (reemplazada por App\Infra\Database::conectar())
│   └── google_drive/         Vacía en git; destino local de credenciales.json y token.json (no versionados)
├── public/
│   └── index.php             App Slim: rutas y composición de dependencias de todo el backend
├── src/
│   ├── Controllers/          Auth, Administración (usuarios/cohortes), Carreras, indicadores I1–I5, Google Drive
│   ├── Repositories/         Acceso a datos (SQL) de cada dominio
│   ├── Services/             Lógica de cálculo y validación de evidencias (CSV/PDF), integración Google Drive
│   ├── DTOs/                 Objetos tipados de entrada/salida
│   ├── Middleware/           CORS y autenticación por sesión
│   ├── Infra/                Conexión a base de datos
│   └── OpenApi/              Anotaciones compartidas para la documentación OpenAPI
├── db/
│   ├── migrations/           Migraciones de Phinx (esquema de base de datos)
│   └── seeds/                Seeders de Phinx (datos base: carreras, cohortes, indicadores, usuarios...)
├── sql/                      Scripts SQL puntuales de migraciones de datos ya aplicadas
├── tests/
│   ├── Unit/                 Tests unitarios por indicador (PHPUnit)
│   └── Integration/          Tests de integración contra una base de datos real de prueba
├── docs/                     Diagrama entidad-relación y notas de entrega de fases anteriores
│                             (INSTRUCCIONES_fase*.md: qué cambió y cómo verificar cada fase)
├── bin/generate-openapi.php  Genera openapi.json a partir de las anotaciones en src/
├── openapi.json              Documentación de la API generada (Swagger/OpenAPI)
├── phinx.php                 Configuración de Phinx (entornos local/testing)
├── composer.json / composer.lock
└── frontend/                 Aplicación React (ver estructura abajo)
```

### `frontend/`

```
frontend/
├── src/
│   ├── features/             Un módulo por área funcional (auth, carreras, dashboard, indicadores)
│   │   ├── carreras/         Alta/edición/eliminación de carreras (incluye el flujo de carga
│   │   │                     de malla curricular en Excel: crea cohorte + PAOs + asignaturas)
│   │   │   └── hooks/        useNewCareerForm/useEditCareerForm — orquestan los pasos del
│   │   │                     flujo (crear carrera → subir malla → crear cohorte/períodos/
│   │   │                     asignaturas), con rollback si falla un paso intermedio
│   │   └── indicadores/      Vistas, hooks y componentes específicos de cada indicador (I1–I5)
│   ├── shared/
│   │   ├── services/         Funciones que llaman a la API (fetch), separadas por dominio
│   │   ├── data/              Definiciones estáticas (p. ej. slots de evidencia por indicador)
│   │   ├── utils/mallaCurricular.ts  Parseo del Excel de malla curricular a cohorte/PAOs/
│   │   │                             asignaturas
│   │   └── components/       Componentes reutilizables
│   ├── App.tsx / main.tsx
├── e2e/                      Tests end-to-end (Playwright)
├── vite.config.ts / vitest.config.ts / playwright.config.ts
└── package.json
```

---

## Malla curricular automática desde Excel

Al crear una carrera nueva (o editar una existente), el usuario sube un archivo Excel con la
malla curricular. El sistema lo parsea (`shared/utils/mallaCurricular.ts`) y genera
automáticamente, en orden:

1. La **cohorte** de la carrera.
2. Los **3 períodos académicos** (PAO 1/2/3) de esa cohorte.
3. Las **asignaturas** reales de cada período, con su **módulo** (A/B/C) tomado del Excel.

Si falla algún paso intermedio (por ejemplo, la subida de la malla o la creación de la cohorte),
el flujo hace *rollback* de lo ya creado en pasos anteriores en vez de dejar una carrera a medio
configurar. Antes de esta funcionalidad, esas asignaturas se cargaban a mano una por una o venían
de un listado fijo que solo cubría la carrera de Desarrollo de Software.

---

## Almacenamiento de evidencias: Google Drive o local

Cada carrera puede configurarse para guardar las evidencias que se suben (PDF, CSV, Excel) en
**Google Drive** o en el **servidor local**, según convenga (por ejemplo, para una entrega sin
depender de una cuenta de Drive). El interruptor se cambia desde el modal de administración de la
carrera y aplica de inmediato a las evidencias nuevas que se suban.

---

## Uso de la aplicación

Para una guía funcional de cómo usar el sistema una vez instalado (login, gestión de carreras,
cohortes, usuarios, almacenamiento, dashboard de indicadores y carga de evidencias), ver
[`USAGE.md`](./USAGE.md).

## Roles del sistema

Definidos en la tabla `usuarios.rol`:

- **administrador** — acceso completo: administración de carreras, cohortes, usuarios, malla
  curricular, y carga/evaluación de evidencias de todos los indicadores.
- **evaluador** — consulta de evidencias y resultados de evaluación.
- **coordinador** — gestión de evidencias a nivel de carrera/cohorte.

La autorización se resuelve en dos niveles: `SessionAuthMiddleware` (¿hay sesión activa? → 401
si no) y, para algunas acciones específicas (p. ej. subir malla curricular), un chequeo de rol
dentro del propio controller (→ 403 si el rol no alcanza).

---

## Instalación y ejecución local (XAMPP)

### 1. Clonar el repositorio

Clonarlo **dentro de `htdocs`**, con el nombre `sistemacaces` en minúscula (las rutas del
frontend y el `basePath` de Slim ya asumen ese nombre):

```bash
git clone https://github.com/kjveliz/Sistema_caces_final.git C:/xampp/htdocs/sistemacaces
cd C:/xampp/htdocs/sistemacaces
```

### 2. Backend — dependencias y configuración

```bash
composer install
```

Copiar `.env.example` a `.env` y completar según el entorno local (usuario/clave de MySQL,
origen permitido de CORS, ruta a las credenciales de Google Drive):

```bash
cp .env.example .env
```

### 3. Google Drive (opcional para desarrollo local sin subida real)

Colocar el archivo de credenciales OAuth de Google Cloud en
`api/google_drive/credenciales.json` (no se versiona, ver `.gitignore`). Sin este archivo, los
endpoints que suben evidencias a Drive fallarán; el resto del sistema funciona igual.

### 4. Base de datos

Con Apache y MySQL corriendo en XAMPP, crear el esquema y los datos base con Phinx (recomendado,
es la fuente de verdad del esquema):

```bash
vendor/bin/phinx migrate
vendor/bin/phinx seed:run
```

Alternativamente, se puede importar directamente un dump SQL ya poblado (`evaluacion_caces.sql`)
si se dispone de uno, en una base llamada `evaluacion_caces`.

### 5. Frontend

```bash
cd frontend
npm install
npm run dev
```

### 6. Acceder a la aplicación

| | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API (Slim — todo el backend) | http://localhost/sistemacaces/public/... |

---

## Tests

Ver [`docs/TESTING.md`](docs/TESTING.md) para el detalle de qué cubre cada suite (qué archivos,
qué casos, y para qué sirve cada una).

**Backend** (desde la raíz del repo, con la base de datos `evaluacion_caces_test` disponible
para los de integración — Phinx la crea con `-e testing`):

```bash
composer test               # unitarios (tests/Unit)
composer test:integration   # integración (tests/Integration)
```

**Frontend** (desde `frontend/`):

```bash
npm run typecheck   # TypeScript
npm run lint         # ESLint
npm run test         # Vitest (unitarios)
npm run test:e2e     # Playwright (end-to-end, requiere backend + frontend corriendo)
npm run build        # build de producción
```

---

## Documentación de la API

`openapi.json`, en la raíz del repo, documenta todos los endpoints del backend (autenticación,
administración, Google Drive, Carreras e indicadores I1–I5), generado a partir de anotaciones
OpenAPI en `src/Controllers/`. Para regenerarlo tras modificar un controller:

```bash
composer generate-openapi
```

El archivo resultante puede visualizarse en cualquier herramienta compatible con OpenAPI/Swagger
(por ejemplo, [Swagger Editor](https://editor.swagger.io/) o Swagger UI).

---

## Base de datos

Tablas principales (ver `docs/diagrama-er.png` para el diagrama completo y `db/migrations/` para
la definición exacta):

`carreras`, `cohortes`, `periodo_academico`, `asignatura` (incluye `modulo`, A/B/C, tomado de la
malla curricular en Excel), `indicadores`, `indicador_evidencia`, `evidencias`,
`evidencia_asignatura`, `evidencia_validacion_pdf`, `mallas_curriculares`, `seguimiento_syllabus`,
`syllabus`, `tutorias`, `tutorias_academicas`, `evaluaciones`, `datos_tasa_desercion`,
`datos_tasa_titulacion`, `catalogo_evidencias`, `compartir_catalogo`, `usuarios`.

---

## Estado del proyecto

Este repositorio se entrega como proyecto final (sin continuidad de despliegue/CI). La
migración del backend de scripts PHP sueltos hacia la arquitectura en capas sobre Slim está
**completa**: los 5 indicadores (I1–I5), la administración de Carreras, autenticación,
administración de usuarios/cohortes y la integración con Google Drive (incluido el flujo OAuth
de conexión/callback) viven todos en `src/`, montados en una sola app Slim
(`public/index.php`). `api/` ya no expone endpoints propios.

Funcionalidad multi-carrera cerrada y probada end-to-end: creación/edición de carreras con
generación automática de cohorte + PAOs + asignaturas desde una malla curricular en Excel,
interruptor de almacenamiento (Google Drive / local) por carrera, y eliminación (incluida la
eliminación forzada con evaluaciones asociadas) de carreras y cohortes.

---

## Autores

Proyecto desarrollado como parte de las prácticas preprofesionales de la carrera de Desarrollo
de Software, para apoyar el proceso de evaluación institucional del Modelo CACES mediante la
gestión organizada de evidencias de los indicadores del criterio Docencia.
