<?php

declare(strict_types=1);

namespace Tests\Integration\EvidenciaAsignatura;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /evidencia-asignatura/ver (visor nuevo de
 * `evidencia_asignatura`, tabla compartida I2/I3) -- parte 1 del paso 6 de
 * plan_interruptor_almacenamiento.txt, pendiente explícito del paso 5 (ver
 * MEMORIA §68.2/§68.5: ver_archivo.php solo ramificó el visor de I4/I5).
 *
 * Usa id_asignatura=1 (Comunicación efectiva y trabajo en equipo, sembrada
 * por AsignaturaSeeder, misma que ya usan otros tests de esta suite -- ver
 * ResultadoAsignaturaTest / EvidenciaAsignaturaSubirTest). El seam de
 * testing de GoogleDriveService::descargarContenidoDrive() (activo porque
 * IntegrationTestCase levanta el servidor con APP_ENV=testing) devuelve
 * contenido determinístico ("contenido-fake-de-prueba-drive:{id}") sin
 * llamar a Drive real -- se usa acá para probar la rama Drive del visor,
 * igual que ya la usa AlmacenamientoTest para el migrador.
 */
final class VerTest extends IntegrationTestCase
{
    private const RUTA = '/evidencia-asignatura/ver';

    private function insertarEvidencia(string $nombreArchivo, string $urlArchivo, string $tipo = 'syllabus'): int
    {
        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (1, ?, ?, ?, 'test', 1)"
        );
        $stmt->bind_param('sss', $tipo, $nombreArchivo, $urlArchivo);
        $stmt->execute();

        return $stmt->insert_id;
    }

    public function testSinSesionDevuelve401(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?id_evidencia_asig=1');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testSinIdDevuelve400(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testIdInexistenteDevuelve404(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('GET', self::RUTA . '?id_evidencia_asig=999999');

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testArchivoLocalExistenteDevuelveContenidoConContentTypeCsv(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $rutaArchivo = tempnam(sys_get_temp_dir(), 'caces_visor_') . '.csv';
        file_put_contents($rutaArchivo, "col1,col2\nvalor1,valor2\n");

        $idEvidencia = $this->insertarEvidencia('encuesta.csv', $rutaArchivo, 'encuesta_csv');

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia_asig={$idEvidencia}");

        unlink($rutaArchivo);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame("col1,col2\nvalor1,valor2\n", $respuesta['body']);
    }

    public function testArchivoLocalInexistenteDevuelve404(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $rutaInexistente = sys_get_temp_dir() . '/caces_no_existe_' . uniqid('', true) . '.pdf';
        $idEvidencia = $this->insertarEvidencia('syllabus.pdf', $rutaInexistente);

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia_asig={$idEvidencia}");

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testArchivoEnDriveUsaElSeamDeTestingYDevuelveContenido(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $idEvidencia = $this->insertarEvidencia(
            'syllabus.pdf',
            'https://drive.google.com/file/d/fake-visor-1/view?usp=drivesdk',
        );

        $respuesta = $this->peticion('GET', self::RUTA . "?id_evidencia_asig={$idEvidencia}");

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame('contenido-fake-de-prueba-drive:fake-visor-1', $respuesta['body']);
    }
}
