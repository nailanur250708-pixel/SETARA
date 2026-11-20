<?php
/**
 * api/promo_publik.php — Endpoint publik: promo aktif (read-only) untuk landing page.
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$st = db()->query('SELECT p.kode, p.potongan_persen, m.nama AS menu_nama, m.harga, m.foto_path, m.gambar FROM promo p JOIN menu m ON m.id=p.menu_id WHERE p.aktif=1 AND m.diarsipkan=0 ORDER BY p.id DESC');
$rows = [];
foreach ($st->fetchAll() as $r) {
    // Foto menu baru (foto_path) lebih utama.
    $g = (string) ($r['foto_path'] ?? '');
    if ($g === '') {
        $g = (string) ($r['gambar'] ?? '');
    }
    if ($g !== '' && !is_file(dirname(__DIR__) . '/' . $g)) {
        $g = '';
    }
    $rows[] = [
        'kode'            => (string) $r['kode'],
        'potongan_persen' => (float) $r['potongan_persen'],
        'menu_nama'       => (string) $r['menu_nama'],
        'harga'           => (float) $r['harga'],
        'gambar'          => $g !== '' ? $g : null,
    ];
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
