<?php
/**
 * menu/hapus.php -- Hapus permanen menu hanya jika belum dipakai transaksi.
 * Hapus juga file foto menu (uploads/menu/ + legacy assets/img/) dari disk.
 * POST + CSRF + role owner.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_menu_foto.php';

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
    $st = db()->prepare('SELECT COUNT(*) FROM detail_transaksi WHERE menu_id=?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
        flash('error', 'Menu tidak dapat dihapus karena sudah pernah digunakan dalam transaksi.');
        redirect('menu/index.php');
    }

    // Hapus file foto (uploads/menu/ + legacy assets/img/) sebelum record dihapus.
    menu_delete_photo_files(db(), $id);

    $st = db()->prepare('DELETE FROM menu WHERE id=?');
    $st->execute([$id]);

    flash('success', 'Menu berhasil dihapus permanen.');
} catch (PDOException $e) {
    error_log('[menu/hapus] ' . $e->getMessage());
    flash('error', 'Gagal menghapus menu.');
}

redirect('menu/index.php');
