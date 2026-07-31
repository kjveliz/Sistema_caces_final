<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;
use RuntimeException;

/**
 * Repository nuevo del subgrupo de usuarios del Grupo E (Administración) del
 * plan de migración de PHP suelto a Slim (ver plan_migracion_slim_legacy_v3.txt
 * §1 Grupo E, §3 Parte 14): no había nada existente que cubriera la tabla
 * `usuarios` fuera de AuthRepository (que sólo resuelve Login/Me, un usuario
 * por vez, no un listado completo). Arranca en la Parte 14 con listar().
 */
final class UsuariosRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * Misma consulta exacta que el SELECT original de
     * api/administracion/usuarios/listar.php.
     *
     * @return array<int, array{id_usuario: int, nombres: string, apellidos: string, correo: string, rol: string, activo: int}>
     */
    public function listar(): array
    {
        $sql = '
            SELECT
                id_usuario,
                nombres,
                apellidos,
                correo,
                rol,
                activo
            FROM usuarios
            ORDER BY apellidos, nombres
        ';

        $resultado = $this->conexion->query($sql);

        if (!$resultado) {
            throw new RuntimeException($this->conexion->error);
        }

        $usuarios = [];

        while ($fila = $resultado->fetch_assoc()) {
            $usuarios[] = [
                'id_usuario' => (int) $fila['id_usuario'],
                'nombres' => $fila['nombres'],
                'apellidos' => $fila['apellidos'],
                'correo' => $fila['correo'],
                'rol' => $fila['rol'],
                'activo' => (int) $fila['activo'],
            ];
        }

        return $usuarios;
    }

    /**
     * Crea un usuario nuevo. Reemplaza al INSERT de
     * api/administracion/usuarios/crear.php (Parte 15 del plan de
     * migración slim-legacy, ver plan_migracion_slim_legacy_v3.txt §3
     * Grupo E, segunda Parte del subgrupo de usuarios). $contrasenaHash ya
     * viene hasheado (password_hash) desde el Controller, mismo criterio
     * que el original -- este método no conoce la contraseña en texto
     * plano. Igual que crearCohorteConEvaluacion() en
     * SeguimientoSyllabusRepository, no se chequea a mano el error de
     * mysqli: se deja propagar (incluyendo un eventual 1062 de correo
     * duplicado), y es el Controller quien distingue por código de error.
     *
     * @return array{id_usuario: int, nombres: string, apellidos: string, correo: string, rol: string, activo: int}
     */
    public function crear(
        string $nombres,
        string $apellidos,
        string $correo,
        string $contrasenaHash,
        string $rol,
        int $activo,
    ): array {
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuarios (nombres, apellidos, correo, contrasena, rol, activo) VALUES (?, ?, ?, ?, ?, ?)',
        );
        $stmt->bind_param(
            'sssssi',
            $nombres,
            $apellidos,
            $correo,
            $contrasenaHash,
            $rol,
            $activo,
        );
        $stmt->execute();

        return [
            'id_usuario' => (int) $stmt->insert_id,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $correo,
            'rol' => $rol,
            'activo' => $activo,
        ];
    }
}
