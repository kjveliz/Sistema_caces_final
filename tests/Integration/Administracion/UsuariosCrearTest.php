<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /administracion/usuarios/crear -- Parte 15
 * del plan de migración slim-legacy (ver plan_migracion_slim_legacy_v3.txt
 * §3 Grupo E, segunda Parte del subgrupo de usuarios), reemplaza a
 * api/administracion/usuarios/crear.php.
 *
 * Mismo chequeo de rol a mano que usuariosListar() (Parte 14): exige
 * $_SESSION['rol'] === 'administrador' (403), además de sesión activa
 * (401, resuelto por SessionAuthMiddleware en la ruta).
 */
final class UsuariosCrearTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Prueba',
                'apellidos' => 'Nueva',
                'correo' => 'prueba.nueva@demo.local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'evaluador',
            ],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolNoAdministradorDevuelve403(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Prueba',
                'apellidos' => 'Nueva',
                'correo' => 'prueba.nueva@demo.local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'evaluador',
            ],
        ]);

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Solo un administrador puede crear usuarios.', $respuesta['json']['mensaje']);
    }

    public function testConDatosValidosCreaElUsuario(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Prueba',
                'apellidos' => 'Nueva',
                'correo' => 'PRUEBA.Nueva@Demo.Local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'EVALUADOR',
                'activo' => 0,
            ],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Usuario creado correctamente.', $respuesta['json']['mensaje']);

        $datos = $respuesta['json']['datos'];
        $this->assertIsInt($datos['id_usuario']);
        $this->assertSame('Prueba', $datos['nombres']);
        $this->assertSame('Nueva', $datos['apellidos']);
        // El original hace strtolower($correo) -- mismo criterio.
        $this->assertSame('prueba.nueva@demo.local', $datos['correo']);
        // El original hace strtolower($rol) también.
        $this->assertSame('evaluador', $datos['rol']);
        $this->assertSame(0, $datos['activo']);
        $this->assertArrayNotHasKey('contrasena', $datos);

        // Verificación directa contra la BD, incluyendo que la contraseña
        // quedó hasheada (nunca en texto plano).
        $conexion = self::conexionBd();
        $fila = $conexion->query(
            'SELECT correo, rol, activo, contrasena FROM usuarios WHERE id_usuario = ' . (int) $datos['id_usuario'],
        )->fetch_assoc();
        $this->assertSame('prueba.nueva@demo.local', $fila['correo']);
        $this->assertSame('evaluador', $fila['rol']);
        $this->assertSame(0, (int) $fila['activo']);
        $this->assertNotSame('ClaveSegura1', $fila['contrasena']);
        $this->assertTrue(password_verify('ClaveSegura1', $fila['contrasena']));
    }

    public function testActivoPorDefectoEs1SiNoSeEnvia(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'SinActivo',
                'apellidos' => 'PorDefecto',
                'correo' => 'sin.activo@demo.local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'coordinador',
            ],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(1, $respuesta['json']['datos']['activo']);
    }

    public function testConCamposFaltantesDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => ['nombres' => 'Incompleto'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Complete correctamente todos los campos.', $respuesta['json']['mensaje']);
    }

    public function testConRolInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Rol',
                'apellidos' => 'Invalido',
                'correo' => 'rol.invalido@demo.local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'superadmin',
            ],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Complete correctamente todos los campos.', $respuesta['json']['mensaje']);
    }

    public function testConCorreoInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Correo',
                'apellidos' => 'Invalido',
                'correo' => 'no-es-un-correo',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'evaluador',
            ],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('El correo electrónico no es válido.', $respuesta['json']['mensaje']);
    }

    public function testConContrasenaCortaDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Clave',
                'apellidos' => 'Corta',
                'correo' => 'clave.corta@demo.local',
                'contrasena' => '1234567',
                'rol' => 'evaluador',
            ],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('La contraseña debe tener al menos 8 caracteres.', $respuesta['json']['mensaje']);
    }

    public function testConCorreoDuplicadoDevuelve409(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        // administrador@demo.local ya existe (UsuariosSeeder).
        $respuesta = $this->peticion('POST', '/administracion/usuarios/crear', [
            'json' => [
                'nombres' => 'Duplicado',
                'apellidos' => 'Correo',
                'correo' => 'administrador@demo.local',
                'contrasena' => 'ClaveSegura1',
                'rol' => 'evaluador',
            ],
        ]);

        $this->assertSame(409, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Ya existe un usuario con ese correo.', $respuesta['json']['mensaje']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/usuarios/crear');

        $this->assertSame(405, $respuesta['status']);
    }
}
