<?php
/**
 * promo/toggle.php -- Aktifkan / nonaktifkan promo.
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
    flash('error', 'Promo tidak ditemukan.');
    redirect('menu/index.php?tab=promo');
}

try {
    $st = db()->prepare('UPDATE promo SET aktif = 1 - aktif WHERE id=?');
    $st->execute([$id]);
    flash('success', 'Status promo berhasil diubah.');
} catch (PDOException $e) {
    error_log('[promo/toggle] ' . $e->getMessage());
    flash('error', 'Gagal mengubah status promo.');
}

redirect('menu/index.php?tab=promo');
