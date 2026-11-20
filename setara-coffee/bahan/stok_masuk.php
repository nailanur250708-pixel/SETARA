<?php
/**
 * bahan/stok_masuk.php — Catat stok masuk bahan, optionally catat kas pembelian.
 * Semua perubahan (stok + pergerakan + kas + harga) dalam SATU transaction.
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
$jumlah    = (float)($_POST['jumlah'] ?? 0);
$totalHarga = isset($_POST['total_harga']) && trim((string)$_POST['total_harga']) !== ''
    ? (float)$_POST['total_harga']
    : null;
$keterangan = trim((string)($_POST['keterangan'] ?? ''));
$tanggal   = trim((string)($_POST['tanggal'] ?? ''));

// Validasi tanggal: default hari ini bila kosong/invalid.
$dt = null;
if ($tanggal !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
    if ($dt === false || $dt->format('Y-m-d') !== $tanggal) { $dt = null; }
}
if ($dt === null) { $dt = new DateTime('today'); }
$tanggalDb = $dt->format('Y-m-d H:i:s');

if ($jumlah <= 0) {
    flash('error', 'Jumlah masuk harus lebih dari 0.');
    redirect('bahan/index.php?aksi=stok_masuk');
}

try {
    $pdo->beginTransaction();

    // Pastikan bahan ada & kunci baris.
    $stBahan = $pdo->prepare('SELECT id, nama, satuan, stok FROM bahan WHERE id=? FOR UPDATE');
    $stBahan->execute([$bahanId]);
    $bahan = $stBahan->fetch();
    if (!$bahan) {
        throw new RuntimeException('Bahan tidak ditemukan.');
    }

    // 1. Tambah stok.
    $upd = $pdo->prepare('UPDATE bahan SET stok = stok + ? WHERE id=?');
    $upd->execute([$jumlah, $bahanId]);

    // 2. Catat pergerakan stok bertanda positif.
    $ket = $keterangan !== '' ? $keterangan : 'Stok masuk';
    $insP = $pdo->prepare(
        "INSERT INTO pergerakan_stok (bahan_id, tipe, qty, keterangan, transaksi_id, user_id, tanggal)
         VALUES (?,'masuk',?,?,NULL,?,?)"
    );
    $insP->execute([$bahanId, $jumlah, mb_substr($ket, 0, 255), $userId, $tanggalDb]);

    // 3. Jika total harga diisi: update harga satuan & catat kas keluar.
    if ($totalHarga !== null && $totalHarga > 0) {
        $hargaBaru = $totalHarga / $jumlah;
        $updHarga = $pdo->prepare('UPDATE bahan SET harga_beli_per_satuan=? WHERE id=?');
        $updHarga->execute([$hargaBaru, $bahanId]);

        $insKas = $pdo->prepare(
            "INSERT INTO kas (user_id, tipe, kategori, jumlah, keterangan, tanggal)
             VALUES (?,'keluar','Pembelian bahan',?,?,?)"
        );
        $ketKas = $keterangan !== '' ? $keterangan : ('Pembelian ' . $bahan['nama']);
        $insKas->execute([$userId, round($totalHarga), $ketKas, $tanggalDb]);
    }

    $pdo->commit();

    $pesan = 'Stok ' . $bahan['nama'] . ' bertambah ' . $jumlah . ' ' . $bahan['satuan'] . '.';
    if ($totalHarga !== null && $totalHarga > 0) {
        $pesan .= ' Harga satuan diperbarui dan pembelian dicatat di kas.';
    }
    flash('success', $pesan);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[bahan/stok_masuk] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan stok masuk: ' . $e->getMessage());
}

redirect('bahan/index.php?aksi=stok_masuk&id=' . $bahanId);
