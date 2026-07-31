<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Repository del Grupo A (Autenticación) del plan de migración de PHP
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en
 * la Parte 1 con solo el método que necesita Login.php; Logout.php (Parte
 * 2) no toca la BD y Me.php (Parte 3) reutiliza este mismo
 * usuarioPorCorreo() indirectamente vía $_SESSION, así que este Repository
 * probablemente no crezca mucho más allá de este único método.
 */
final class AuthRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * Misma consulta exacta que el SELECT original de api/auth/Login.php.
     *
     * @return array{id_usuario: int, nombres: string, apellidos: string, correo: string, contrasena: string, rol: string, activo: int}|null
     */
    public function usuarioPorCorreo(string $correo): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_usuario, nombres, apellidos, correo, contrasena, rol, activo FROM usuarios WHERE correo = ? LIMIT 1'
        );

        if (!$stmt) {
            throw new \RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('s', $correo);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();

        if ($fila === null) {
            return null;
        }

        return [
            'id_usuario' => (int) $fila['id_usuario'],
            'nombres' => $fila['nombres'],
            'apellidos' => $fila['apellidos'],
            'correo' => $fila['correo'],
            'contrasena' => $fila['contrasena'],
            'rol' => $fila['rol'],
            'activo' => (int) $fila['activo'],
        ];
    }
}
