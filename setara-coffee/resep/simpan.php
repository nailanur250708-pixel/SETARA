<?php
/**
 * resep/simpan.php — Simpan harga menu & komposisi resep dalam satu transaksi.
 * Modul: Resep & HPP · Café Setara.
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
    redirect('resep/index.php');
}

$menuId = isset($_POST['menu_id']) ? (int)$_POST['menu_id'] : 0;
$harga  = isset($_POST['harga']) ? (float)$_POST['harga'] : 0.0;

$bahanArr  = $_POST['bahan_id'] ?? [];
$takaranArr = $_POST['takaran'] ?? [];

// Pastikan menu ada.
$stMenu = db()->prepare('SELECT id, nama FROM menu WHERE id=?');
$stMenu->execute([$menuId]);
$menu = $stMenu->fetch();
if (!$menu) {
    flash('error', 'Menu tidak ditemukan.');
    redirect('resep/index.php');
}

$redirect = 'resep/index.php?menu=' . $menuId;
$errors = [];

if (!is_array($bahanArr) || count($bahanArr) < 1) {
    $errors[] = 'Minimal satu bahan harus ditambahkan.';
}
if (!is_array($takaranArr)) {
    $takaranArr = [];
}
if (!($harga > 0)) {
    $errors[] = 'Harga jual harus lebih dari 0.';
}

// Validasi tiap baris: bahan valid, takaran > 0, bahan unik.
$bahanBersih = [];
$terlihat = [];
foreach ($bahanArr as $i => $bahanId) {
    $bahanId = (int)$bahanId;
    $takaran = isset($takaranArr[$i]) ? (float)$takaranArr[$i] : 0.0;

    if ($bahanId <= 0) {
        $errors[] = 'Bahan pada baris ' . ($i + 1) . ' tidak valid.';
        continue;
    }
    if (!($takaran > 0)) {
        $errors[] = 'Takaran pada baris ' . ($i + 1) . ' harus lebih dari 0.';
    }
    if (isset($terlihat[$bahanId])) {
        $errors[] = 'Bahan yang sama tidak boleh ditambahkan dua kali.';
        continue;
    }
    $terlihat[$bahanId] = true;

    // Pastikan bahan benar-benar ada di database.
    $stBahan = db()->prepare('SELECT id FROM bahan WHERE id=?');
    $stBahan->execute([$bahanId]);
    if (!$stBahan->fetchColumn()) {
        $errors[] = 'Bahan pada baris ' . ($i + 1) . ' tidak ditemukan.';
        continue;
    }

    $bahanBersih[] = ['bahan_id' => $bahanId, 'takaran' => $takaran];
}

if (count($bahanBersih) < 1) {
    $errors[] = 'Minimal satu bahan valid diperlukan.';
}

if ($errors) {
    flash('error', implode(' ', array_unique($errors)));
    redirect($redirect);
}

/* --------------------------------------------------------- Transaksi DB */
$pdo = db();
try {
    $pdo->beginTransaction();

    $stUpdate = $pdo->prepare('UPDATE menu SET harga=? WHERE id=?');
    $stUpdate->execute([$harga, $menuId]);

    $stHapus = $pdo->prepare('DELETE FROM resep WHERE menu_id=?');
    $stHapus->execute([$menuId]);

    $stInsert = $pdo->prepare('INSERT INTO resep (menu_id, bahan_id, takaran) VALUES (?, ?, ?)');
    foreach ($bahanBersih as $b) {
        $stInsert->execute([$menuId, $b['bahan_id'], $b['takaran']]);
    }

    $pdo->commit();
    flash('success', 'Resep dan harga menu "' . $menu['nama'] . '" berhasil disimpan.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[RESEP] Gagal menyimpan resep menu ' . $menuId . ': ' . $e->getMessage());
    flash('error', 'Gagal menyimpan resep. Perubahan dibatalkan. Silakan coba lagi.');
}

redirect($redirect);
