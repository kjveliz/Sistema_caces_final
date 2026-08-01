<?php

declare(strict_types=1);

namespace Tests\Unit\GoogleDrive;

use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de GoogleDriveService::subirArchivoCatalogo() (Parte 21 del plan de
 * migración slim-legacy, sesión 1 -- puerto del branch de Drive de
 * api/google_drive/subir_archivo.php, endpoint genérico compartido por
 * I1/I4/I5). Igual que subirArchivo() (sin test unitario propio hoy, solo
 * cubierto vía el seam de APP_ENV=testing consumido por las suites de
 * integración), esta clase no permite mockear `Google\Client`/`Drive`
 * fácilmente porque `GoogleDriveClienteAutorizado::obtener()` se llama de
 * forma estática -- la cobertura acá se limita a verificar el
 * comportamiento bajo el seam de testing (sin llamadas reales a Drive); el
 * comportamiento real de 3 niveles de carpeta (Carrera/Cohorte, sin
 * PAO/Asignatura) se verifica por revisión de código contra
 * GoogleDriveCarpetas::obtenerEstructuraCaces() y, más adelante, con los
 * tests de integración de la Parte 21 (sesión 2, una vez exista la ruta
 * Slim real).
 */
final class GoogleDriveServiceTest extends TestCase
{
    private ?string $appEnvOriginal;

    protected function setUp(): void
    {
        $this->appEnvOriginal = getenv('APP_ENV') ?: null;
        putenv('APP_ENV=testing');
    }

    protected function tearDown(): void
    {
        putenv($this->appEnvOriginal !== null ? "APP_ENV={$this->appEnvOriginal}" : 'APP_ENV');
    }

    private function archivoTemporalConContenido(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'catalogo_');
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function testSubirArchivoCatalogoBajoSeamDeTestingNoLlamaADriveReal(): void
    {
        $servicio = new GoogleDriveService();
        $tmp = $this->archivoTemporalConContenido('contenido de prueba');

        $resultado = $servicio->subirArchivoCatalogo($tmp, 'reporte.pdf', 'Comunicación efectiva', 'B2025');

        $this->assertArrayHasKey('id_archivo', $resultado);
        $this->assertArrayHasKey('nombre_archivo', $resultado);
        $this->assertArrayHasKey('url_archivo', $resultado);
        $this->assertSame('reporte.pdf', $resultado['nombre_archivo']);
        $this->assertStringStartsWith('https://drive.google.com/fake-test-double/', $resultado['url_archivo']);
        unlink($tmp);
    }

    public function testSubirArchivoCatalogoUsaMimeTypePorDefectoPdfSiNoSeIndica(): void
    {
        $servicio = new GoogleDriveService();
        $tmp = $this->archivoTemporalConContenido('contenido de prueba');

        // El default no afecta el resultado bajo el seam de testing (no
        // llega a llamar a la API real), pero se ejercita la firma
        // completa igual que la llamará GoogleDriveController más
        // adelante (sesión 2).
        $resultado = $servicio->subirArchivoCatalogo($tmp, 'malla.xlsx', 'Comunicación efectiva', 'B2025', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame('malla.xlsx', $resultado['nombre_archivo']);
        unlink($tmp);
    }
}
