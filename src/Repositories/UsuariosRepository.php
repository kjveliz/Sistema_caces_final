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
}
