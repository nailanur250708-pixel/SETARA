<?php
/**
 * menu/arsip.php -- Arsipkan menu (tetap tersimpan, tidak dihapus).
 * POST + CSRF + role owner.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo 'Permintaan tidak valid (CSRF).';
    exit;
}
require_role(['owner']);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Menu tidak ditemukan.');
    redirect('menu/index.php');
}

try {
    $st = db()->prepare('UPDATE menu SET diarsipkan=1, tersedia=0 WHERE id=?');
    $st->execute([$id]);
    flash('success', 'Menu berhasil diarsipkan.');
} catch (PDOException $e) {
    error_log('[menu/arsip] ' . $e->getMessage());
    flash('error', 'Gagal mengarsipkan menu.');
}

redirect('menu/index.php');
