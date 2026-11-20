<?php
/**
 * galeri/simpan.php -- Tambah foto galeri atau perbarui judul/urutan.
 * POST + CSRF + role owner.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo 'Permintaan tidak valid (CSRF).';
    exit;
}
require_role(['owner']);

$id     = (int)($_POST['id'] ?? 0);
$judul  = trim((string)($_POST['judul'] ?? ''));
$urutan = (int)($_POST['urutan'] ?? 0);

$fotoBaru = upload_gambar($_FILES['foto'] ?? [], 'galeri');

try {
    if ($id > 0) {
        $st = db()->prepare('SELECT id FROM galeri WHERE id=?');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            flash('error', 'Foto galeri tidak ditemukan.');
            redirect('menu/index.php?tab=galeri');
        }
        if ($fotoBaru !== null) {
            // Hapus file lama setelah berhasil mengunggah yang baru.
            $st = db()->prepare('SELECT gambar FROM galeri WHERE id=?');
            $st->execute([$id]);
            $lama = $st->fetchColumn();
            $st = db()->prepare('UPDATE galeri SET judul=?, urutan=?, gambar=? WHERE id=?');
            $st->execute([$judul, $urutan, $fotoBaru, $id]);
            if ($lama && str_starts_with($lama, 'assets/')) {
                $file = dirname(__DIR__, 2) . '/' . $lama;
                if (is_file($file)) @unlink($file);
            }
        } else {
            $st = db()->prepare('UPDATE galeri SET judul=?, urutan=? WHERE id=?');
            $st->execute([$judul, $urutan, $id]);
        }
        flash('success', 'Galeri berhasil diperbarui.');
    } else {
        if ($fotoBaru === null) {
            flash('error', 'Foto wajib diunggah (jpg/png/webp, maksimal 2MB).');
            redirect('menu/index.php?tab=galeri');
        }
        $st = db()->prepare('INSERT INTO galeri (judul, gambar, urutan) VALUES (?,?,?)');
        $st->execute([$judul, $fotoBaru, $urutan]);
        flash('success', 'Foto berhasil ditambahkan ke galeri.');
    }
} catch (PDOException $e) {
    error_log('[galeri/simpan] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan galeri.');
}

redirect('menu/index.php?tab=galeri');
