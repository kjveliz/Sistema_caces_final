<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/evidencia-listar
 * (indicador I2) -- ver MEMORIA v70, §47.8 punto 8.
 *
 * Cubre el fix de filtrado por tipo documentado en
 * SeguimientoSyllabusRepository::evidenciasVigentesPorAsignatura() (mismo
 * bug de "evidencia_asignatura es tabla compartida entre indicadores" ya
 * corregido para I3, ver MEMORIA v63/§40.5): al insertar una fila con un
 * tipo que NO pertenece a I2, el endpoint no debe reflejarla como subida.
 */
final class EvidenciaListarTest extends IntegrationTestCase
{
    private const TIPOS_I2 = ['syllabus', 'acta_ajuste_curricular', 'evidencia_difusion', 'encuesta_csv'];

    public function testSinParametroDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/evidencia-listar');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaSinEvidenciasDevuelveLosCuatroTiposConSubidaFalse(): void
    {
        $idAsignatura = 2; // Cultura tecnológica y digital -- sin evidencias sembradas.

        $respuesta = $this->peticion('GET', "/seguimiento-syllabus/evidencia-listar?id_asignatura={$idAsignatura}");

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(4, $datos);
        $this->assertSame(self::TIPOS_I2, array_column($datos, 'tipo'));

        foreach ($datos as $item) {
            $this->assertFalse($item['subida']);
            $this->assertNull($item['archivo']);
        }
    }

    public function testAsignaturaConUnaEvidenciaVigenteMarcaSoloEseTipoComoSubido(): void
    {
        $idAsignatura = 1; // Comunicación efectiva y trabajo en equipo.

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (?, 'acta_ajuste_curricular', 'acta_prueba.pdf', 'https://drive.example/acta', '2', 1)"
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();

        $respuesta = $this->peticion('GET', "/seguimiento-syllabus/evidencia-listar?id_asignatura={$idAsignatura}");

        $this->assertSame(200, $respuesta['status']);
        $datos = $respuesta['json']['datos'];

        $porTipo = array_combine(array_column($datos, 'tipo'), $datos);

        $this->assertTrue($porTipo['acta_ajuste_curricular']['subida']);
        $this->assertSame('acta_prueba.pdf', $porTipo['acta_ajuste_curricular']['archivo']['nombre_archivo']);

        foreach (['syllabus', 'evidencia_difusion', 'encuesta_csv'] as $tipo) {
            $this->assertFalse($porTipo[$tipo]['subida']);
            $this->assertNull($porTipo[$tipo]['archivo']);
        }
    }
}
