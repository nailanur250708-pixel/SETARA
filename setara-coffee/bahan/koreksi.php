<?php
/**
 * bahan/koreksi.php — Koreksi stok fisik. Selisih = stok_fisik - stok sekarang.
 * Perubahan stok & pergerakan_stok 'koreksi' dicatat dalam SATU transaction.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo 'Permintaan tidak valid (CSRF).';
    exit;
}
require_role(['owner']);

$pdo = db();
$userId = (int)($_SESSION['user']['id'] ?? 0) ?: null;

$bahanId   = (int)($_POST['bahan_id'] ?? 0);
$stokFisik = (float)($_POST['stok_fisik'] ?? 0);
$keterangan = trim((string)($_POST['keterangan'] ?? ''));

if ($stokFisik < 0) {
    flash('error', 'Stok fisik tidak boleh negatif.');
    redirect('bahan/index.php?aksi=koreksi&id=' . $bahanId);
}
if ($keterangan === '') {
    flash('error', 'Keterangan koreksi wajib diisi.');
    redirect('bahan/index.php?aksi=koreksi&id=' . $bahanId);
}

try {
    $pdo->beginTransaction();

    $stBahan = $pdo->prepare('SELECT id, nama, satuan, stok FROM bahan WHERE id=? FOR UPDATE');
    $stBahan->execute([$bahanId]);
    $bahan = $stBahan->fetch();
    if (!$bahan) {
        throw new RuntimeException('Bahan tidak ditemukan.');
    }

    $stokSekarang = (float)$bahan['stok'];
    $selisih = $stokFisik - $stokSekarang;

    // 1. Set stok ke nilai fisik.
    $upd = $pdo->prepare('UPDATE bahan SET stok=? WHERE id=?');
    $upd->execute([$stokFisik, $bahanId]);

    // 2. Catat pergerakan koreksi (qty bisa negatif).
    $insP = $pdo->prepare(
        "INSERT INTO pergerakan_stok (bahan_id, tipe, qty, keterangan, transaksi_id, user_id, tanggal)
         VALUES (?,'koreksi',?,?,NULL,?,NOW())"
    );
    $insP->execute([$bahanId, $selisih, mb_substr($keterangan, 0, 255), $userId]);

    $pdo->commit();

    $tanda = $selisih < 0 ? '−' : '+';
    flash('success', 'Koreksi stok ' . $bahan['nama'] . ': ' . $tanda . abs($selisih) . ' ' . $bahan['satuan'] . '. Perubahan tercatat di riwayat.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[bahan/koreksi] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan koreksi: ' . $e->getMessage());
}

redirect('bahan/index.php?aksi=koreksi&id=' . $bahanId);
