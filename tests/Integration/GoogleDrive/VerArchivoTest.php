<?php

declare(strict_types=1);

namespace Tests\Integration\GoogleDrive;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /google-drive/ver-archivo (Parte 20 del plan
 * de migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/google_drive/ver_archivo.php, tabla `evidencias`,
 * I1/I4/I5).
 *
 * Mismo criterio que tests/Integration/EvidenciaAsignatura/VerTest.php
 * (visor análogo de I2/I3, ya migrado): fixture insertada directo en
 * `evidencias` (id_evaluacion=1 de EvaluacionesSeeder, id_catalogo=1 de
 * CatalogoEvidenciasSeeder, mismo par que ya usa GuardadasTest/
 * CompartidasTest), y el seam de testing de
 * GoogleDriveService::descargarContenidoDrive() (activo porque
 * IntegrationTestCase levanta el servidor con APP_ENV=testing) para
 * probar la rama Drive sin llamar a la API real.
 */
final class VerArchivoTest extends IntegrationTestCase
{
    private const RUTA = '/google-drive/ver-archivo';
    private const ID_EVALUACION = 1;
    private const ID_CATALOGO = 1;

    private int $idEvidenciaInsertada = 0;

    protected function tearDown(): void
    {
        if ($this->idEvidenciaInsertada > 0) {
            $conexion = self::conexionBd();
            $conexion->query(
                'DELETE FROM evidencias WHERE id_evidencia = ' . $this->idEvidenciaInsertada
            );
            $this->idEvidenciaInsertada = 0;
        }
    }

    private function insertarEvidencia(string $nombreArchivo, string $urlArchivo): int
    {
        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "INSERT INTO evidencias (id_catalogo, id_evaluacion, codigo_evidencia, descripcion, nombre_archivo, tipo, url_archivo, id_usuario)
             VALUES (?, ?, 'DOC.TIT.01', 'Evidencia de prueba (VerArchivoTest)', ?, 'pdf', ?, 1)"
        );
        $idCatalogo = self::ID_CATALOGO;
        $idEvaluacion = self::ID_EVALUACION;
        $stmt->bind_param('iiss', $idCatalogo, $idEvaluacion, $nombreArchivo, $urlArchivo);
        $stmt->execute();

        $this->idEvidenciaInsertada = (int) $stmt->insert_id;

        return $this->idEvidenciaInsertada;
    }

    public function testSinSesionDevuelve401(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?id_evidencia=1');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testSinIdDevuelve400(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Identificador de evidencia inválido.', $respuesta['json']['mensaje']);
    }

    public function testIdCeroODevuelve400(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('GET', self::RUTA . '?id_evidencia=0');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testIdInexistenteDevuelve404(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('GET', self::RUTA . '?id_evidencia=999999');

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('La evidencia no existe.', $respuesta['json']['mensaje']);
    }

    public function testArchivoLocalExistenteDevuelveContenidoConContentTypeCsv(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $rutaArchivo = tempnam(sys_get_temp_dir(), 'caces_ver_archivo_') . '.csv';
        file_put_contents($rutaArchivo, "col1,col2\nvalor1,valor2\n");

        $idEvidencia = $this->insertarEvidencia('reporte.csv', $rutaArchivo);

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia={$idEvidencia}");

        unlink($rutaArchivo);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame("col1,col2\nvalor1,valor2\n", $respuesta['body']);
    }

    public function testArchivoLocalInexistenteDevuelve404(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $rutaInexistente = sys_get_temp_dir() . '/caces_no_existe_' . uniqid('', true) . '.pdf';
        $idEvidencia = $this->insertarEvidencia('graduados.pdf', $rutaInexistente);

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia={$idEvidencia}");

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testArchivoEnDriveUsaElSeamDeTestingYDevuelveContenido(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $idEvidencia = $this->insertarEvidencia(
            'graduados.pdf',
            'https://drive.google.com/file/d/fake-ver-archivo-1/view?usp=drivesdk',
        );

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia={$idEvidencia}");

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame('contenido-fake-de-prueba-drive:fake-ver-archivo-1', $respuesta['body']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
