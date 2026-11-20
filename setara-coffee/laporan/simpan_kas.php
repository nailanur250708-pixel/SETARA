<?php
/**
 * laporan/simpan_kas.php — Catat pengeluaran operasional (kas keluar).
 * Modul: Laporan operasional · Café Setara.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

require_role(['owner']);

if (!csrf_check()) {
    flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    redirect('laporan/index.php?tab=cashflow');
}

/** Validasi tanggal YYYY-MM-DD. */
function kas_tanggal_valid($s): bool
{
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

$mulai = isset($_POST['mulai']) && kas_tanggal_valid($_POST['mulai']) ? (string)$_POST['mulai'] : date('Y-m-01');
$sampai = isset($_POST['sampai']) && kas_tanggal_valid($_POST['sampai']) ? (string)$_POST['sampai'] : date('Y-m-d');
if (strtotime($sampai) < strtotime($mulai)) {
    $sampai = $mulai;
}
$pengelompokan = isset($_POST['pengelompokan']) && in_array($_POST['pengelompokan'], ['harian', 'mingguan', 'bulanan'], true)
    ? (string)$_POST['pengelompokan']
    : 'harian';
$redirect = 'laporan/index.php?' . http_build_query([
    'tab' => 'cashflow',
    'mulai' => $mulai,
    'sampai' => $sampai,
    'pengelompokan' => $pengelompokan,
]);

$tanggal = isset($_POST['tanggal']) ? trim((string)$_POST['tanggal']) : '';
$kategori = isset($_POST['kategori']) ? trim((string)$_POST['kategori']) : '';
$keterangan = isset($_POST['keterangan']) ? trim((string)$_POST['keterangan']) : '';
$jumlah = isset($_POST['jumlah']) ? (float)$_POST['jumlah'] : 0.0;

$errors = [];
if (!kas_tanggal_valid($tanggal)) {
    $errors[] = 'Tanggal tidak valid.';
}
if ($kategori === '') {
    $errors[] = 'Kategori wajib diisi.';
}
if (mb_strlen($kategori) > 50) {
    $errors[] = 'Kategori terlalu panjang (maksimal 50 karakter).';
}
if (!($jumlah > 0)) {
    $errors[] = 'Jumlah harus lebih dari 0.';
}
if (mb_strlen($keterangan) > 255) {
    $errors[] = 'Keterangan terlalu panjang (maksimal 255 karakter).';
}

if ($errors) {
    flash('error', implode(' ', array_unique($errors)));
    redirect($redirect);
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
try {
    $st = db()->prepare(
        "INSERT INTO kas (user_id, tipe, kategori, jumlah, keterangan, tanggal)
         VALUES (?, 'keluar', ?, ?, ?, ?)"
    );
    $st->execute([
        $userId,
        $kategori,
        (int)round($jumlah),
        $keterangan !== '' ? $keterangan : null,
        $tanggal . ' ' . date('H:i:s'),
    ]);
    flash('success', 'Pengeluaran operasional "' . $kategori . '" sebesar ' . rupiah($jumlah) . ' berhasil dicatat.');
} catch (PDOException $e) {
    error_log('[KAS] Gagal menyimpan kas keluar: ' . $e->getMessage());
    flash('error', 'Gagal menyimpan pengeluaran. Silakan coba lagi.');
}

redirect($redirect);
