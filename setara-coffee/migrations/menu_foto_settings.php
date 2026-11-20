<?php
/**
 * Migration: kolom foto & tampilan menu + deskripsi + tampil_landing + urutan.
 * Idempotent: hanya tambah kolom yang belum ada; tidak menghapus data lama.
 */
require_once __DIR__ . '/../config/database.php';
$pdo = db();

function menu_col_exists(PDO $pdo, string $col): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='menu' AND COLUMN_NAME=?");
    $st->execute([$col]);
    return (int) $st->fetchColumn() > 0;
}

$cols = [
    'foto_fit'    => "ENUM('cover','contain') NULL",
    'foto_pos_x'  => "DECIMAL(5,2) NULL",
    'foto_pos_y'  => "DECIMAL(5,2) NULL",
    'foto_zoom'   => "DECIMAL(5,2) DEFAULT 1.00",
    'deskripsi'   => "VARCHAR(255) NULL",
    'tampil_landing' => "TINYINT(1) NOT NULL DEFAULT 1",
    'urutan'      => "INT NOT NULL DEFAULT 0",
];
foreach ($cols as $c => $def) {
    if (!menu_col_exists($pdo, $c)) {
        $pdo->exec("ALTER TABLE `menu` ADD COLUMN `$c` $def");
        echo "Ditambah: menu.$c\n";
    }
}
echo "Migration menu_foto_settings selesai.\n";
