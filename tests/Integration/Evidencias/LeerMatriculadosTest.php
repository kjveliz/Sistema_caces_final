<?php

declare(strict_types=1);

namespace Tests\Integration\Evidencias;

use CURLFile;
use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /evidencias/leer-matriculados (Parte 6 del
 * plan de migración de PHP suelto a Slim -- ver
 * plan_migracion_slim_legacy_v3.txt §3; reemplaza a
 * api/evidencias/leer_matriculados.php).
 *
 * No usa fixtures de BD (a diferencia de GuardadasTest/CompartidasTest):
 * este endpoint no toca la base de datos, solo lee texto de un PDF. Los
 * PDFs de prueba se generan a mano con un stream de texto mínimo (objetos
 * PDF armados directo, con tabla xref real -- Smalot\PdfParser necesita el
 * `startxref` para no tirar "Unable to find startxref"), en vez de usar el
 * truco de %PDF-1.4 + estructura mínima sin contenido que ya usa
 * EvidenciaAsignaturaSubirTest (ese alcanza para pasar la validación de
 * mime con finfo, pero acá además hace falta que Smalot\PdfParser pueda
 * extraer texto real de las páginas).
 */
final class LeerMatriculadosTest extends IntegrationTestCase
{
    private const RUTA = '/evidencias/leer-matriculados';

    private function crearPdfConTexto(string $texto): string
    {
        $contenidoStream = "BT /F1 12 Tf 10 150 Td ({$texto}) Tj ET";
        $longitud = strlen($contenidoStream);

        $objetos = [];
        $objetos[1] = '<</Type/Catalog/Pages 2 0 R>>';
        $objetos[2] = '<</Type/Pages/Kids[3 0 R]/Count 1>>';
        $objetos[3] = '<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 200]/Resources<</Font<</F1 5 0 R>>>>/Contents 4 0 R>>';
        $objetos[4] = "<</Length {$longitud}>>stream\n{$contenidoStream}\nendstream\n";
        $objetos[5] = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj{$cuerpo}endobj\n";
        }
        $offsetXref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<</Size {$total}/Root 1 0 R>>\nstartxref\n{$offsetXref}\n%%EOF";

        $ruta = tempnam(sys_get_temp_dir(), 'caces_matriculados_') . '.pdf';
        file_put_contents($ruta, $pdf);

        return $ruta;
    }

    public function testSinArchivoDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No se recibió el PDF.', $respuesta['json']['mensaje']);
    }

    public function testArchivoQueNoEsPdfDevuelve400(): void
    {
        $rutaTxt = tempnam(sys_get_temp_dir(), 'caces_no_pdf_') . '.txt';
        file_put_contents($rutaTxt, 'esto no es un PDF');

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => [
                'archivo' => new CURLFile($rutaTxt, 'text/plain', 'no_es_pdf.txt'),
            ],
        ]);

        unlink($rutaTxt);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('El archivo debe ser un PDF válido.', $respuesta['json']['mensaje']);
    }

    public function testPdfConTotalReportadoExtraeMatriculadosPeriodoYCohorte(): void
    {
        $ruta = $this->crearPdfConTexto(
            'Periodo: Marzo 2025 - Agosto 2025 Fecha Inicio: 01/03/2025 '
            . 'Cohorte B2025 Total alumnos por ciclo: 35'
        );

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'matriculados.pdf'),
            ],
        ]);

        unlink($ruta);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('El PDF fue leído correctamente.', $respuesta['json']['mensaje']);
        $this->assertSame(35, $respuesta['json']['datos']['matriculados']);
        $this->assertSame('Marzo 2025 - Agosto 2025', $respuesta['json']['datos']['periodo']);
        $this->assertSame('B2025', $respuesta['json']['datos']['cohorte_detectada']);
    }

    public function testPdfConVariantesDeTotalDeAlumnosTambienLoDetecta(): void
    {
        // Confirma el segundo patrón ("Total de alumnos:"), no solo el
        // primero ("Total alumnos por ciclo:") -- mismo orden que el
        // original.
        $ruta = $this->crearPdfConTexto('Total de alumnos: 12');

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'matriculados.pdf'),
            ],
        ]);

        unlink($ruta);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertSame(12, $respuesta['json']['datos']['matriculados']);
        $this->assertNull($respuesta['json']['datos']['periodo']);
        $this->assertNull($respuesta['json']['datos']['cohorte_detectada']);
    }

    public function testPdfSinTotalReportadoCuentaIdentificacionesUnicasComoRespaldo(): void
    {
        // Sin ningún patrón de "Total...:", cae al respaldo de contar
        // números de 10 dígitos únicos -- 3 cédulas distintas + 1 repetida.
        $ruta = $this->crearPdfConTexto(
            'Listado de alumnos 1723456789 1798765432 0912345678 1723456789 fin del listado'
        );

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'listado.pdf'),
            ],
        ]);

        unlink($ruta);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertSame(3, $respuesta['json']['datos']['matriculados']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
