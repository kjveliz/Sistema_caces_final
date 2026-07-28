<?php

declare(strict_types=1);

namespace Tests\Integration\Carreras;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de PUT /carreras/{id}/almacenamiento (paso 4 de
 * plan_interruptor_almacenamiento.txt — EvidenciaMigradorService +
 * CarrerasAlmacenamientoController).
 *
 * Usa id_carrera=1 ("Desarrollo de Software", sembrada por
 * CarrerasSeeder) y id_asignatura=1..5 (AsignaturaSeeder, todas del
 * PAO 1 de esa carrera — ver ResultadoAsignaturaTest). El seam de testing
 * de GoogleDriveService::subirArchivo() (activo porque IntegrationTestCase
 * levanta el servidor con APP_ENV=testing) evita llamadas reales a Drive,
 * igual que en el resto de la suite de integración.
 */
final class AlmacenamientoTest extends IntegrationTestCase
{
    private const RUTA = '/carreras/1/almacenamiento';

    private function insertarEvidenciaDePrueba(int $idAsignatura, string $tipo, string $urlArchivo): int
    {
        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (?, ?, 'evidencia_prueba.csv', ?, 'test', 1)"
        );
        $stmt->bind_param('iss', $idAsignatura, $tipo, $urlArchivo);
        $stmt->execute();

        return $stmt->insert_id;
    }

    public function testSinSesionDevuelve401(): void
    {
        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'local'],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolEvaluadorDevuelve403(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'local'],
        ]);

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolCoordinadorYModoInvalidoDevuelve400(): void
    {
        $this->loguearComo('coordinador@demo.local');

        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'ftp'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testMigracionExitosaDriveALocalActualizaCarreraYEvidencia(): void
    {
        $this->loguearComo('administrador@demo.local');

        $idEvidencia = $this->insertarEvidenciaDePrueba(
            1,
            'plan_tutorias',
            'https://drive.google.com/file/d/fake-migracion-1/view?usp=drivesdk',
        );

        $rutaLocal = sys_get_temp_dir() . '/caces_almacenamiento_test_' . uniqid('', true);

        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'local', 'ruta_local' => $rutaLocal],
        ]);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('drive', $respuesta['json']['datos']['modo_anterior']);
        $this->assertSame('local', $respuesta['json']['datos']['modo_nuevo']);
        $this->assertGreaterThanOrEqual(1, $respuesta['json']['datos']['total_migrados']);

        $conexion = self::conexionBd();

        $filaCarrera = $conexion->query('SELECT modo_almacenamiento, ruta_almacenamiento_local FROM carreras WHERE id_carrera = 1')->fetch_assoc();
        $this->assertSame('local', $filaCarrera['modo_almacenamiento']);
        $this->assertSame($rutaLocal, $filaCarrera['ruta_almacenamiento_local']);

        $stmt = $conexion->prepare('SELECT url_archivo FROM evidencia_asignatura WHERE id_evidencia_asig = ?');
        $stmt->bind_param('i', $idEvidencia);
        $stmt->execute();
        $filaEvidencia = $stmt->get_result()->fetch_assoc();

        $this->assertStringStartsWith($rutaLocal, $filaEvidencia['url_archivo']);
        $this->assertFileExists($filaEvidencia['url_archivo']);

        // Deja la carrera como estaba para no afectar otros tests de la
        // suite que asumen id_carrera=1 en modo 'drive' por defecto.
        $conexion->query("UPDATE carreras SET modo_almacenamiento = 'drive', ruta_almacenamiento_local = NULL WHERE id_carrera = 1");
    }

    public function testMigrarAlMismoModoSinCambiosDevuelve422(): void
    {
        $this->loguearComo('administrador@demo.local');

        // id_carrera=1 arranca en 'drive' (default de la migración de
        // esquema) y no se le pasó ruta_local -- no hay nada que migrar.
        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'drive'],
        ]);

        $this->assertSame(422, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testMigracionConArchivoDeOrigenInexistenteSeRevierteYDevuelve422(): void
    {
        $this->loguearComo('administrador@demo.local');

        $idEvidenciaOk = $this->insertarEvidenciaDePrueba(
            2,
            'plan_tutorias',
            'https://drive.google.com/file/d/fake-migracion-ok/view?usp=drivesdk',
        );
        // url_archivo con forma de ruta local (no empieza con http) que no
        // existe -- resolverParaDescarga() la manda a AlmacenamientoLocalService,
        // cuyo descargarContenido() devuelve null si el archivo no existe,
        // forzando el fallo a mitad de la migración.
        $rutaInexistente = sys_get_temp_dir() . '/no_existe_' . uniqid('', true) . '.csv';
        $this->insertarEvidenciaDePrueba(2, 'registro_tutorias', $rutaInexistente);

        $respuesta = $this->peticion('PUT', self::RUTA, [
            'json' => ['modo_almacenamiento' => 'local'],
        ]);

        $this->assertSame(422, $respuesta['status'], (string) $respuesta['body']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertStringContainsString('revertida', $respuesta['json']['detalle']);

        $conexion = self::conexionBd();
        $filaCarrera = $conexion->query('SELECT modo_almacenamiento FROM carreras WHERE id_carrera = 1')->fetch_assoc();
        $this->assertSame('drive', $filaCarrera['modo_almacenamiento']);

        $stmt = $conexion->prepare('SELECT url_archivo FROM evidencia_asignatura WHERE id_evidencia_asig = ?');
        $stmt->bind_param('i', $idEvidenciaOk);
        $stmt->execute();
        $filaOk = $stmt->get_result()->fetch_assoc();
        $this->assertSame('https://drive.google.com/file/d/fake-migracion-ok/view?usp=drivesdk', $filaOk['url_archivo']);
    }
}
