<?php

declare(strict_types=1);

namespace Tests\Integration\Evidencias;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /evidencias/guardar (Parte 7 del plan de
 * migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/evidencias/guardar_evidencia.php, cuarta y última
 * Parte del Grupo B).
 *
 * Usa id_catalogo=5 (CatalogoEvidenciasSeeder: id_indicador=1 "Syllabus")
 * e id_evaluacion=2 (EvaluacionesSeeder), distinto del id_evaluacion=1 que
 * ya usan GuardadasTest/CompartidasTest, para no compartir fixtures con
 * esas otras clases.
 */
final class GuardarTest extends IntegrationTestCase
{
    private const ID_CATALOGO = 5;
    private const ID_EVALUACION = 2;
    private const ID_INDICADOR_ORIGEN = 1;
    private const ID_INDICADOR_DESTINO_COMPARTIDO = 2;

    protected function tearDown(): void
    {
        // Deja las tablas como estaban para no afectar otros tests de esta
        // misma clase ni de otras clases que reutilicen el mismo seed.
        $conexion = self::conexionBd();
        $conexion->query(
            'DELETE FROM indicador_evidencia WHERE id_evidencia IN (
                SELECT id_evidencia FROM evidencias
                WHERE id_evaluacion = ' . self::ID_EVALUACION . '
                  AND id_catalogo = ' . self::ID_CATALOGO . '
            )'
        );
        $conexion->query(
            'DELETE FROM evidencias WHERE id_evaluacion = ' . self::ID_EVALUACION
            . ' AND id_catalogo = ' . self::ID_CATALOGO
        );
        $conexion->query(
            'DELETE FROM compartir_catalogo WHERE id_catalogo_origen = ' . self::ID_CATALOGO
        );
    }

    private function datosValidos(): array
    {
        return [
            'id_catalogo' => self::ID_CATALOGO,
            'id_evaluacion' => self::ID_EVALUACION,
            'codigo_evidencia' => 'DOC.SYL.01',
            'descripcion' => 'Malla curricular vigente',
            'nombre_archivo' => 'malla.pdf',
            'tipo' => 'pdf',
            'url_archivo' => 'https://drive.example/malla.pdf',
        ];
    }

    public function testGuardarSinSesionDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', '/evidencias/guardar', ['json' => $this->datosValidos()]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('La sesión no está activa.', $respuesta['json']['mensaje']);
    }

    public function testGuardarSinDatosDevuelve400(): void
    {
        $this->loguearComo('administrador@demo.local');

        $respuesta = $this->peticion('POST', '/evidencias/guardar', ['json' => []]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame(
            'Faltan datos obligatorios para registrar la evidencia.',
            $respuesta['json']['mensaje']
        );
    }

    public function testGuardarCasoFelizInsertaEvidenciaYLaRelacionaConSuIndicadorDeOrigen(): void
    {
        $this->loguearComo('administrador@demo.local');

        $respuesta = $this->peticion('POST', '/evidencias/guardar', ['json' => $this->datosValidos()]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Evidencia guardada correctamente.', $respuesta['json']['mensaje']);
        $this->assertIsInt($respuesta['json']['id_evidencia']);
        $this->assertSame(0, $respuesta['json']['relaciones_compartidas']);

        $idEvidencia = $respuesta['json']['id_evidencia'];

        $conexion = self::conexionBd();
        $fila = $conexion->query(
            'SELECT * FROM evidencias WHERE id_evidencia = ' . $idEvidencia
        )->fetch_assoc();

        $this->assertNotNull($fila);
        $this->assertSame(self::ID_CATALOGO, (int) $fila['id_catalogo']);
        $this->assertSame(self::ID_EVALUACION, (int) $fila['id_evaluacion']);
        $this->assertSame('DOC.SYL.01', $fila['codigo_evidencia']);
        $this->assertSame('malla.pdf', $fila['nombre_archivo']);

        $relacionOrigen = $conexion->query(
            'SELECT * FROM indicador_evidencia WHERE id_evidencia = ' . $idEvidencia
            . ' AND id_indicador = ' . self::ID_INDICADOR_ORIGEN
        )->fetch_assoc();

        $this->assertNotNull($relacionOrigen);
    }

    public function testGuardarConReglaDeComparticionActivaComparteAutomaticamente(): void
    {
        $conexion = self::conexionBd();
        $conexion->query(
            'INSERT INTO compartir_catalogo (id_catalogo_origen, id_indicador_destino, activo) VALUES ('
            . self::ID_CATALOGO . ', ' . self::ID_INDICADOR_DESTINO_COMPARTIDO . ', 1)'
        );

        $this->loguearComo('administrador@demo.local');

        $respuesta = $this->peticion('POST', '/evidencias/guardar', ['json' => $this->datosValidos()]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(1, $respuesta['json']['relaciones_compartidas']);

        $idEvidencia = $respuesta['json']['id_evidencia'];

        $relacionCompartida = $conexion->query(
            'SELECT * FROM indicador_evidencia WHERE id_evidencia = ' . $idEvidencia
            . ' AND id_indicador = ' . self::ID_INDICADOR_DESTINO_COMPARTIDO
        )->fetch_assoc();

        $this->assertNotNull($relacionCompartida);
    }

    public function testGuardarConLaMismaEvaluacionYCatalogoActualizaEnVezDeDuplicar(): void
    {
        $this->loguearComo('administrador@demo.local');

        $primeraRespuesta = $this->peticion('POST', '/evidencias/guardar', ['json' => $this->datosValidos()]);

        $segundaRespuesta = $this->peticion('POST', '/evidencias/guardar', [
            'json' => array_merge($this->datosValidos(), [
                'descripcion' => 'Malla curricular actualizada',
                'nombre_archivo' => 'malla-v2.pdf',
                'url_archivo' => 'https://drive.example/malla-v2.pdf',
            ]),
        ]);

        $this->assertSame(200, $segundaRespuesta['status']);
        $this->assertSame(
            $primeraRespuesta['json']['id_evidencia'],
            $segundaRespuesta['json']['id_evidencia']
        );

        $conexion = self::conexionBd();
        $filas = $conexion->query(
            'SELECT * FROM evidencias WHERE id_evaluacion = ' . self::ID_EVALUACION
            . ' AND id_catalogo = ' . self::ID_CATALOGO
        );

        $this->assertSame(1, $filas->num_rows);

        $fila = $filas->fetch_assoc();
        $this->assertSame('Malla curricular actualizada', $fila['descripcion']);
        $this->assertSame('malla-v2.pdf', $fila['nombre_archivo']);
    }

    public function testGuardarConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/evidencias/guardar');

        $this->assertSame(405, $respuesta['status']);
    }
}
