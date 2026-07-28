<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Repositorio mínimo para `evidencia_asignatura`, tabla COMPARTIDA entre I2
 * (Seguimiento Syllabus) y I3 (Tutorías Académicas) -- mismo comentario que
 * ya deja SeguimientoSyllabusRepository::evidenciasVigentesPorAsignatura()
 * y TutoriasRepository::evidenciasVigentesPorAsignatura(). No duplica
 * lógica de negocio de I2/I3 (esos repos siguen siendo los dueños de
 * "listar evidencia de una asignatura"); este repo solo resuelve UNA fila
 * por su ID, para el visor nuevo de evidencia_asignatura -- paso 6 del
 * plan de interruptor de almacenamiento, parte pendiente explícita del
 * paso 5 (ver_archivo.php solo ramificó I4/I5, ver MEMORIA §68.2/§68.5).
 *
 * Al ser una tabla compartida entre indicadores, este repositorio no
 * pertenece ni a SeguimientoSyllabusRepository ni a TutoriasRepository --
 * así el controlador del visor no depende de ninguno de los dos.
 */
final class EvidenciaAsignaturaRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * @return array{nombre_archivo: string, url_archivo: string, tipo: string}|null
     */
    public function obtenerPorId(int $idEvidenciaAsig): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT nombre_archivo, url_archivo, tipo FROM evidencia_asignatura WHERE id_evidencia_asig = ?'
        );
        $stmt->bind_param('i', $idEvidenciaAsig);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila ?: null;
    }
}
