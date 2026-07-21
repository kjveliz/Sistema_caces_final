<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);

/**
 * DEPRECADO (v18, ver MEMORIA): este endpoint listaba las materias
 * distintas encontradas dentro de un único CSV evaluation-wide, útil cuando
 * la encuesta mezclaba filas de varias materias en un solo archivo. Desde
 * que el CSV pasó a subirse por-asignatura (cada archivo ya pertenece a una
 * sola materia), listar "materias dentro del CSV" ya no tiene sentido: la
 * materia la determina quién sube el archivo, no su contenido.
 *
 * Se deja el endpoint respondiendo ok=true con una lista vacía (en vez de
 * eliminarlo) para no romper en caliente si algo todavía lo está llamando;
 * pendiente confirmar con el usuario si se puede borrar del todo (ver
 * MEMORIA, pendientes).
 */
responderJson(true, 'Endpoint deprecado: la encuesta ahora es por-asignatura, ya no aplica listar materias dentro de un CSV compartido.', ['datos' => []]);
