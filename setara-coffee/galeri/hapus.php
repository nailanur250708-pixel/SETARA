<?php
/**
 * galeri/hapus.php -- Hapus foto galeri (file fisik + baris DB).
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
    flash('error', 'Foto galeri tidak ditemukan.');
    redirect('menu/index.php?tab=galeri');
}

try {
    $st = db()->prepare('SELECT gambar FROM galeri WHERE id=?');
    $st->execute([$id]);
    $gambar = $st->fetchColumn();

    $st = db()->prepare('DELETE FROM galeri WHERE id=?');
    $st->execute([$id]);

    if ($gambar && str_starts_with($gambar, 'assets/')) {
        $file = dirname(__DIR__, 2) . '/' . $gambar;
        if (is_file($file)) @unlink($file);
    }

    flash('success', 'Foto galeri berhasil dihapus.');
} catch (PDOException $e) {
    error_log('[galeri/hapus] ' . $e->getMessage());
    flash('error', 'Gagal menghapus foto galeri.');
}

redirect('menu/index.php?tab=galeri');
