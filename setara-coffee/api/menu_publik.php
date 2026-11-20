<?php
/**
 * api/menu_publik.php — Endpoint publik: daftar menu aktif (read-only).
 * Digunakan landing page customer (halaman sudah dihapus).
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$st = db()->prepare('SELECT id, nama, kategori, harga, foto_path, gambar, foto_fit, foto_pos_x, foto_pos_y, foto_zoom, deskripsi, tampil_landing, tersedia, diarsipkan FROM menu WHERE tampil_landing=1 AND diarsipkan=0 ORDER BY urutan DESC, id DESC');
$st->execute();
$rows = [];
foreach ($st->fetchAll() as $m) {
    // Foto menu baru (foto_path) lebih utama; jatuhkan ke gambar lama (legacy).
    $g = (string) ($m['foto_path'] ?? '');
    if ($g === '') {
        $g = (string) ($m['gambar'] ?? '');
    }
    if ($g !== '') {
        $gf = dirname(__DIR__) . '/' . $g;
        if (is_file($gf)) {
            $g = $g . '?v=' . filemtime($gf);
        }
    }
    $rows[] = [
        'id'         => (int) $m['id'],
        'nama'       => (string) $m['nama'],
        'kategori'   => (string) $m['kategori'],
        'harga'      => (float) $m['harga'],
        'gambar'     => $g !== '' ? $g : null,
        'foto_fit'   => $m['foto_fit'],
        'foto_pos_x' => $m['foto_pos_x'],
        'foto_pos_y' => $m['foto_pos_y'],
        'foto_zoom'  => $m['foto_zoom'],
        'deskripsi'  => (string) ($m['deskripsi'] ?? ''),
        'tersedia'   => (int) ($m['tersedia'] ?? 1),
        'diarsipkan' => (int) ($m['diarsipkan'] ?? 0),
    ];
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
