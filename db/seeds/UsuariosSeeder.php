<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Los 3 roles que usa el sistema, con una contraseña de demo generada al
 * correr el seed (nunca hardcodeada como hash fijo en el repo). Password de
 * demo para los 3: "CacesDemo2026!" — solo para entornos locales/de prueba,
 * nunca usar en producción.
 */
final class UsuariosSeeder extends AbstractSeed
{
    public function run(): void
    {
        $hash = password_hash('CacesDemo2026!', PASSWORD_BCRYPT);

        $this->table('usuarios')->insert([
            [
                'id_usuario' => 1,
                'nombres' => 'Administrador',
                'apellidos' => 'Demo',
                'correo' => 'administrador@demo.local',
                'contrasena' => $hash,
                'rol' => 'administrador',
                'activo' => 1,
            ],
            [
                'id_usuario' => 2,
                'nombres' => 'Evaluador',
                'apellidos' => 'Demo',
                'correo' => 'evaluador@demo.local',
                'contrasena' => $hash,
                'rol' => 'evaluador',
                'activo' => 1,
            ],
            [
                'id_usuario' => 3,
                'nombres' => 'Coordinador',
                'apellidos' => 'Demo',
                'correo' => 'coordinador@demo.local',
                'contrasena' => $hash,
                'rol' => 'coordinador',
                'activo' => 1,
            ],
        ])->saveData();
    }
}
