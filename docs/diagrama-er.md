# Diagrama ER — `evaluacion_caces`

**Generado:** 24 julio 2026, a partir del esquema reconstruido en
`db/migrations/20260724020000_esquema_inicial.php` (Fase 2 del Plan de
Mejora), que a su vez viene del dump de referencia `evaluacion_caces (12).sql`
(24-07-2026, MariaDB 10.4.32).

Archivo versionado: [`diagrama-er.png`](./diagrama-er.png).

Colores:
- **Azul** — catálogos base sin dependencias (`carreras`, `indicadores`,
  `usuarios`).
- **Gris oscuro** — tablas de segundo/tercer nivel (referencian solo a los
  catálogos base).
- **Amarillo** — `asignatura` y `evaluaciones`, los dos ejes centrales de los
  que cuelga casi todo lo demás.
- **Verde** — todo lo relacionado con evidencias (`evidencias`,
  `evidencia_asignatura`, `evidencia_validacion_pdf`, `indicador_evidencia`).
- **Marrón** — resultados de cálculo por indicador (`seguimiento_syllabus`,
  `syllabus`, `tutorias`, `tutorias_academicas`, `datos_tasa_desercion`,
  `datos_tasa_titulacion`).

## Cómo regenerarlo

El archivo fuente (`diagrama-er.dot`, formato Graphviz) queda versionado
junto al PNG. Si el esquema cambia (migración nueva en una fase futura),
actualizar el `.dot` a mano y correr:

```bash
dot -Tpng diagrama-er.dot -o diagrama-er.png
```

Requiere tener Graphviz instalado (`sudo apt install graphviz` en
Linux/WSL, o `choco install graphviz` en Windows). Actualizar también la
fecha de "Generado" arriba cuando se regenere.
