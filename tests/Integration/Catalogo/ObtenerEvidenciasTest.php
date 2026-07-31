<?php

declare(strict_types=1);

namespace Tests\Integration\Catalogo;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /catalogo/obtener-evidencias (Parte 9 del
 * plan de migración de PHP suelto a Slim -- ver
 * plan_migracion_slim_legacy_v3.txt §3; reemplaza a
 * api/catalogo/obtener_evidencias.php), primera Parte del Grupo C
 * (misceláneos).
 *
 * Usa el catálogo real del seeder (CatalogoEvidenciasSeeder): id_indicador=1
 * (Syllabus) tiene exactamente 2 filas activas (id_catalogo=5 "DOC.SYL.01"
 * orden=1, id_catalogo=7 "DOC.SYL.02" orden=2), buen caso limpio para
 * confirmar el ORDER BY orden ASC sin insertar fixtures propios. Para el
 * filtro `activo = 1` (que el seeder no ejerce, todas sus filas ya vienen
 * activas) se inserta una fila inactiva de prueba directo en la BD.
 */
final class ObtenerEvidenciasTest extends IntegrationTestCase
{
    private const RUTA = '/catalogo/obtener-evidencias';
    private const ID_INDICADOR_SYLLABUS = 1;

    private ?int $idCatalogoInactivoInsertado = null;

    protected function tearDown(): void
    {
        if ($this->idCatalogoInactivoInsertado !== null) {
            self::conexionBd()->query(
                'DELETE FROM catalogo_evidencias WHERE id_catalogo = ' . $this->idCatalogoInactivoInsertado
            );
            $this->idCatalogoInactivoInsertado = null;
        }
    }

    public function testSinIdIndicadorDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Debe enviar el id del indicador.', $respuesta['json']['mensaje']);
    }

    public function testConIdIndicadorCeroDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?id_indicador=0');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testIndicadorSinCatalogoDevuelveListaVacia(): void
    {
        // Un id_indicador que no existe en catalogo_evidencias no es un
        // error -- mismo comportamiento que el original, 200 con "datos"
        // vacío (no hay caso 404 en este endpoint).
        $respuesta = $this->peticion('GET', self::RUTA . '?id_indicador=9999');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
        $this->assertArrayNotHasKey('mensaje', $respuesta['json']);
    }

    public function testCasoFelizDevuelveElCatalogoOrdenadoPorOrden(): void
    {
        $respuesta = $this->peticion(
            'GET',
            self::RUTA . '?id_indicador=' . self::ID_INDICADOR_SYLLABUS
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertArrayNotHasKey('mensaje', $respuesta['json']);
        $this->assertCount(2, $respuesta['json']['datos']);

        // orden=1 primero, orden=2 segundo -- confirma el ORDER BY, no solo
        // que trajo las filas correctas.
        $primero = $respuesta['json']['datos'][0];
        $segundo = $respuesta['json']['datos'][1];

        $this->assertSame(5, $primero['id_catalogo']);
        $this->assertSame('DOC.SYL.01', $primero['codigo_evidencia']);
        $this->assertSame('Malla curricular', $primero['titulo_corto']);
        $this->assertSame('Malla_Curricular', $primero['nombre_archivo_base']);
        $this->assertSame(1, $primero['orden']);

        $this->assertSame(7, $segundo['id_catalogo']);
        $this->assertSame('DOC.SYL.02', $segundo['codigo_evidencia']);
        $this->assertSame('Syllabus', $segundo['titulo_corto']);
        $this->assertSame(2, $segundo['orden']);
    }

    public function testNoIncluyeCatalogoInactivo(): void
    {
        $conexion = self::conexionBd();
        $conexion->query(
            "INSERT INTO catalogo_evidencias
                (id_indicador, codigo_evidencia, titulo_corto, descripcion, nombre_archivo_base, orden, activo)
             VALUES
                (" . self::ID_INDICADOR_SYLLABUS . ", 'DOC.SYL.99', 'Inactivo de prueba', 'Fila inactiva de prueba', 'Inactivo_Prueba', 99, 0)"
        );
        $this->idCatalogoInactivoInsertado = $conexion->insert_id;

        $respuesta = $this->peticion(
            'GET',
            self::RUTA . '?id_indicador=' . self::ID_INDICADOR_SYLLABUS
        );

        $this->assertSame(200, $respuesta['status']);
        // Sigue siendo 2 (las activas del seeder), la inactiva no aparece.
        $this->assertCount(2, $respuesta['json']['datos']);

        $codigos = array_column($respuesta['json']['datos'], 'codigo_evidencia');
        $this->assertNotContains('DOC.SYL.99', $codigos);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
