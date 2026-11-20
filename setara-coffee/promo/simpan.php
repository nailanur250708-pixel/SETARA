<?php
/**
 * promo/simpan.php -- Tambah atau perbarui promo.
 * POST + CSRF + role owner.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo 'Permintaan tidak valid (CSRF).';
    exit;
}
require_role(['owner']);

$id       = (int)($_POST['id'] ?? 0);
$menuId   = (int)($_POST['menu_id'] ?? 0);
$kode     = strtoupper(trim((string)($_POST['kode'] ?? '')));
$potongan = (float)($_POST['potongan_persen'] ?? 0);
$mulai    = trim((string)($_POST['berlaku_mulai'] ?? ''));
$sampai   = trim((string)($_POST['berlaku_sampai'] ?? ''));
$aktif    = isset($_POST['aktif']) && $_POST['aktif'] !== '' ? 1 : 0;

$gagal = [];
if ($menuId <= 0) {
    $gagal[] = 'Pilih menu terlebih dahulu.';
}
if ($kode === '' || !preg_match('/^[A-Z0-9_-]+$/', $kode)) {
    $gagal[] = 'Kode promo wajib diisi (huruf kapital, angka, tanda hubung, atau garis bawah).';
}
if ($potongan < 0.01 || $potongan > 100) {
    $gagal[] = 'Potongan harus antara 0,01% sampai 100%.';
}
if ($mulai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) {
    $gagal[] = 'Format tanggal mulai tidak valid.';
}
if ($sampai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
    $gagal[] = 'Format tanggal selesai tidak valid.';
}
if ($mulai !== '' && $sampai !== '' && $sampai < $mulai) {
    $gagal[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
}

try {
    if ($menuId > 0) {
        $st = db()->prepare('SELECT id FROM menu WHERE id=?');
        $st->execute([$menuId]);
        if (!$st->fetchColumn()) {
            $gagal[] = 'Menu yang dipilih tidak ditemukan.';
        }
    }

    if (!$gagal) {
        // Kode unik (selain id sendiri).
        $st = db()->prepare('SELECT id FROM promo WHERE kode=?' . ($id > 0 ? ' AND id<>?' : ''));
        $params = [$kode];
        if ($id > 0) $params[] = $id;
        $st->execute($params);
        if ($st->fetchColumn()) {
            $gagal[] = 'Kode promo "' . $kode . '" sudah digunakan.';
        }
    }

    if (!$gagal && $aktif === 1) {
        // Cek bentrok: satu menu hanya satu promo aktif pada rentang tanggal yang sama.
        $sql = 'SELECT id FROM promo
                WHERE menu_id=? AND aktif=1';
        $params = [$menuId];
        if ($id > 0) {
            $sql .= ' AND id<>?';
            $params[] = $id;
        }
        // Overlap rentang: NULL berarti tanpa batas.
        $sql .= ' AND (
            (berlaku_mulai IS NULL AND berlaku_sampai IS NULL)
            OR (berlaku_mulai IS NULL AND (? IS NULL OR berlaku_sampai >= ?))
            OR (berlaku_sampai IS NULL AND (? IS NULL OR berlaku_mulai <= ?))
            OR (berlaku_mulai IS NOT NULL AND berlaku_sampai IS NOT NULL
                AND ? <= berlaku_sampai AND ? >= berlaku_mulai)
        )';
        $params[] = $mulai !== '' ? $mulai : null;
        $params[] = $mulai !== '' ? $mulai : null;
        $params[] = $sampai !== '' ? $sampai : null;
        $params[] = $sampai !== '' ? $sampai : null;
        $params[] = $mulai !== '' ? $mulai : '0000-00-00';
        $params[] = $sampai !== '' ? $sampai : '9999-12-31';
        $st = db()->prepare($sql);
        $st->execute($params);
        if ($st->fetchColumn()) {
            $gagal[] = 'Menu ini sudah memiliki promo aktif pada rentang tanggal tersebut.';
        }
    }
} catch (PDOException $e) {
    error_log('[promo/simpan-validasi] ' . $e->getMessage());
    $gagal[] = 'Gagal memvalidasi promo. Silakan coba lagi.';
}

if ($gagal) {
    flash('error', implode(' ', $gagal));
    redirect('menu/index.php?tab=promo' . ($id > 0 ? '&aksi=edit&id=' . $id : '&aksi=tambah'));
}

try {
    $mulaiDb  = $mulai !== '' ? $mulai : null;
    $sampaiDb = $sampai !== '' ? $sampai : null;
    if ($id > 0) {
        $st = db()->prepare('UPDATE promo SET menu_id=?, kode=?, potongan_persen=?, aktif=?, berlaku_mulai=?, berlaku_sampai=? WHERE id=?');
        $st->execute([$menuId, $kode, $potongan, $aktif, $mulaiDb, $sampaiDb, $id]);
        flash('success', 'Promo berhasil diperbarui.');
    } else {
        $st = db()->prepare('INSERT INTO promo (menu_id, kode, potongan_persen, aktif, berlaku_mulai, berlaku_sampai) VALUES (?,?,?,?,?,?)');
        $st->execute([$menuId, $kode, $potongan, $aktif, $mulaiDb, $sampaiDb]);
        flash('success', 'Promo berhasil ditambahkan.');
    }
} catch (PDOException $e) {
    error_log('[promo/simpan] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan promo.');
}

redirect('menu/index.php?tab=promo');
