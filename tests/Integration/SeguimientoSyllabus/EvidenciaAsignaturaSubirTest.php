<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use CURLFile;
use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de api/seguimiento_syllabus/evidencia_asignatura_subir.php.
 *
 * La subida real a Google Drive queda reemplazada por el seam de testing en
 * _google_drive.php (activo porque IntegrationTestCase levanta el servidor
 * con APP_ENV=testing) -- estos tests verifican el contrato HTTP y el
 * efecto en la base de datos, no la integración real con Drive.
 */
final class EvidenciaAsignaturaSubirTest extends IntegrationTestCase
{
    private const RUTA = '/api/seguimiento_syllabus/evidencia_asignatura_subir.php';

    private function crearPdfDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'caces_pdf_') . '.pdf';
        // PDF mínimo mente válido: encabezado %PDF- + estructura mínima,
        // suficiente para que finfo lo detecte como application/pdf (lo
        // mismo que valida validarPdf() en _google_drive.php).
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 3 3]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";
        file_put_contents($ruta, $contenido);
        return $ruta;
    }

    public function testSinSesionDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => ['id_asignatura' => '1', 'tipo' => 'syllabus'],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConSesionTipoInvalidoDevuelve400(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => ['id_asignatura' => '1', 'tipo' => 'tipo_que_no_existe'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConSesionSinArchivoDevuelve400(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => ['id_asignatura' => '1', 'tipo' => 'syllabus'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testSubidaExitosaGuardaEvidenciaVigenteYUsaElSeamDeDrive(): void
    {
        $this->loguearComo('evaluador@demo.local');

        $idAsignatura = 3; // Humanismo y Persona
        $rutaPdf = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => [
                'id_asignatura' => (string) $idAsignatura,
                'tipo' => 'syllabus',
                'archivo' => new CURLFile($rutaPdf, 'application/pdf', 'syllabus.pdf'),
            ],
        ]);

        unlink($rutaPdf);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertStringContainsString('fake-test-double', $respuesta['json']['datos']['url_archivo']);

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "SELECT COUNT(*) AS total FROM evidencia_asignatura
             WHERE id_asignatura = ? AND tipo = 'syllabus' AND vigente = 1"
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        $this->assertSame(1, (int) $fila['total']);
    }

    public function testSubidaNuevaMarcaLaAnteriorComoNoVigente(): void
    {
        $this->loguearComo('evaluador@demo.local');

        // Asignatura propia de este test (distinta de la de los demás
        // tests de esta clase) para no depender de qué haya corrido antes.
        $idAsignatura = 1; // Comunicación efectiva y trabajo en equipo

        foreach (['syllabus_v1.pdf', 'syllabus_v2.pdf'] as $nombreArchivo) {
            $rutaPdf = $this->crearPdfDePrueba();
            $respuesta = $this->peticion('POST', self::RUTA, [
                'multipart' => [
                    'id_asignatura' => (string) $idAsignatura,
                    'tipo' => 'syllabus',
                    'archivo' => new CURLFile($rutaPdf, 'application/pdf', $nombreArchivo),
                ],
            ]);
            unlink($rutaPdf);
            $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        }

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "SELECT vigente FROM evidencia_asignatura
             WHERE id_asignatura = ? AND tipo = 'syllabus' ORDER BY id_evidencia_asig ASC"
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->assertCount(2, $filas);
        $this->assertSame(0, (int) $filas[0]['vigente']);
        $this->assertSame(1, (int) $filas[1]['vigente']);
    }
}
