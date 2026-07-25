<?php

declare(strict_types=1);

namespace App\Infra;

use mysqli;
use RuntimeException;

/**
 * Conecta a MySQL a partir de las MISMAS variables de entorno que ya usa
 * api/conexion.php (DB_HOST, DB_USER, DB_PASS, DB_NAME) — sin duplicar
 * credenciales ni agregar una segunda fuente de configuración.
 *
 * Se separa de api/conexion.php a propósito: ese archivo hace
 * header()/echo/exit directamente si la conexión falla, lo cual no encaja
 * con el flujo de respuestas de Slim (que arma la respuesta HTTP a través
 * del objeto Response, no con echo+exit). Acá, si la conexión falla, se
 * lanza una excepción y el manejador de errores de Slim la convierte en un
 * 500 con el mismo formato {ok, mensaje} — ver public/index.php.
 */
final class Database
{
    public static function conectar(): mysqli
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $usuario = $_ENV['DB_USER'] ?? 'root';
        $contrasena = $_ENV['DB_PASS'] ?? '';
        $baseDatos = $_ENV['DB_NAME'] ?? 'evaluacion_caces';

        $conexion = new mysqli($host, $usuario, $contrasena, $baseDatos);

        if ($conexion->connect_error) {
            throw new RuntimeException('Error de conexión con la base de datos: ' . $conexion->connect_error);
        }

        $conexion->set_charset('utf8mb4');

        return $conexion;
    }
}
