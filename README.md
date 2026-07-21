\# Sistema de Gestión de Evidencias CACES



\## Indicador: Tasa de Titulación



\## Descripción



El Sistema de Gestión de Evidencias CACES fue desarrollado para apoyar el proceso de evaluación institucional del \*\*Modelo de Evaluación CACES\*\*, específicamente el indicador \*\*Tasa de Titulación\*\*.



El objetivo principal es facilitar la administración de evidencias, permitiendo organizar documentos, registrar información de las cohortes y mantener un repositorio centralizado para el proceso de evaluación.



\---



\# Características



\* Inicio de sesión mediante usuarios registrados.

\* Control de acceso por roles.

\* Administración de carreras.

\* Administración de cohortes.

\* Administración de períodos académicos.

\* Administración de indicadores.

\* Carga de evidencias en formato PDF.

\* Organización automática de documentos.

\* Consulta de evidencias registradas.

\* Evaluación del indicador.

\* Panel principal (Dashboard).



\---



\# Tecnologías utilizadas



\## Frontend



\* React

\* TypeScript

\* Vite

\* Tailwind CSS

\* Lucide React



\## Backend



\* PHP 8

\* Apache



\## Base de datos



\* MySQL



\## Herramientas de desarrollo



\* Visual Studio Code

\* XAMPP

\* Git

\* GitHub



\---



\# Arquitectura del sistema



```

React (Frontend)



&#x20;       │



&#x20;       ▼



Fetch API



&#x20;       │



&#x20;       ▼



PHP (Backend)



&#x20;       │



&#x20;       ▼



MySQL

```



\---



\# Estructura del proyecto



```

SistemaCACES/



│

├── api/

│

├── public/

│

├── src/

│

├── package.json

│

├── vite.config.ts

│

├── tsconfig.json

│

└── README.md

```



\---



\# Explicación de carpetas



\## api/



Contiene toda la lógica del servidor desarrollada en PHP.



Aquí se reciben las solicitudes provenientes del frontend, se realizan las validaciones necesarias y se consulta la base de datos.



Ejemplo:



```

api/



auth/

config/

evidencias/

evaluacion/

```



\### auth/



Gestiona la autenticación del sistema.



Archivos principales:



\* login.php

\* logout.php

\* session.php



Responsabilidades:



\* Validar usuarios.

\* Crear sesión.

\* Cerrar sesión.

\* Verificar permisos.



\---



\### config/



Contiene la configuración general.



Normalmente incluye:



\* conexión a MySQL

\* variables de configuración

\* funciones comunes



\---



\### evidencias/



Administra toda la información relacionada con las evidencias.



Funciones principales:



\* registrar evidencia

\* cargar archivos PDF

\* consultar evidencias

\* actualizar registros



\---



\### evaluacion/



Contiene la lógica utilizada para la evaluación del indicador.



Aquí se realizan los cálculos y validaciones correspondientes.



\---



\# src/



Contiene todo el código del frontend desarrollado con React.



```

src/



assets/

components/

services/

views/

App.tsx

main.tsx

```



\---



\## assets/



Almacena recursos gráficos.



Ejemplos:



\* imágenes

\* logotipos

\* íconos



\---



\## components/



Contiene componentes reutilizables.



Ejemplos:



\* botones

\* tablas

\* tarjetas

\* formularios

\* ventanas modales



\---



\## services/



Contiene funciones encargadas de comunicarse con el backend mediante Fetch API.



Ejemplos:



\* login

\* obtener carreras

\* guardar evidencia

\* consultar indicadores



Esto permite separar la lógica de comunicación de las vistas.



\---



\## views/



Aquí se encuentran todas las pantallas del sistema.



Ejemplo:



```

LoginView.tsx



Dashboard.tsx



CareersView.tsx



EvidenceUploadView.tsx



EvaluationView.tsx

```



Cada archivo representa una página completa del sistema.



\---



\# Flujo general del sistema



```

Inicio



↓



Inicio de sesión



↓



Validación de usuario



↓



Dashboard



↓



Selección de carrera



↓



Selección de cohorte



↓



Selección de período académico



↓



Carga de evidencia



↓



Registro en la base de datos



↓



Consulta de evidencias



↓



Evaluación del indicador

```



\---



\# Flujo de autenticación



```

Usuario



↓



Ingresa correo y contraseña



↓



React envía petición al Backend



↓



PHP valida usuario



↓



MySQL verifica credenciales



↓



Si son correctas



↓



Se crea la sesión



↓



Acceso al Dashboard

```



\---



\# Flujo de carga de evidencias



```

Usuario



↓



Selecciona archivo PDF



↓



Validación del formato



↓



Envío al Backend



↓



Registro en MySQL



↓



Actualización de la interfaz



↓



La evidencia queda disponible para consulta

```



\---



\# Base de datos



Las principales tablas utilizadas por el sistema son:



```

usuarios



carreras



cohortes



periodo\_academico



indicadores



evidencias



indicador\_evidencia



evidencia\_archivo

```



\---



\## Relación general



```

Carrera



↓



Cohorte



↓



Periodo Académico



↓



Indicador



↓



Evidencia



↓



Archivo PDF

```



\---



\# Roles del sistema



\## Administrador



Puede:



\* administrar usuarios

\* administrar carreras

\* administrar cohortes

\* administrar períodos

\* administrar indicadores

\* cargar evidencias

\* consultar información

\* realizar evaluaciones



\---



\## Evaluador



Puede:



\* iniciar sesión

\* consultar evidencias

\* visualizar documentos

\* revisar evaluaciones



\---



\# Instalación del proyecto



\## 1. Clonar el repositorio



```

git clone https://github.com/usuario/SistemaCACES.git

```



\---



\## 2. Instalar dependencias



```

npm install

```



\---



\## 3. Ejecutar el frontend



```

npm run dev

```



\---



\## 4. Configurar XAMPP



Iniciar:



\* Apache

\* MySQL



\---



\## 5. Colocar el backend



Copiar la carpeta \*\*api\*\* dentro del directorio:



```

xampp/htdocs/SistemaCACES/

```



\---



\## 6. Crear la base de datos



Crear una base de datos denominada:



```

sistemacaces

```



Importar el archivo SQL correspondiente.



\---



\## 7. Ejecutar la aplicación



Frontend:



```

http://localhost:5173

```



Backend:



```

http://localhost/SistemaCACES/api

```



\---



\# Buenas prácticas implementadas



\* Separación entre frontend y backend.

\* Organización modular del código.

\* Componentes reutilizables.

\* Validaciones en cliente y servidor.

\* Uso de Fetch API para comunicación.

\* Código organizado por responsabilidades.

\* Base de datos normalizada.

\* Gestión centralizada de evidencias.



\---



\# Posibles mejoras futuras



\* Lectura automática del contenido de archivos PDF.

\* Cálculo automático de la tasa de titulación.

\* Generación de reportes en PDF.

\* Exportación a Excel.

\* Historial de cambios.

\* Registro de auditoría.

\* Notificaciones al usuario.

\* Panel de estadísticas.



\---



\# Autor



Proyecto desarrollado como parte de las prácticas preprofesionales de la carrera de Desarrollo de Software.



Su finalidad es apoyar el proceso de evaluación institucional del Modelo CACES mediante la gestión organizada de evidencias correspondientes al indicador \*\*Tasa de Titulación\*\*.



