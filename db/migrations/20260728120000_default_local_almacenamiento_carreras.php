<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Invierte el default del interruptor de almacenamiento (ver
 * `20260727010000_modo_almacenamiento_carreras.php` y
 * plan_interruptor_almacenamiento.txt): para la entrega, el proyecto se
 * distribuye sin evidencia subida a Google Drive, así que el
 * comportamiento por defecto pasa a ser `local` para toda carrera. Drive
 * sigue disponible como opción — el usuario (admin/coordinador) lo elige
 * explícitamente con el mismo interruptor de siempre en la UI de Carreras,
 * en vez de tener que "apagar" Drive.
 *
 * Se usa `up()`/`down()` en vez de `change()` porque además del cambio de
 * esquema (nuevo default de la columna) hay una actualización de datos
 * (las carreras ya existentes en `carreras`), y Phinx no puede revertir
 * automáticamente un `execute()` de datos dentro de `change()`.
 */
final class DefaultLocalAlmacenamientoCarreras extends AbstractMigration
{
    public function up(): void
    {
        $this->table('carreras')
            ->changeColumn('modo_almacenamiento', 'enum', [
                'values' => ['drive', 'local'],
                'default' => 'local',
                'null' => false,
            ])
            ->update();

        // Todas las carreras existentes pasan a local — coherente con la
        // entrega sin evidencia subida a Drive (no hay nada que "perder"
        // al cambiar el modo, la migración real de archivos solo se
        // dispara desde el interruptor de la UI, no desde acá).
        $this->execute("UPDATE carreras SET modo_almacenamiento = 'local'");
    }

    public function down(): void
    {
        $this->table('carreras')
            ->changeColumn('modo_almacenamiento', 'enum', [
                'values' => ['drive', 'local'],
                'default' => 'drive',
                'null' => false,
            ])
            ->update();

        $this->execute("UPDATE carreras SET modo_almacenamiento = 'drive'");
    }
}
