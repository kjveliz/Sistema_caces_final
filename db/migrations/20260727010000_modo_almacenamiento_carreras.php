<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Interruptor de almacenamiento por carrera (Google Drive / Local).
 *
 * Agrega a `carreras` el flag que decide dónde se guarda la evidencia
 * subida por los indicadores (I2/I3 en esta primera entrega — ver
 * plan_interruptor_almacenamiento.txt §5, I4/I5 quedan fuera de alcance
 * por ahora porque usan la ruta legacy api/google_drive/subir_archivo.php,
 * que no reutiliza GoogleDriveService).
 *
 * - modo_almacenamiento: 'drive' (comportamiento actual, default) o
 *   'local'. Solo lo puede mover un usuario con rol administrador o
 *   coordinador (chequeo de rol en el controller nuevo, no a nivel de
 *   BD — mismo patrón que CarrerasController::actualizar()).
 * - ruta_almacenamiento_local: NULL/vacío => se usa la ruta local por
 *   defecto del sistema (storage/evidencias/); si el admin la llena, esa
 *   ruta se usa como raíz para esa carrera (pendrive/disco externo).
 */
final class ModoAlmacenamientoCarreras extends AbstractMigration
{
    public function change(): void
    {
        $this->table('carreras')
            ->addColumn('modo_almacenamiento', 'enum', [
                'values' => ['drive', 'local'],
                'default' => 'drive',
                'null' => false,
                'after' => 'activo',
            ])
            ->addColumn('ruta_almacenamiento_local', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'modo_almacenamiento',
            ])
            ->update();
    }
}