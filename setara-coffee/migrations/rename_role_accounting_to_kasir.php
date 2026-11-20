<?php
/**
 * Migration: ganti role 'accounting' menjadi 'kasir' (users.role ENUM).
 * Idempoten: aman dijalankan berulang kali. Tidak menghapus data lain.
 */
require_once __DIR__ . '/../config/database.php';
$pdo = db();

function role_enum_values(PDO $pdo): array {
    $st = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'");
    $st->execute();
    $type = (string) $st->fetchColumn();
    preg_match_all("/'([^']*)'/", $type, $m);
    return $m[1];
}

$values = role_enum_values($pdo);

// 1. Pastikan 'kasir' ada di enum (tambahkan bila belum).
if (!in_array('kasir', $values, true)) {
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('owner', 'kasir') NULL");
    $values = role_enum_values($pdo);
}

// 2. Update data lama: accounting -> kasir (case-insensitive).
$pdo->exec("UPDATE `users` SET role='kasir' WHERE LOWER(role)='accounting'");

// 3. Hapus nilai lama 'accounting' dari enum — aman bila tidak ada data yang pakai.
if (in_array('accounting', $values, true)) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE LOWER(role)='accounting'");
    $st->execute();
    if ((int) $st->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('owner', 'kasir') NULL");
    } else {
        echo "Peringatan: ada data dengan role 'accounting' — nilai tidak dihapus dari enum.\n";
    }
}

echo "Migration selesai: role 'accounting' -> 'kasir'.\n";
