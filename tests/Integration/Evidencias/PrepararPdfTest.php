<?php

declare(strict_types=1);

namespace Tests\Integration\Evidencias;

use CURLFile;
use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /evidencias/preparar-pdf (Parte 8 del plan
 * de migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/evidencias/preparar_pdf.php), quinta y última Parte
 * del Grupo B.
 *
 * Usa el catálogo real del seeder (CatalogoEvidenciasSeeder): id_catalogo=1
 * (DOC.TIT.01, espera PDF, caso normal) e id_catalogo=12 (DOC.SEG.05,
 * espera CSV, único slot especial). No requiere sesión (el original tampoco
 * valida $_SESSION['id_usuario']), así que no hay caso 401. El PDF de
 * prueba usa el mismo truco mínimo que EvidenciaAsignaturaSubirTest (le
 * alcanza a finfo para detectarlo como application/pdf, no hace falta que
 * Smalot\PdfParser pueda leerlo -- este endpoint no extrae texto, solo
 * valida mime + extensión).
 */
final class PrepararPdfTest extends IntegrationTestCase
{
    private const RUTA = '/evidencias/preparar-pdf';

    private function crearPdfDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'caces_preparar_pdf_') . '.pdf';
        $contenido = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 3 3]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function crearCsvDePrueba(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'caces_preparar_csv_') . '.csv';
        file_put_contents($ruta, "pregunta,respuesta\n1,Excelente\n2,Bueno\n");

        return $ruta;
    }

    private function camposBase(array $sobrescribir = []): array
    {
        return array_merge([
            'id_catalogo' => '1',
            'codigo_carrera' => 'DESSOF',
            'cohorte' => 'A2026',
            'criterio' => '4',
            'indicador' => '1',
        ], $sobrescribir);
    }

    public function testFaltanDatosDevuelve400(): void
    {
        $ruta = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(['id_catalogo' => '0']), [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'malla.pdf'),
            ]),
        ]);

        unlink($ruta);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Faltan datos para procesar el archivo.', $respuesta['json']['mensaje']);
    }

    public function testSinArchivoDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => $this->camposBase(),
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No se recibió ningún archivo.', $respuesta['json']['mensaje']);
    }

    public function testCatalogoInexistenteDevuelve404(): void
    {
        $ruta = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(['id_catalogo' => '9999']), [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'malla.pdf'),
            ]),
        ]);

        unlink($ruta);

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('La evidencia seleccionada no existe o está inactiva.', $respuesta['json']['mensaje']);
    }

    public function testArchivoQueNoEsPdfDevuelve400ParaCatalogoNormal(): void
    {
        $rutaTxt = tempnam(sys_get_temp_dir(), 'caces_no_pdf_') . '.txt';
        file_put_contents($rutaTxt, 'esto no es un PDF');

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'archivo' => new CURLFile($rutaTxt, 'text/plain', 'no_es_pdf.txt'),
            ]),
        ]);

        unlink($rutaTxt);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Solo se aceptan archivos PDF válidos.', $respuesta['json']['mensaje']);
    }

    public function testCasoFelizPdfGeneraNombreTecnico(): void
    {
        // id_catalogo=1 -> DOC.TIT.01, orden=1, nombre_archivo_base=Estudiantes_Graduados.
        $ruta = $this->crearPdfDePrueba();

        $respuesta = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(), [
                'archivo' => new CURLFile($ruta, 'application/pdf', 'Estudiantes Graduados 2026.pdf'),
            ]),
        ]);

        unlink($ruta);

        $this->assertSame(200, $respuesta['status'], (string) $respuesta['body']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('PDF validado y nombre generado correctamente.', $respuesta['json']['mensaje']);
        $this->assertSame(1, $respuesta['json']['datos']['id_catalogo']);
        $this->assertSame('DOC.TIT.01', $respuesta['json']['datos']['codigo_evidencia']);
        $this->assertSame('Estudiantes graduados', $respuesta['json']['datos']['titulo_corto']);
        $this->assertSame('Estudiantes Graduados 2026.pdf', $respuesta['json']['datos']['nombre_original']);
        $this->assertSame(
            'DESSOF.A2026.C4.1.1.Estudiantes_Graduados.pdf',
            $respuesta['json']['datos']['nombre_generado'],
        );
        $this->assertSame('application/pdf', $respuesta['json']['datos']['tipo']);
        $this->assertGreaterThan(0, $respuesta['json']['datos']['tamano']);
    }

    public function testSlotDocSeg05AceptaCsvYRechazaPdf(): void
    {
        // id_catalogo=12 -> DOC.SEG.05, orden=5, único slot que espera CSV
        // en vez de PDF (identificado por codigo_evidencia de servidor).
        $rutaCsv = $this->crearCsvDePrueba();

        $respuestaCsv = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(['id_catalogo' => '12']), [
                'archivo' => new CURLFile($rutaCsv, 'text/csv', 'resultados.csv'),
            ]),
        ]);

        unlink($rutaCsv);

        $this->assertSame(200, $respuestaCsv['status'], (string) $respuestaCsv['body']);
        $this->assertTrue($respuestaCsv['json']['ok']);
        $this->assertSame('CSV validado y nombre generado correctamente.', $respuestaCsv['json']['mensaje']);
        $this->assertSame('DOC.SEG.05', $respuestaCsv['json']['datos']['codigo_evidencia']);
        $this->assertStringEndsWith('.csv', $respuestaCsv['json']['datos']['nombre_generado']);

        // El mismo slot rechaza un PDF: el original exige CSV para este
        // codigo_evidencia puntual, sin importar que un PDF sí sea válido
        // como formato en general.
        $rutaPdf = $this->crearPdfDePrueba();

        $respuestaPdf = $this->peticion('POST', self::RUTA, [
            'multipart' => array_merge($this->camposBase(['id_catalogo' => '12']), [
                'archivo' => new CURLFile($rutaPdf, 'application/pdf', 'resultados.pdf'),
            ]),
        ]);

        unlink($rutaPdf);

        $this->assertSame(400, $respuestaPdf['status']);
        $this->assertFalse($respuestaPdf['json']['ok']);
        $this->assertSame('Solo se aceptan archivos CSV válidos.', $respuestaPdf['json']['mensaje']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
