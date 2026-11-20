<?php
/**
 * bahan/riwayat.php — Shortcut ke halaman Bahan & stok dengan filter riwayat.
 * Filter tanggal/status/kata kunci diproses oleh bahan/index.php dari GET.
 * Endpoint POST tetap dilindungi CSRF + role sesuai konvensi modul.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo 'Permintaan tidak valid (CSRF).';
        exit;
    }
    require_role(['owner']);
}

require_role(['owner']);

// index.php membaca filter dari GET ($_GET['dari'], $_GET['sampai'], dll.)
// dan merender seluruh halaman, termasuk kartu "Riwayat pergerakan bahan".
require __DIR__ . '/index.php';
