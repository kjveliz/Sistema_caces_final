<?php

declare(strict_types=1);

final class InicializadorCohorte
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * @return array{periodos_creados:int, asignaturas_creadas:int}
     */
    public function copiarDesdeCohorte(
        int $idCohorteOrigen,
        int $idCohorteDestino,
        int $idCarreraEsperada
    ): array {
        if ($idCohorteOrigen <= 0 || $idCohorteDestino <= 0) {
            throw new InvalidArgumentException(
                'Las cohortes de origen y destino son obligatorias.'
            );
        }

        if ($idCohorteOrigen === $idCohorteDestino) {
            throw new InvalidArgumentException(
                'La cohorte de origen no puede ser igual a la cohorte de destino.'
            );
        }

        $origen = $this->obtenerCohorte($idCohorteOrigen);
        $destino = $this->obtenerCohorte($idCohorteDestino);

        if ($origen === null) {
            throw new RuntimeException('La cohorte de origen no existe.');
        }

        if ($destino === null) {
            throw new RuntimeException('La cohorte de destino no existe.');
        }

        if (
            (int) $origen['id_carrera'] !== $idCarreraEsperada ||
            (int) $destino['id_carrera'] !== $idCarreraEsperada
        ) {
            throw new RuntimeException(
                'Las cohortes deben pertenecer a la misma carrera.'
            );
        }

        if ($this->contarPeriodos($idCohorteDestino) > 0) {
            throw new RuntimeException(
                'La cohorte de destino ya posee una estructura académica.'
            );
        }

        $periodosOrigen = $this->obtenerPeriodos($idCohorteOrigen);

        if ($periodosOrigen === []) {
            throw new RuntimeException(
                'La cohorte de origen no tiene períodos académicos para copiar.'
            );
        }

        $periodosCreados = 0;
        $asignaturasCreadas = 0;

        foreach ($periodosOrigen as $periodoOrigen) {
            $idPeriodoOrigen = (int) $periodoOrigen['id_periodoacademico'];

            $idPeriodoDestino = $this->crearPeriodo(
                $idCohorteDestino,
                (string) $periodoOrigen['nombre'],
                $periodoOrigen['orden'] !== null
                    ? (int) $periodoOrigen['orden']
                    : null
            );

            $periodosCreados++;

            foreach ($this->obtenerAsignaturas($idPeriodoOrigen) as $asignatura) {
                $this->crearAsignatura(
                    $idPeriodoDestino,
                    (string) $asignatura['nombre']
                );

                $asignaturasCreadas++;
            }
        }

        return [
            'periodos_creados' => $periodosCreados,
            'asignaturas_creadas' => $asignaturasCreadas,
        ];
    }

    private function obtenerCohorte(int $idCohorte): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_cohorte, id_carrera, nombre_cohorte
             FROM cohortes
             WHERE id_cohorte = ?
             LIMIT 1'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudo preparar la consulta de cohorte: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $fila ?: null;
    }

    private function contarPeriodos(int $idCohorte): int
    {
        $stmt = $this->conexion->prepare(
            'SELECT COUNT(*) AS total
             FROM periodo_academico
             WHERE id_cohorte = ?'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudo verificar la estructura académica: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($fila['total'] ?? 0);
    }

    private function obtenerPeriodos(int $idCohorte): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_periodoacademico, nombre, orden
             FROM periodo_academico
             WHERE id_cohorte = ?
             ORDER BY orden, id_periodoacademico'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudieron consultar los períodos académicos: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $filas;
    }

    private function obtenerAsignaturas(int $idPeriodo): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT nombre
             FROM asignatura
             WHERE id_periodoacademico = ?
             ORDER BY id_asignatura'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudieron consultar las asignaturas: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('i', $idPeriodo);
        $stmt->execute();

        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $filas;
    }

    private function crearPeriodo(
        int $idCohorte,
        string $nombre,
        ?int $orden
    ): int {
        $stmt = $this->conexion->prepare(
            'INSERT INTO periodo_academico
                (id_cohorte, nombre, orden, fecha_inicio, fecha_fin)
             VALUES (?, ?, ?, NULL, NULL)'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudo preparar la creación del PAO: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('isi', $idCohorte, $nombre, $orden);

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'No se pudo crear el PAO ' . $nombre . ': ' . $stmt->error
            );
        }

        $idPeriodo = (int) $stmt->insert_id;
        $stmt->close();

        return $idPeriodo;
    }

    private function crearAsignatura(
        int $idPeriodo,
        string $nombre
    ): void {
        $docente = null;

        $stmt = $this->conexion->prepare(
            'INSERT INTO asignatura
                (id_periodoacademico, nombre, docente, fecha_creacion)
             VALUES (?, ?, ?, CURDATE())'
        );

        if (!$stmt) {
            throw new RuntimeException(
                'No se pudo preparar la creación de asignatura: ' .
                $this->conexion->error
            );
        }

        $stmt->bind_param('iss', $idPeriodo, $nombre, $docente);

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'No se pudo crear la asignatura ' .
                $nombre .
                ': ' .
                $stmt->error
            );
        }

        $stmt->close();
    }
}