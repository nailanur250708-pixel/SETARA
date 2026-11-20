<?php
/**
 * menu/urutan.php -- Urutkan menu (naik/turun) via aksi=naik|turun.
 * POST + CSRF + role owner.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo 'Permintaan tidak valid (CSRF).';
    exit;
}
require_role(['owner']);

$id   = (int)($_POST['id'] ?? 0);
$aksi = strtolower(trim((string)($_POST['aksi'] ?? '')));

if ($id <= 0 || !in_array($aksi, ['naik', 'turun'], true)) {
    flash('error', 'Menu tidak ditemukan atau aksi tidak valid.');
    redirect('menu/index.php');
}

try {
    $rows = db()->query('SELECT id, urutan FROM menu ORDER BY urutan DESC, id DESC')->fetchAll();
    $pos = -1;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    if ($pos < 0) {
        flash('error', 'Menu tidak ditemukan.');
        redirect('menu/index.php');
    }
    // Urutan DESC: pos 0 = paling atas. Naik = geser ke pos-1, turun = ke pos+1.
    $next = ($aksi === 'naik') ? $pos - 1 : $pos + 1;
    if ($next < 0 || $next >= count($rows)) {
        flash('error', 'Tidak ada menu di arah tersebut.');
        redirect('menu/index.php');
    }
    db()->prepare('UPDATE menu SET urutan=? WHERE id=?')->execute([(int) $rows[$next]['urutan'], (int) $rows[$pos]['id']]);
    db()->prepare('UPDATE menu SET urutan=? WHERE id=?')->execute([(int) $rows[$pos]['urutan'], (int) $rows[$next]['id']]);
    flash('success', 'Urutan menu berhasil diubah.');
} catch (PDOException $e) {
    error_log('[menu/urutan] ' . $e->getMessage());
    flash('error', 'Gagal mengubah urutan menu.');
}

redirect('menu/index.php');
