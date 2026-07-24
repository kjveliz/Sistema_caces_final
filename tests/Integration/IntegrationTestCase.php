<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base para los tests de integración de la Fase 5 (ver plan de mejora,
 * FASE 5 -> "tests de integración para los endpoints críticos... corriendo
 * contra una base de datos de prueba separada, usar los seeders de la
 * Fase 2").
 *
 * A diferencia de tests/Unit (que incluye directamente los archivos y
 * testea funciones puras), acá se levanta un servidor PHP embebido real
 * (`php -S`) y se le pega por HTTP a los endpoints tal cual como los usa el
 * frontend -- es la única forma razonable de testear estos endpoints sin
 * reescribirlos primero a un framework con inyección de dependencias (eso
 * es la Fase 3, todavía no hecha). Antes de correr la clase completa:
 *   1. `phinx rollback -e testing -t 0` (deja evaluacion_caces_test vacía,
 *      ignorando el error si nunca se había migrado).
 *   2. `phinx migrate -e testing` (esquema limpio).
 *   3. `phinx seed:run -e testing` (los 8 seeders de la Fase 2, mismos
 *      datos de ejemplo que ya usa el equipo en local).
 *
 * El servidor se levanta con APP_ENV=testing en su entorno, que es lo que
 * activa el seam de subirArchivoDrive() en _google_drive.php (ver ese
 * archivo) para no llamar a Google Drive real desde un test.
 *
 * Requiere una base de datos MySQL/MariaDB alcanzable con las credenciales
 * de la sección "testing" de phinx.php (por defecto root sin contraseña en
 * localhost, igual que el resto del proyecto). Si no hay una BD disponible,
 * estos tests fallan con un mensaje claro en vez de un error críptico.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static string $baseUrl;
    private static $serverProcess = null;
    protected static string $cookieJar;
    private static string $repoRoot;
    private static array $serverEnv;

    public static function setUpBeforeClass(): void
    {
        self::$repoRoot = dirname(__DIR__, 2);
        self::$serverEnv = array_merge(getenv() ?: [], [
            'APP_ENV' => 'testing',
            'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
            'DB_NAME' => 'evaluacion_caces_test',
            'DB_USER' => getenv('DB_USER') ?: 'root',
            'DB_PASS' => getenv('DB_PASS') ?: '',
            'DB_PORT' => getenv('DB_PORT') ?: '3306',
        ]);

        self::prepararBaseDeDatos();

        $port = self::puertoLibre();
        self::$baseUrl = "http://127.0.0.1:{$port}";
        self::$cookieJar = tempnam(sys_get_temp_dir(), 'caces_cookies_');

        $descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $comando = sprintf(
            'exec php -d variables_order=EGPCS -S 127.0.0.1:%d -t %s',
            $port,
            escapeshellarg(self::$repoRoot)
        );

        self::$serverProcess = proc_open($comando, $descriptores, $pipes, self::$repoRoot, self::$serverEnv);

        if (!is_resource(self::$serverProcess)) {
            throw new RuntimeException('No se pudo levantar el servidor PHP embebido para los tests de integración.');
        }

        self::esperarServidor($port);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }

        if (isset(self::$cookieJar) && is_file(self::$cookieJar)) {
            unlink(self::$cookieJar);
        }
    }

    private static function prepararBaseDeDatos(): void
    {
        // Phinx no crea la base si no existe (ver INSTRUCCIONES_fase2.md,
        // que pide crearla a mano antes de la primera corrida) -- se crea
        // acá para que los tests de integración no requieran ese paso
        // manual previo.
        $conexionServidor = new mysqli(
            self::$serverEnv['DB_HOST'],
            self::$serverEnv['DB_USER'],
            self::$serverEnv['DB_PASS'],
            '',
            (int) self::$serverEnv['DB_PORT']
        );
        if ($conexionServidor->connect_error) {
            throw new RuntimeException(
                'No se pudo conectar a MySQL/MariaDB para preparar la base de prueba: ' . $conexionServidor->connect_error
            );
        }
        $conexionServidor->query('CREATE DATABASE IF NOT EXISTS evaluacion_caces_test CHARACTER SET utf8mb4');
        $conexionServidor->close();

        self::correrPhinx(['rollback', '-e', 'testing', '-t', '0', '--force'], permitirFallo: true);
        self::correrPhinx(['migrate', '-e', 'testing']);
        self::correrPhinx(['seed:run', '-e', 'testing']);
    }

    private static function correrPhinx(array $args, bool $permitirFallo = false): void
    {
        $binario = self::$repoRoot . '/vendor/bin/phinx';
        $comando = $binario . ' ' . implode(' ', array_map('escapeshellarg', $args));

        $descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proceso = proc_open($comando, $descriptores, $pipes, self::$repoRoot, self::$serverEnv);

        if (!is_resource($proceso)) {
            throw new RuntimeException("No se pudo ejecutar: {$comando}");
        }

        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proceso);

        if ($codigo !== 0 && !$permitirFallo) {
            throw new RuntimeException(
                "Falló '{$comando}' (código {$codigo}).\n--- stdout ---\n{$salida}\n--- stderr ---\n{$error}\n\n" .
                'Verificá que haya una base MySQL/MariaDB alcanzable con las credenciales del entorno "testing" de phinx.php.'
            );
        }
    }

    private static function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("No se pudo reservar un puerto libre: {$errstr}");
        }
        $nombre = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($nombre, strrpos($nombre, ':') + 1);
    }

    private static function esperarServidor(int $port, float $timeoutSegundos = 5.0): void
    {
        $inicio = microtime(true);
        while (microtime(true) - $inicio < $timeoutSegundos) {
            $conexion = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($conexion !== false) {
                fclose($conexion);
                return;
            }
            usleep(50000);
        }
        throw new RuntimeException("El servidor PHP embebido no respondió en el puerto {$port} tras {$timeoutSegundos}s.");
    }

    /** Conexión mysqli directa a la BD de prueba, para preparar fixtures o verificar efectos secundarios. */
    protected static function conexionBd(): mysqli
    {
        $conexion = new mysqli(
            self::$serverEnv['DB_HOST'],
            self::$serverEnv['DB_USER'],
            self::$serverEnv['DB_PASS'],
            self::$serverEnv['DB_NAME'],
            (int) self::$serverEnv['DB_PORT']
        );
        $conexion->set_charset('utf8mb4');
        return $conexion;
    }

    /**
     * @param array{json?: array, multipart?: array} $opciones
     * @return array{status: int, body: string, json: mixed}
     */
    protected function peticion(string $metodo, string $ruta, array $opciones = []): array
    {
        $ch = curl_init(self::$baseUrl . $ruta);

        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => self::$cookieJar,
            CURLOPT_COOKIEFILE => self::$cookieJar,
            CURLOPT_TIMEOUT => 10,
        ]);

        if (isset($opciones['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opciones['json'], JSON_UNESCAPED_UNICODE));
            $headers[] = 'Content-Type: application/json';
        }

        if (isset($opciones['multipart'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opciones['multipart']);
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Petición HTTP falló: {$error}");
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }

    /** Loguea con un usuario demo del seeder y deja la cookie de sesión lista para las siguientes peticiones. */
    protected function loguearComo(string $correo, string $contrasena = 'CacesDemo2026!'): array
    {
        return $this->peticion('POST', '/api/auth/Login.php', [
            'json' => ['correo' => $correo, 'contrasena' => $contrasena],
        ]);
    }
}
