<?php
/**
 * bahan/simpan.php — Simpan bahan baru atau edit bahan yang sudah ada.
 * Tambah: insert bahan + pergerakan_stok 'masuk' untuk stok awal.
 * Edit: update nama/satuan/harga/minimum (stok TIDAK diubah langsung).
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

$nama   = trim((string)($_POST['nama'] ?? ''));
$satuan = trim((string)($_POST['satuan'] ?? ''));
$harga  = (float)($_POST['harga_beli_per_satuan'] ?? 0);
$minimum = (float)($_POST['stok_minimum'] ?? 0);
$harga  = max(0, $harga);
$minimum = max(0, $minimum);

$form   = (string)($_POST['form'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if ($nama === '' || $satuan === '') {
    flash('error', 'Nama dan satuan bahan wajib diisi.');
    redirect('bahan/index.php');
}

try {
    $pdo->beginTransaction();

    if ($form === 'tambah' || $id <= 0) {
        // Cek nama unik.
        $stCek = $pdo->prepare('SELECT COUNT(*) FROM bahan WHERE nama=?');
        $stCek->execute([$nama]);
        if ((int)$stCek->fetchColumn() > 0) {
            throw new RuntimeException('Bahan dengan nama "' . $nama . '" sudah ada.');
        }

        $stokAwal = (float)($_POST['stok_awal'] ?? 0);
        $stokAwal = max(0, $stokAwal);

        $ins = $pdo->prepare(
            'INSERT INTO bahan (nama, satuan, harga_beli_per_satuan, stok, stok_minimum)
             VALUES (?,?,?,?,?)'
        );
        $ins->execute([$nama, $satuan, $harga, $stokAwal, $minimum]);
        $bahanId = (int)$pdo->lastInsertId();

        $insP = $pdo->prepare(
            "INSERT INTO pergerakan_stok (bahan_id, tipe, qty, keterangan, user_id, tanggal)
             VALUES (?,'masuk',?,'Stok awal',?,NOW())"
        );
        $insP->execute([$bahanId, $stokAwal, $userId]);

        flash('success', 'Bahan "' . $nama . '" ditambahkan dengan stok awal ' . $stokAwal . ' ' . $satuan . '.');
    } else {
        // Pastikan bahan ada & ambil nama lama untuk pesan.
        $stCek = $pdo->prepare('SELECT nama FROM bahan WHERE id=?');
        $stCek->execute([$id]);
        $namaLama = $stCek->fetchColumn();
        if ($namaLama === false) {
            throw new RuntimeException('Bahan tidak ditemukan.');
        }

        // Cek nama unik pada bahan lain.
        $stDup = $pdo->prepare('SELECT COUNT(*) FROM bahan WHERE nama=? AND id<>?');
        $stDup->execute([$nama, $id]);
        if ((int)$stDup->fetchColumn() > 0) {
            throw new RuntimeException('Bahan dengan nama "' . $nama . '" sudah ada.');
        }

        $upd = $pdo->prepare(
            'UPDATE bahan SET nama=?, satuan=?, harga_beli_per_satuan=?, stok_minimum=? WHERE id=?'
        );
        $upd->execute([$nama, $satuan, $harga, $minimum, $id]);

        flash('success', 'Bahan "' . $nama . '" diperbarui.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[bahan/simpan] ' . $e->getMessage());
    flash('error', $e->getMessage());
}

redirect('bahan/index.php');
