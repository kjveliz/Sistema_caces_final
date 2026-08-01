<?php

declare(strict_types=1);

namespace Tests\Unit\GoogleDrive;

use App\Services\GoogleDriveCarpetas;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files;
use PHPUnit\Framework\TestCase;

/**
 * Tests de GoogleDriveCarpetas (Parte 18 del plan de migración slim-legacy
 * -- puerto de api/google_drive/drive_helpers.php). El recurso `files` de
 * Google\Service\Drive se mockea (createMock de Files) para no depender de
 * la API real; Drive se construye sin cliente real (config vacía, sin
 * llamadas de red en el constructor).
 */
final class GoogleDriveCarpetasTest extends TestCase
{
    private function driveConFilesMock(Files $filesMock): Drive
    {
        $drive = new Drive();
        $drive->files = $filesMock;

        return $drive;
    }

    public function testEscaparConsultaDriveEscapaBackslashYComillaSimple(): void
    {
        $this->assertSame(
            "Asignatura \\\\ con \\'comillas\\'",
            GoogleDriveCarpetas::escaparConsultaDrive("Asignatura \\ con 'comillas'"),
        );
    }

    public function testEscaparConsultaDriveDejaTextoSinCaracteresEspecialesIgual(): void
    {
        $this->assertSame(
            'Desarrollo de Software',
            GoogleDriveCarpetas::escaparConsultaDrive('Desarrollo de Software'),
        );
    }

    public function testObtenerOCrearCarpetaDevuelveElIdSiLaCarpetaYaExiste(): void
    {
        $filesMock = $this->createMock(Files::class);

        $existente = new DriveFile();
        $existente->setId('id-existente');

        $lista = new FileList();
        $lista->setFiles([$existente]);

        $filesMock->expects($this->once())->method('listFiles')->willReturn($lista);
        $filesMock->expects($this->never())->method('create');

        $id = GoogleDriveCarpetas::obtenerOCrearCarpeta($this->driveConFilesMock($filesMock), 'B2025', 'id-padre');

        $this->assertSame('id-existente', $id);
    }

    public function testObtenerOCrearCarpetaLaCreaSiNoExiste(): void
    {
        $filesMock = $this->createMock(Files::class);

        $filesMock->method('listFiles')->willReturn(new FileList(['files' => []]));

        $creada = new DriveFile();
        $creada->setId('id-nueva');

        $filesMock->expects($this->once())->method('create')->willReturn($creada);

        $id = GoogleDriveCarpetas::obtenerOCrearCarpeta($this->driveConFilesMock($filesMock), 'B2025');

        $this->assertSame('id-nueva', $id);
    }

    public function testObtenerEstructuraCacesArmaElArbolRaizCarreraCohorteAnidado(): void
    {
        $filesMock = $this->createMock(Files::class);

        // Ninguna carpeta existe todavía -- las 3 llamadas van por create().
        $filesMock->method('listFiles')->willReturn(new FileList(['files' => []]));

        // El id devuelto por create() refleja el nombre y el padre recibidos,
        // para poder verificar que obtenerEstructuraCaces anida correctamente
        // (cada nivel usa el id del nivel anterior como idPadre).
        $filesMock->method('create')->willReturnCallback(
            static function (DriveFile $postBody): DriveFile {
                $padres = $postBody->getParents();
                $sufijoPadre = $padres ? ('-bajo-' . $padres[0]) : '';

                $creada = new DriveFile();
                $creada->setId('id-' . $postBody->getName() . $sufijoPadre);

                return $creada;
            },
        );

        $estructura = GoogleDriveCarpetas::obtenerEstructuraCaces(
            $this->driveConFilesMock($filesMock),
            'Desarrollo de Software',
            'B2025',
        );

        $this->assertSame('id-Sistema CACES', $estructura['raiz']);
        $this->assertSame('id-Desarrollo de Software-bajo-id-Sistema CACES', $estructura['carrera']);
        $this->assertSame(
            'id-B2025-bajo-id-Desarrollo de Software-bajo-id-Sistema CACES',
            $estructura['cohorte'],
        );
    }
}
