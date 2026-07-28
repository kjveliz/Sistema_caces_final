<?php

declare(strict_types=1);

namespace Tests\Unit\Almacenamiento;

use App\Services\AlmacenamientoLocalService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de AlmacenamientoLocalService (interruptor de almacenamiento por
 * carrera — ver plan_interruptor_almacenamiento.txt §4.2). Usa una raíz
 * temporal aislada por test (tempnam-based), nunca storage/evidencias/
 * real, para no ensuciar el filesystem del repo al correr la suite.
 */
final class AlmacenamientoLocalServiceTest extends TestCase
{
    private string $raizPrueba;

    protected function setUp(): void
    {
        $this->raizPrueba = sys_get_temp_dir() . '/almacenamiento_test_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->borrarRecursivo($this->raizPrueba);
    }

    private function borrarRecursivo(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $archivo) {
            if ($archivo === '.' || $archivo === '..') {
                continue;
            }
            $ruta = "{$dir}/{$archivo}";
            is_dir($ruta) ? $this->borrarRecursivo($ruta) : unlink($ruta);
        }
        rmdir($dir);
    }

    private function archivoTemporalConContenido(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'evidencia_');
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function testSubirArchivoCreaElArbolCarreraCohortePaoAsignatura(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp = $this->archivoTemporalConContenido('contenido de prueba');

        $resultado = $servicio->subirArchivo($tmp, 'informe.csv', 'Comunicación efectiva', 'B2025', 'PAO 1', 'Bases de datos');

        $this->assertFileExists($resultado['url_archivo']);
        $this->assertSame('informe.csv', $resultado['nombre_archivo']);
        $this->assertStringStartsWith('local-', $resultado['id_archivo']);
        $this->assertStringContainsString('B2025', $resultado['url_archivo']);
        $this->assertStringContainsString('PAO 1', $resultado['url_archivo']);
        unlink($tmp);
    }

    public function testSubirArchivoDosVecesReemplazaElContenido(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp1 = $this->archivoTemporalConContenido('version 1');
        $tmp2 = $this->archivoTemporalConContenido('version 2');

        $primera = $servicio->subirArchivo($tmp1, 'informe.csv', 'Carrera', 'B2025', 'PAO 1', 'Materia');
        $segunda = $servicio->subirArchivo($tmp2, 'informe.csv', 'Carrera', 'B2025', 'PAO 1', 'Materia');

        $this->assertSame($primera['url_archivo'], $segunda['url_archivo']);
        $this->assertSame('version 2', file_get_contents($segunda['url_archivo']));
        unlink($tmp1);
        unlink($tmp2);
    }

    public function testDescargarContenidoDevuelveElContenidoYaSubido(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp = $this->archivoTemporalConContenido('Cohorte;Pao' . "\n" . 'B2025;PAO 1');

        $subida = $servicio->subirArchivo($tmp, 'informe.csv', 'Carrera', 'B2025', 'PAO 1', 'Materia');
        $contenido = $servicio->descargarContenido($subida['url_archivo']);

        $this->assertSame('Cohorte;Pao' . "\n" . 'B2025;PAO 1', $contenido);
        unlink($tmp);
    }

    public function testDescargarContenidoDevuelveNullSiLaRutaNoExiste(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);

        $this->assertNull($servicio->descargarContenido($this->raizPrueba . '/no-existe.csv'));
    }

    public function testSubirArchivoSaneaSegmentosConCaracteresPeligrosos(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp = $this->archivoTemporalConContenido('x');

        // Intento de path traversal en nombre de carrera y de archivo: el
        // resultado debe quedar contenido dentro de la raíz de prueba.
        $resultado = $servicio->subirArchivo($tmp, '../../etc/passwd', '../../../etc', 'B2025', 'PAO 1', 'X');

        $rutaReal = realpath($resultado['url_archivo']);
        $raizReal = realpath($this->raizPrueba);

        $this->assertNotFalse($rutaReal);
        $this->assertStringStartsWith($raizReal, $rutaReal);
        unlink($tmp);
    }

    public function testRaizPorDefectoApuntaAStorageEvidenciasDelProyecto(): void
    {
        $this->assertStringEndsWith('/storage/evidencias', AlmacenamientoLocalService::raizPorDefecto());
    }

    public function testEliminarArchivoBorraUnArchivoExistente(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp = $this->archivoTemporalConContenido('contenido');
        $subida = $servicio->subirArchivo($tmp, 'informe.csv', 'Carrera', 'B2025', 'PAO 1', 'Materia');

        $resultado = $servicio->eliminarArchivo($subida['url_archivo']);

        $this->assertTrue($resultado);
        $this->assertFileDoesNotExist($subida['url_archivo']);
        unlink($tmp);
    }

    public function testEliminarArchivoDevuelveFalseSiLaRutaNoExiste(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);

        $this->assertFalse($servicio->eliminarArchivo($this->raizPrueba . '/no-existe.csv'));
    }

    // ── Validaciones heredadas del trait compartido con GoogleDriveService ──

    public function testValidarCsvRechazaExtensionDistinta(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        $tmp = $this->archivoTemporalConContenido('contenido');

        $archivo = ['error' => UPLOAD_ERR_OK, 'size' => 10, 'name' => 'informe.pdf', 'tmp_name' => $tmp];

        $this->assertNotNull($servicio->validarCsv($archivo));
        unlink($tmp);
    }

    public function testValidarArchivoSubidoAceptaUnPdfValido(): void
    {
        $servicio = new AlmacenamientoLocalService($this->raizPrueba);
        // Cabecera mínima real de PDF para que finfo lo detecte como application/pdf.
        $tmp = $this->archivoTemporalConContenido("%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj");

        $archivo = ['error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'name' => 'evidencia.pdf', 'tmp_name' => $tmp];

        $this->assertNull($servicio->validarArchivoSubido($archivo));
        unlink($tmp);
    }
}
