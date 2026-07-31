<?php

declare(strict_types=1);

namespace Tests\Integration\Evidencias;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /evidencias/guardadas (Parte 4 del plan de
 * migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/evidencias/obtener_evidencias_guardadas.php).
 *
 * Fixture: inserta directo en `evidencias` (sin seeder propio, a
 * diferencia de `catalogo_evidencias` -- ver CatalogoEvidenciasSeeder) una
 * fila para id_evaluacion=1 (EvaluacionesSeeder) + id_catalogo=1
 * (CatalogoEvidenciasSeeder: id_indicador=5, titulo_corto='Estudiantes
 * graduados'), mismo criterio que MeTest al preparar datos directo contra
 * la BD de prueba en vez de vía HTTP.
 */
final class GuardadasTest extends IntegrationTestCase
{
    private const ID_EVALUACION = 1;
    private const ID_CATALOGO = 1;
    private const ID_INDICADOR = 5;

    protected function tearDown(): void
    {
        // Deja la tabla como estaba para no afectar otros tests de esta
        // misma clase ni de otras clases que reutilicen el mismo seed.
        $conexion = self::conexionBd();
        $conexion->query(
            'DELETE FROM evidencias WHERE id_evaluacion = ' . self::ID_EVALUACION
            . ' AND id_catalogo = ' . self::ID_CATALOGO
        );
    }

    public function testGuardadasSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/evidencias/guardadas');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Debe enviar la evaluación y el indicador.', $respuesta['json']['mensaje']);
    }

    public function testGuardadasConSoloIdEvaluacionDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/evidencias/guardadas?id_evaluacion=' . self::ID_EVALUACION);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testGuardadasSinEvidenciasDevuelveListaVacia(): void
    {
        // Ningún fixture insertado: mismo comportamiento que el original
        // (no hay caso 404, una evaluación/indicador sin evidencias
        // guardadas todavía es un 200 con "datos" vacío).
        $respuesta = $this->peticion(
            'GET',
            '/evidencias/guardadas?id_evaluacion=' . self::ID_EVALUACION . '&id_indicador=' . self::ID_INDICADOR
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
        $this->assertArrayNotHasKey('mensaje', $respuesta['json']);
    }

    public function testGuardadasConEvidenciaGuardadaDevuelveLosDatosCruzadosConElCatalogo(): void
    {
        $conexion = self::conexionBd();
        $conexion->query(
            "INSERT INTO evidencias (id_catalogo, id_evaluacion, codigo_evidencia, descripcion, nombre_archivo, tipo, url_archivo, id_usuario)
             VALUES (" . self::ID_CATALOGO . ", " . self::ID_EVALUACION . ", 'DOC.TIT.01', 'Evidencia de prueba', 'graduados.pdf', 'pdf', 'https://drive.example/graduados.pdf', 1)"
        );

        $respuesta = $this->peticion(
            'GET',
            '/evidencias/guardadas?id_evaluacion=' . self::ID_EVALUACION . '&id_indicador=' . self::ID_INDICADOR
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertCount(1, $respuesta['json']['datos']);

        $fila = $respuesta['json']['datos'][0];
        $this->assertSame(self::ID_CATALOGO, $fila['id_catalogo']);
        $this->assertSame(self::ID_EVALUACION, $fila['id_evaluacion']);
        $this->assertSame('DOC.TIT.01', $fila['codigo_evidencia']);
        $this->assertSame('graduados.pdf', $fila['nombre_archivo']);
        // Datos cruzados con catalogo_evidencias (CatalogoEvidenciasSeeder,
        // id_catalogo=1): confirma el INNER JOIN, no solo el SELECT plano.
        $this->assertSame('Estudiantes graduados', $fila['titulo_corto']);
        $this->assertSame('Estudiantes_Graduados', $fila['nombre_archivo_base']);
        $this->assertSame(1, $fila['orden']);
    }

    public function testGuardadasConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', '/evidencias/guardadas');

        $this->assertSame(405, $respuesta['status']);
    }
}
