# Manual de uso

Esta guía explica **cómo se usa la aplicación una vez instalada y corriendo** (ver
[`README.md`](./README.md) para instalación, arquitectura y tests). Está pensada para quien opera
el sistema día a día: administradores, coordinadores y evaluadores.

## Índice

1. [Roles y qué puede hacer cada uno](#1-roles-y-qué-puede-hacer-cada-uno)
2. [Iniciar sesión](#2-iniciar-sesión)
3. [Elegir una carrera](#3-elegir-una-carrera)
4. [Crear una carrera (malla curricular en Excel)](#4-crear-una-carrera-malla-curricular-en-excel)
5. [Editar o eliminar una carrera](#5-editar-o-eliminar-una-carrera)
6. [Gestionar cohortes](#6-gestionar-cohortes)
7. [Gestionar usuarios](#7-gestionar-usuarios)
8. [Almacenamiento de evidencias: Google Drive o local](#8-almacenamiento-de-evidencias-google-drive-o-local)
9. [Dashboard de indicadores (I1–I5)](#9-dashboard-de-indicadores-i1i5)
10. [Subir evidencias](#10-subir-evidencias)
11. [Conectar la cuenta de Google Drive (una sola vez, administrador)](#11-conectar-la-cuenta-de-google-drive-una-sola-vez-administrador)

---

## 1. Roles y qué puede hacer cada uno

El sistema tiene tres roles (`usuarios.rol`):

| | administrador | coordinador | evaluador |
|---|---|---|---|
| Ver carreras, dashboard, indicadores y evidencias | ✔ | ✔ | ✔ |
| Subir evidencias | ✔ | ✔ | — |
| Crear / editar / eliminar carreras | ✔ | — | — |
| Gestionar cohortes | ✔ | — | — |
| Gestionar usuarios | ✔ | — | — |
| Cambiar el modo de almacenamiento (Drive/local) de una carrera | ✔ | ✔ | — |

El botón **Gestionar** (arriba a la derecha, en la lista de carreras) solo aparece para
`administrador`. El botón **Almacenamiento** aparece para `administrador` y `coordinador`. Un
`evaluador` solo puede consultar: no ve ninguno de los dos botones ni los controles para subir
archivos.

## 2. Iniciar sesión

En `/login`, con **correo** y **contraseña** del usuario creado por un administrador (ver
[§7](#7-gestionar-usuarios)). Si el correo o la contraseña no coinciden, o la cuenta está
desactivada, se muestra el error debajo del formulario sin salir de la pantalla.

Una sesión activa se recuerda entre refrescos de página (no hace falta volver a loguearse cada
vez que se recarga el navegador).

## 3. Elegir una carrera

Después de iniciar sesión se ve la lista de carreras, agrupadas por área. Al elegir una carrera se
entra a sus **criterios** y, dentro de "Docencia", al **dashboard** con los 5 indicadores
(I1–I5) de esa carrera.

## 4. Crear una carrera (malla curricular en Excel)

Menú **Gestionar → Nueva carrera** (solo administrador). El formulario pide:

- **Nombre** de la carrera y **código institucional**.
- **Área** y **modalidad**.
- **Archivo de malla curricular** (Excel) — de acá se generan automáticamente, en este orden, la
  **cohorte**, los **3 períodos académicos** (PAO 1/2/3) y las **asignaturas** de cada período con
  su **módulo** (A/B/C).
- Datos de la **cohorte inicial**: nombre (p. ej. `A2026`), fecha de inicio, fecha de fin y
  estado.

Si falla algún paso intermedio del proceso (por ejemplo, la creación de la cohorte después de ya
haber creado la carrera), el sistema deshace automáticamente lo que alcanzó a crear en pasos
anteriores — no queda una carrera a medio configurar.

## 5. Editar o eliminar una carrera

- **Gestionar → Editar carrera**: permite cambiar nombre, código, área y modalidad de una carrera
  existente.
- **Gestionar → Eliminar carrera**: si la carrera ya tiene evaluaciones registradas, el sistema lo
  bloquea y exige una confirmación explícita adicional antes de permitir el borrado forzado.

## 6. Gestionar cohortes

Menú **Gestionar → Gestionar cohortes** (solo administrador). Permite crear una cohorte nueva para
una carrera existente (carrera, nombre, fecha de inicio, fecha de fin, estado) y eliminar
cohortes, además de la que ya se crea junto con la carrera en el paso 4.

## 7. Gestionar usuarios

Menú **Gestionar → Gestionar usuarios** (solo administrador). Desde ahí se crea un usuario nuevo
con:

- Nombres, apellidos, correo.
- Contraseña (mínimo 8 caracteres).
- Rol: `administrador`, `coordinador` o `evaluador`.
- Estado de la cuenta (activa/inactiva).

También se puede activar o desactivar una cuenta existente desde el listado, sin necesidad de
eliminarla.

## 8. Almacenamiento de evidencias: Google Drive o local

Botón **Almacenamiento** (administrador y coordinador). Por cada carrera se elige si las
evidencias que se suban (PDF, CSV, Excel) se guardan en **Google Drive** o de forma **local** en
el servidor:

- **Google Drive**: requiere que la cuenta de Drive ya esté conectada una vez por un administrador
  (ver [§11](#11-conectar-la-cuenta-de-google-drive-una-sola-vez-administrador)).
- **Local**: opcionalmente se puede indicar una **ruta local** absoluta en el servidor (por
  ejemplo, para guardar en un pendrive o disco externo); si se deja vacía, usa la carpeta local
  por defecto.

El cambio aplica de inmediato a las evidencias nuevas que se suban desde ese momento; no migra
retroactivamente las que ya estaban guardadas con el modo anterior.

## 9. Dashboard de indicadores (I1–I5)

Al entrar a una carrera y elegir la cohorte/período (PAO) desde el dashboard, se ven los 5
indicadores del criterio Docencia:

| # | Indicador | Tipo | Evidencia |
|---|---|---|---|
| I1 | Syllabus (Malla Curricular) | — | Documento de malla curricular por carrera |
| I2 | Seguimiento de Syllabus | Cuantitativo | Encuesta (CSV) + evidencias de cumplimiento por asignatura |
| I3 | Tutorías Académicas | Cualitativo | Planeación/Cumplimiento/Seguimiento (CSV) + Normativas (PDF), por asignatura |
| I4 | Tasa de Deserción | Cuantitativo | Datos por cohorte + evidencia (PDF) |
| I5 | Tasa de Titulación | Cuantitativo | Datos por cohorte + evidencia (PDF) |

Cada indicador muestra el porcentaje de cumplimiento calculado y una escala (Satisfactorio / Cuasi
Satisfactorio / Poco Satisfactorio / Deficiente), agregando resultados por asignatura y por
cohorte/período. Al hacer clic en un indicador se entra al detalle con el desglose por asignatura
(I1–I3) o por cohorte (I4–I5).

## 10. Subir evidencias

Disponible para `administrador` y `coordinador` (el botón no aparece para `evaluador`). Desde el
dashboard o desde el detalle de un indicador, el flujo de carga es un asistente de pasos:

1. **Seleccionar indicador** (si no se entró ya desde uno específico).
2. **Configurar el contexto**:
   - I1–I3: período (PAO), módulo y asignatura.
   - I4–I5: cohorte.
3. **Subir el archivo** correspondiente (CSV, PDF o Excel según el indicador — ver la tabla de
   [§9](#9-dashboard-de-indicadores-i1i5)).

El archivo se guarda en Google Drive o localmente según el modo configurado para esa carrera (ver
[§8](#8-almacenamiento-de-evidencias-google-drive-o-local)), y el porcentaje de cumplimiento del
indicador se recalcula automáticamente.

## 11. Conectar la cuenta de Google Drive (una sola vez, administrador)

Esto **no se hace desde el frontend**: es un paso manual que un administrador abre directamente en
el navegador, una sola vez (o cada vez que el token expire), visitando:

```
http://localhost/sistemacaces/public/google-drive/conectar
```

Esa URL redirige a la pantalla de consentimiento de Google. Al aceptar, Google redirige de vuelta
a `/google-drive/callback`, que guarda el token de acceso en el servidor
(`api/google_drive/token.json`, no versionado). A partir de ahí, cualquier carrera configurada en
modo **Google Drive** ya puede subir evidencias sin que cada usuario tenga que autorizar nada por
su cuenta.

Requiere tener las credenciales OAuth ya colocadas en `api/google_drive/credenciales.json` (ver
"Instalación y ejecución local" en el `README.md`).
