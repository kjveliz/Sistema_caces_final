<?php

declare(strict_types=1);

namespace Tests\Unit\Almacenamiento;

use App\Services\AlmacenamientoLocalService;
use App\Services\EvidenciaStorageResolver;
use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de EvidenciaStorageResolver::resolverParaDescarga() -- la única
 * parte del resolver que no depende de una conexión mysqli real (decide
 * por la forma de la URL, no consulta `carreras`). resolver(int) (la
 * parte que sí consulta `carreras.modo_almacenamiento`) se ejercita de
 * punta a punta en los tests de integración de evidencia-subir de I2/I3
 * contra la BD real de pruebas, no acá.
 */
final class EvidenciaStorageResolverTest extends TestCase
{
    private function resolver(): EvidenciaStorageResolver
    {
        // mysqli nunca se toca en resolverParaDescarga(), así que basta con
        // un valor que satisfaga el tipo del constructor sin conectar de
        // verdad -- PHPUnit no necesita instanciar la clase real de mysqli
        // para esto porque el método bajo prueba no la usa.
        $conexionFalsa = $this->createStub(\mysqli::class);

        return new EvidenciaStorageResolver($conexionFalsa, new GoogleDriveService());
    }

    public function testUrlHttpsResuelveAGoogleDriveService(): void
    {
        $resultado = $this->resolver()->resolverParaDescarga('https://drive.google.com/file/d/ABC123/view');

        $this->assertInstanceOf(GoogleDriveService::class, $resultado);
    }

    public function testUrlHttpResuelveAGoogleDriveService(): void
    {
        $resultado = $this->resolver()->resolverParaDescarga('http://drive.google.com/file/d/ABC123/view');

        $this->assertInstanceOf(GoogleDriveService::class, $resultado);
    }

    public function testRutaLocalResuelveAAlmacenamientoLocalService(): void
    {
        $resultado = $this->resolver()->resolverParaDescarga('/var/www/storage/evidencias/Carrera/B2025/PAO1/Materia/informe.csv');

        $this->assertInstanceOf(AlmacenamientoLocalService::class, $resultado);
    }

    public function testRutaLocalEstiloWindowsTambienResuelveALocal(): void
    {
        $resultado = $this->resolver()->resolverParaDescarga('C:\\xampp\\htdocs\\sistemacaces\\storage\\evidencias\\informe.csv');

        $this->assertInstanceOf(AlmacenamientoLocalService::class, $resultado);
    }
}
