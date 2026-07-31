<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Repository del Grupo A (Autenticación) del plan de migración de PHP
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3). Arrancó en
 * la Parte 1 con usuarioPorCorreo() (Login.php); Logout.php (Parte 2) no
 * tocó la BD; usuarioPorId() se agrega acá en la Parte 3 para Me.php.
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

    /**
     * Misma consulta exacta que el SELECT original de api/auth/Me.php
     * (sin la columna `contrasena`, a diferencia de usuarioPorCorreo() de
     * arriba -- el original tampoco la selecciona acá).
     *
     * @return array{id_usuario: int, nombres: string, apellidos: string, correo: string, rol: string, activo: int}|null
     */
    public function usuarioPorId(int $idUsuario): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_usuario, nombres, apellidos, correo, rol, activo FROM usuarios WHERE id_usuario = ? LIMIT 1'
        );

        if (!$stmt) {
            throw new \RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('i', $idUsuario);
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
            'rol' => $fila['rol'],
            'activo' => (int) $fila['activo'],
        ];
    }
}
