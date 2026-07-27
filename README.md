# Sistema de Gestión de Evidencias CACES

Sistema web para apoyar el proceso de evaluación institucional del **Modelo de Evaluación
CACES**, carrera de Desarrollo de Software. Centraliza la carga, organización y evaluación
automática de evidencias para 5 indicadores del criterio Docencia.

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

El backend está en una **migración progresiva** de scripts PHP sueltos hacia una arquitectura
en capas sobre Slim. Conviven dos partes:

- **`src/`** — la parte ya migrada (Controllers/Repositories/Services/DTOs), montada como una
  sola app Slim en `public/index.php`. Cubre los indicadores I1–I5 y la administración de
  Carreras.
- **`api/`** — endpoints PHP sueltos que todavía no se migraron: autenticación
  (`api/auth/`), administración de usuarios y cohortes (`api/administracion/`), integración con
  Google Drive (`api/google_drive/`), y algunos endpoints de evidencias/catálogo/evaluaciones
  compartidos entre indicadores.

```
Frontend (React)
      │
      ├── fetch → api/*.php            (endpoints legacy: auth, admin, Google Drive)
      │
      └── fetch → public/index.php     (Slim: I1–I5 + Carreras)
                        │
                        ▼
                   MySQL / MariaDB
```

No hay contenedor de inyección de dependencias: la composición de Controllers/Repositories se
arma a mano en `public/index.php`. Es una decisión deliberada para el tamaño actual del
proyecto (ver comentarios en ese archivo).

---

## Estructura del proyecto

```
.
├── api/                      Endpoints PHP sueltos, sin migrar (auth, administración, Google Drive, evidencias compartidas)
├── public/
│   └── index.php             App Slim: rutas y composición de dependencias de I1–I5 y Carreras
├── src/
│   ├── Controllers/          Un controller por indicador/entidad migrada
│   ├── Repositories/         Acceso a datos (SQL) de la parte migrada
│   ├── Services/             Lógica de cálculo y validación de evidencias (CSV/PDF)
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
├── docs/                     Diagrama entidad-relación de la base de datos
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
│   │   └── indicadores/      Vistas, hooks y componentes específicos de cada indicador (I1–I5)
│   ├── shared/
│   │   ├── services/         Funciones que llaman a la API (fetch), separadas por dominio
│   │   ├── data/              Definiciones estáticas (p. ej. slots de evidencia por indicador)
│   │   └── components/       Componentes reutilizables
│   ├── App.tsx / main.tsx
├── e2e/                      Tests end-to-end (Playwright)
├── vite.config.ts / vitest.config.ts / playwright.config.ts
└── package.json
```

---

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
| API (Slim — I1 a I5, Carreras) | http://localhost/sistemacaces/public/... |
| API (endpoints legacy — auth, administración, Google Drive) | http://localhost/sistemacaces/api/... |

---

## Tests

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

`openapi.json`, en la raíz del repo, documenta los endpoints de la parte migrada a Slim
(I1–I5 y Carreras), generado a partir de anotaciones OpenAPI en `src/Controllers/`. Para
regenerarlo tras modificar un controller:

```bash
composer generate-openapi
```

El archivo resultante puede visualizarse en cualquier herramienta compatible con OpenAPI/Swagger
(por ejemplo, [Swagger Editor](https://editor.swagger.io/) o Swagger UI).

---

## Base de datos

Tablas principales (ver `docs/diagrama-er.png` para el diagrama completo y `db/migrations/` para
la definición exacta):

`carreras`, `cohortes`, `periodo_academico`, `asignatura`, `indicadores`, `indicador_evidencia`,
`evidencias`, `evidencia_asignatura`, `evidencia_validacion_pdf`, `mallas_curriculares`,
`seguimiento_syllabus`, `syllabus`, `tutorias`, `tutorias_academicas`, `evaluaciones`,
`datos_tasa_desercion`, `datos_tasa_titulacion`, `catalogo_evidencias`, `compartir_catalogo`,
`usuarios`.

---

## Estado del proyecto

Este repositorio se entrega como proyecto final (sin continuidad de despliegue/CI). El backend
está en migración progresiva de `api/` (scripts PHP sueltos) hacia `src/` (arquitectura en
capas sobre Slim); a la fecha de esta entrega, los 5 indicadores (I1–I5) y la administración de
Carreras ya están migrados. Los endpoints de autenticación, administración de usuarios/cohortes
y la integración con Google Drive siguen como scripts sueltos en `api/`.

---

## Autores

Proyecto desarrollado como parte de las prácticas preprofesionales de la carrera de Desarrollo
de Software, para apoyar el proceso de evaluación institucional del Modelo CACES mediante la
gestión organizada de evidencias de los indicadores del criterio Docencia.
