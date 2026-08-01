# Frontend — Sistema de Gestión de Evidencias CACES

Aplicación React que consume la API del backend (Slim, ver `../README.md`) para la carga,
organización y evaluación automática de evidencias de los indicadores del criterio Docencia
(I1–I5), y la administración de carreras, cohortes y usuarios.

## Stack

- React 18 + TypeScript
- Vite 6
- Tailwind CSS 4
- Radix UI / MUI (componentes)
- Vitest — tests unitarios
- Playwright — tests end-to-end
- ESLint + Prettier

## Estructura

```
src/
├── app/                Configuración de la app (rutas, providers)
├── contexts/           Contextos de React compartidos (p. ej. sesión de usuario)
├── features/           Un módulo por área funcional (auth, carreras, dashboard, indicadores)
│   ├── carreras/       Alta/edición/eliminación de carreras, incluye el flujo de carga de
│   │                   malla curricular en Excel (crea cohorte + PAOs + asignaturas)
│   │   └── hooks/      useNewCareerForm/useEditCareerForm — orquestan los pasos del flujo,
│   │                   con rollback si falla un paso intermedio
│   └── indicadores/    Vistas, hooks y componentes específicos de cada indicador (I1–I5)
├── shared/
│   ├── services/       Funciones que llaman a la API (fetch), separadas por dominio
│   ├── data/           Definiciones estáticas (p. ej. slots de evidencia por indicador)
│   ├── utils/mallaCurricular.ts   Parseo del Excel de malla curricular a cohorte/PAOs/asignaturas
│   └── components/      Componentes reutilizables
├── types/               Tipos compartidos
└── main.tsx / preview-entry.tsx

e2e/                      Tests end-to-end (Playwright) contra backend + frontend corriendo
```

## Uso de la aplicación

Para el flujo de pantallas (login, carreras, dashboard de indicadores, carga de evidencias, etc.)
y qué puede hacer cada rol, ver [`USAGE.md`](../USAGE.md) en la raíz del repo — no se duplica acá
para no mantener el mismo contenido en dos lugares.

## Requisitos previos

El backend debe estar corriendo (ver instalación en `../README.md`), incluida la base de datos
con las migraciones y seeders de Phinx aplicados.

## Instalación y ejecución

```bash
npm install
npm run dev
```

La app queda disponible en `http://localhost:5173` y consume la API del backend en
`http://localhost/sistemacaces/public/...`, hoy hardcodeada en cada archivo de
`src/shared/services/*.ts`. Existe un `.env.example` con `VITE_API_URL` para cuando se
centralice esa URL, pero el código todavía no la lee.

## Tests y verificación

```bash
npm run typecheck   # TypeScript (app + tests e2e)
npm run lint         # ESLint
npm run test         # Vitest (unitarios)
npm run test:e2e     # Playwright (end-to-end, requiere backend + frontend corriendo)
npm run build        # Build de producción
npm run format       # Prettier
```

### Configuración opcional para `test:e2e`

Los tests de Playwright asumen un usuario demo (`db/seeds/UsuariosSeeder.php`) y datos de
ejemplo para la carrera "Desarrollo de Software" / cohorte "B 2025". Si tu base de datos local no
tiene esos datos, o el backend/frontend no corren en las URLs por defecto, copiar
`e2e/.env.e2e.example` a `e2e/.env.e2e` y ajustar las variables (`E2E_EMAIL`, `E2E_PASSWORD`,
`E2E_CAREER`, `E2E_BASE_URL`). Ver `docs/INSTRUCCIONES_fase5_playwright.md` (en la raíz del repo)
para más detalle.
