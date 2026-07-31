<?php

declare(strict_types=1);

namespace Tests\Integration\Evidencias;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /evidencias/compartidas (Parte 5 del plan de
 * migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/evidencias/obtener_compartidas.php).
 *
 * Fixture: inserta directo en `evidencias` (id_evaluacion=1,
 * id_catalogo=5 -> CatalogoEvidenciasSeeder: id_indicador=1 "Syllabus") y en
 * `indicador_evidencia` una fila que comparte esa evidencia con el
 * indicador destino=2 ("Seguimiento de Syllabus", IndicadoresSeeder). Con
 * eso se confirma tanto el JOIN con indicador_evidencia (relacion_destino)
 * como el JOIN con indicadores (indicador_origen) y el filtro
 * `ce.id_indicador <> id_indicador_destino`.
 */
final class CompartidasTest extends IntegrationTestCase
{
    private const ID_EVALUACION = 1;
    private const ID_CATALOGO = 5;
    private const ID_INDICADOR_ORIGEN = 1;
    private const ID_INDICADOR_DESTINO = 2;

    private ?int $idEvidenciaInsertada = null;

    protected function tearDown(): void
    {
        // Deja las tablas como estaban para no afectar otros tests de esta
        // misma clase ni de otras clases que reutilicen el mismo seed.
        $conexion = self::conexionBd();

        if ($this->idEvidenciaInsertada !== null) {
            $conexion->query(
                'DELETE FROM indicador_evidencia WHERE id_evidencia = ' . $this->idEvidenciaInsertada
            );
            $conexion->query(
                'DELETE FROM evidencias WHERE id_evidencia = ' . $this->idEvidenciaInsertada
            );
            $this->idEvidenciaInsertada = null;
        }
    }

    public function testCompartidasSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/evidencias/compartidas');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Debe enviar la evaluación y el indicador destino.', $respuesta['json']['mensaje']);
    }

    public function testCompartidasConSoloIdEvaluacionDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/evidencias/compartidas?id_evaluacion=' . self::ID_EVALUACION);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testCompartidasSinEvidenciasDevuelveListaVacia(): void
    {
        // Ningún fixture insertado: mismo comportamiento que el original
        // (no hay caso 404, una evaluación/indicador sin evidencia
        // compartida todavía es un 200 con "datos" vacío).
        $respuesta = $this->peticion(
            'GET',
            '/evidencias/compartidas?id_evaluacion=' . self::ID_EVALUACION
                . '&id_indicador_destino=' . self::ID_INDICADOR_DESTINO
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
        $this->assertArrayNotHasKey('mensaje', $respuesta['json']);
    }

    public function testCompartidasConEvidenciaCompartidaDevuelveLosDatosCruzadosConElIndicadorOrigen(): void
    {
        $conexion = self::conexionBd();
        $conexion->query(
            "INSERT INTO evidencias (id_catalogo, id_evaluacion, codigo_evidencia, descripcion, nombre_archivo, tipo, url_archivo, id_usuario)
             VALUES (" . self::ID_CATALOGO . ", " . self::ID_EVALUACION . ", 'DOC.SYL.01', 'Malla curricular vigente', 'malla.pdf', 'pdf', 'https://drive.example/malla.pdf', 1)"
        );
        $this->idEvidenciaInsertada = $conexion->insert_id;

        $conexion->query(
            'INSERT INTO indicador_evidencia (id_indicador, id_evidencia) VALUES ('
            . self::ID_INDICADOR_DESTINO . ', ' . $this->idEvidenciaInsertada . ')'
        );

        $respuesta = $this->peticion(
            'GET',
            '/evidencias/compartidas?id_evaluacion=' . self::ID_EVALUACION
                . '&id_indicador_destino=' . self::ID_INDICADOR_DESTINO
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertCount(1, $respuesta['json']['datos']);

        $fila = $respuesta['json']['datos'][0];
        $this->assertSame(self::ID_CATALOGO, $fila['id_catalogo']);
        $this->assertSame(self::ID_EVALUACION, $fila['id_evaluacion']);
        $this->assertSame('DOC.SYL.01', $fila['codigo_evidencia']);
        $this->assertSame('malla.pdf', $fila['nombre_archivo']);
        // Datos cruzados con catalogo_evidencias (id_catalogo=5): confirma
        // el INNER JOIN con catalogo_evidencias, no solo el SELECT plano.
        $this->assertSame('Malla curricular', $fila['titulo_corto']);
        $this->assertSame('Malla_Curricular', $fila['nombre_archivo_base']);
        // Datos cruzados con indicadores (indicador_origen): confirma el
        // segundo INNER JOIN, específico de este endpoint (no lo tiene
        // guardadas()).
        $this->assertSame(self::ID_INDICADOR_ORIGEN, $fila['id_indicador_origen']);
        $this->assertSame('Syllabus', $fila['indicador_origen']);
    }

    public function testCompartidasNoDevuelveEvidenciaSiElIndicadorOrigenEsIgualAlDestino(): void
    {
        // Misma evidencia/indicador_evidencia que el test anterior, pero
        // pidiendo como destino el MISMO indicador de origen (1): el
        // filtro `ce.id_indicador <> id_indicador_destino` debe excluirla.
        $conexion = self::conexionBd();
        $conexion->query(
            "INSERT INTO evidencias (id_catalogo, id_evaluacion, codigo_evidencia, descripcion, nombre_archivo, tipo, url_archivo, id_usuario)
             VALUES (" . self::ID_CATALOGO . ", " . self::ID_EVALUACION . ", 'DOC.SYL.01', 'Malla curricular vigente', 'malla.pdf', 'pdf', 'https://drive.example/malla.pdf', 1)"
        );
        $this->idEvidenciaInsertada = $conexion->insert_id;

        $conexion->query(
            'INSERT INTO indicador_evidencia (id_indicador, id_evidencia) VALUES ('
            . self::ID_INDICADOR_ORIGEN . ', ' . $this->idEvidenciaInsertada . ')'
        );

        $respuesta = $this->peticion(
            'GET',
            '/evidencias/compartidas?id_evaluacion=' . self::ID_EVALUACION
                . '&id_indicador_destino=' . self::ID_INDICADOR_ORIGEN
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
    }

    public function testCompartidasConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', '/evidencias/compartidas');

        $this->assertSame(405, $respuesta['status']);
    }
}
