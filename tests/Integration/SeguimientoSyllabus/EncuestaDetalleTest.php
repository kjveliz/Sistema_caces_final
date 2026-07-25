<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/encuesta-detalle
 * (indicador I2) -- ver MEMORIA v70, §47.8 punto 8.
 *
 * A diferencia del resto de I2, este endpoint no tiene un seam de testing
 * en GoogleDriveService::descargarContenidoDrive() (solo subirArchivo() lo
 * tiene -- ver ese archivo) porque EncuestaEvidenciaService primero revisa
 * un caché en disco (rutaCacheCsv(), TTL de 60s) ANTES de tocar el
 * repositorio o Drive: si el caché está fresco, ni siquiera se llega a
 * consultar la BD. El test de éxito de este archivo se apoya en eso --
 * escribe directamente el archivo de caché esperado para no necesitar
 * credenciales reales de Drive (que no hay en este entorno).
 *
 * Riesgo aceptado: si `sys_get_temp_dir()` difiere entre el proceso de
 * PHPUnit y el del servidor embebido (mismo host, pero entornos raros de
 * TMPDIR por usuario), este test podría no encontrar el caché. No se vio
 * ese caso en Windows/XAMPP durante v67-v70.
 */
final class EncuestaDetalleTest extends IntegrationTestCase
{
    private function rutaCache(int $idAsignatura): string
    {
        return sys_get_temp_dir() . '/seguimiento_syllabus_encuesta_cache_asignatura_' . $idAsignatura . '.csv';
    }

    public function testSinParametroDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/encuesta-detalle');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaInexistenteDevuelve404(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/encuesta-detalle?id_asignatura=99999');

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaSinCsvSubidoDevuelve502(): void
    {
        $idAsignatura = 2; // Cultura tecnológica y digital -- sin evidencia sembrada.
        @unlink($this->rutaCache($idAsignatura)); // por si quedó un caché de una corrida anterior.

        $respuesta = $this->peticion('GET', "/seguimiento-syllabus/encuesta-detalle?id_asignatura={$idAsignatura}");

        $this->assertSame(502, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaConCsvCacheadoDevuelveElDetalleParseado(): void
    {
        $idAsignatura = 4; // Fundamentos de Programación y Algoritmos.

        // CSV mínimo con una sola pregunta (P1) y dos respuestas -- mismo
        // formato que valida EncuestaCalculoTest (1 columna fija de
        // timestamp, sin columnas de materia/profesor).
        $csv = "Marca temporal,[P1. Pregunta uno]\n"
            . "2026-07-20 10:00:00,Siempre\n"
            . "2026-07-20 11:00:00,Algunas veces\n";
        file_put_contents($this->rutaCache($idAsignatura), $csv);

        $respuesta = $this->peticion('GET', "/seguimiento-syllabus/encuesta-detalle?id_asignatura={$idAsignatura}");

        @unlink($this->rutaCache($idAsignatura));

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertSame(2, $datos['respuestas_totales_materia']);
        $this->assertCount(23, $datos['preguntas']);

        $pregunta1 = $datos['preguntas'][0];
        $this->assertSame(1, $pregunta1['numero']);
        $this->assertSame('Pregunta uno', $pregunta1['texto']);
        $this->assertSame(1, $pregunta1['conteos']['Siempre']);
        $this->assertSame(1, $pregunta1['conteos']['Algunas veces']);
        $this->assertSame(2, $pregunta1['total']);

        // Ninguna de las otras 22 preguntas vino en el CSV -- deben quedar
        // con texto null y en cero.
        $pregunta2 = $datos['preguntas'][1];
        $this->assertNull($pregunta2['texto']);
        $this->assertSame(0, $pregunta2['total']);
    }
}
