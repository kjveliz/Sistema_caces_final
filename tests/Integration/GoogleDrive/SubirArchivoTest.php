<?php

declare(strict_types=1);

namespace Tests\Integration\GoogleDrive;

use CURLFile;
use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /google-drive/subir-archivo (Parte 21,
 * sesión 2, del plan de migración de PHP suelto a Slim -- ver
 * plan_migracion_slim_legacy_v3.txt §3; reemplaza a
 * api/google_drive/subir_archivo.php, endpoint genérico de I1/I4/I5).
 *
 * Sin sesión requerida (mismo criterio que el original -- ver docblock de
 * GoogleDriveController::subirArchivo()). id_carrera=1 es la carrera "SOFT"
 * de los fixtures de siempre (ver tests/Integration/Carreras/
 * AlmacenamientoTest.php), con modo_almacenamiento='local' por defecto --
 * se restaura después de cada test que lo cambie, para no afectar al resto
 * de la suite.
 *
 * La subida real a Google Drive queda reemplazada por el seam de testing
 * de GoogleDriveService::subirArchivoCatalogo() (activo porque
 * IntegrationTestCase levanta el servidor con APP_ENV=testing) -- estos
 * tests verifican el contrato HTTP, no la integración real con Drive.
 */
final class SubirArchivoTest extends IntegrationTestCase
{
    private const RUTA = '/google-drive/subir-archivo';
    private const ID_CARRERA = 1;

    protected function tearDown(): void
    {
        self::conexionBd()->query(
            "UPDATE carreras SET modo_almacenamiento = 'local', ruta_almacenamiento_local = NULL WHERE id_carrera = " . self::ID_CARRERA
        );
    }

    private function crearPdfDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'caces_subir_archivo_') . '.pdf';
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 3 3]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function crearCsvDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'caces_subir_archivo_') . '.csv';
        file_put_contents($ruta, "col1,col2\nvalor1,valor2\n");

        return $ruta;
    }

    private function camposBase(): array
    {
        return [
            'id_carrera' => (string) self::ID_CARRERA,
            'codigo_carrera' => 'SOFT',
            'nombre_carrera' => 'Desarrollo de Software',
            'cohorte' => 'B2025',
            'indicador' => '4',
            'nombre_archivo' => 'graduados.pdf',
        ];
    }

    public function testSinSesionNoDevuelve401(): void
    {
        // Mismo criterio que api/evidencias/{leer-matriculados,preparar-pdf}:
        // el original no exige sesión, así que un 401 acá sería una
        // regresión de comportamiento, no una mejora.
        $respuesta = $this->peticion('POST', self::RUTA, ['multipart' => $this->camposBase()]);

        $this->assertNotSame(401, $respuesta['status']);
    }

    public function testFaltanDatosDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => ['id_carrera' => (string) self::ID_CARRERA],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Faltan datos para subir el archivo.', $respuesta['json']['mensaje']);
    }

    public function testSinArchivoDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, ['multipart' => $this->camposBase()]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No se recibió ningún archivo.', $respuesta['json']['mensaje']);
    }

    public function testArchivoConExtensionInvalidaDevuelve400(): void
    {
        $rutaCsv = $this->crearCsvDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'archivo' => new CURLFile($rutaCsv, 'text/csv', 'graduados.pdf'),
            ]),
        ]);

        unlink($rutaCsv);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Solo se aceptan archivos PDF válidos.', $respuesta['json']['mensaje']);
    }

    public function testSubidaExitosaEnLocalDevuelveDatosYUsaElArbolDeCincoNiveles(): void
    {
        $rutaPdf = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'archivo' => new CURLFile($rutaPdf, 'application/pdf', 'graduados.pdf'),
            ]),
        ]);

        unlink($rutaPdf);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertStringContainsString(
            'almacenamiento local',
            $respuesta['json']['mensaje'],
        );
        $this->assertStringContainsString('Evaluacion/I4/graduados.pdf', $respuesta['json']['datos']['url_archivo']);
        $this->assertSame('SOFT', $respuesta['json']['datos']['codigo_carrera']);
        $this->assertSame(4, $respuesta['json']['datos']['indicador']);
    }

    public function testSubidaExitosaEnDriveUsaElSeamDeTestingDeSubirArchivoCatalogo(): void
    {
        self::conexionBd()->query(
            "UPDATE carreras SET modo_almacenamiento = 'drive' WHERE id_carrera = " . self::ID_CARRERA
        );

        $rutaPdf = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'archivo' => new CURLFile($rutaPdf, 'application/pdf', 'graduados.pdf'),
            ]),
        ]);

        unlink($rutaPdf);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertStringContainsString('Google Drive', $respuesta['json']['mensaje']);
        $this->assertStringContainsString('fake-test-double', $respuesta['json']['datos']['url_archivo']);
    }

    public function testTipoEsperadoXlsxValidaExtensionXlsx(): void
    {
        $rutaPdf = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'tipo_esperado' => 'xlsx',
                'archivo' => new CURLFile($rutaPdf, 'application/pdf', 'malla.pdf'),
            ]),
        ]);

        unlink($rutaPdf);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Solo se aceptan archivos Excel (.xlsx) válidos.', $respuesta['json']['mensaje']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
