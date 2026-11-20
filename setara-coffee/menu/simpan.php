<?php
/**
 * menu/simplan.php -- Tambah atau perbarui menu (foto + deskripsi + tampil_landing + urutan).
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

$id         = (int)($_POST['id'] ?? 0);
$nama       = trim((string)($_POST['nama'] ?? ''));
$kategori   = (string)($_POST['kategori'] ?? 'Kopi');
$harga      = (float)($_POST['harga'] ?? 0);
$status     = (string)($_POST['status'] ?? 'Aktif');
$deskripsi  = trim((string)($_POST['deskripsi'] ?? ''));
$tampilLand = isset($_POST['tampil_landing']) && $_POST['tampil_landing'] !== '' ? 1 : 0;
$urutan     = max(0, (int)($_POST['urutan'] ?? 0));
$hapusFoto  = isset($_POST['hapus_foto']) && $_POST['hapus_foto'] !== '';

if (!in_array($kategori, ['Kopi', 'Non-kopi'], true)) {
    $kategori = 'Kopi';
}

$fitRaw  = strtolower(trim((string)($_POST['foto_fit'] ?? 'cover')));
$fotoFit = in_array($fitRaw, ['cover', 'contain'], true) ? $fitRaw : 'cover';
$posX    = max(0, min(100, (float)($_POST['foto_pos_x'] ?? 50)));
$posY    = max(0, min(100, (float)($_POST['foto_pos_y'] ?? 50)));
$zoom    = max(1.0, min(3.0, (float)($_POST['foto_zoom'] ?? 1)));

$gagal = [];
if ($nama === '') {
    $gagal[] = 'Nama menu wajib diisi.';
}
if ($harga <= 0) {
    $gagal[] = 'Harga jual harus lebih dari Rp0.';
}
if (strlen($deskripsi) > 255) {
    $gagal[] = 'Deskripsi maksimal 255 karakter.';
}
$umf = ini_get('upload_max_filesize');
if ($umf !== false && ini_bytes($umf) > 0 && ini_bytes($umf) < 5.5 * 1024 * 1024) {
    $gagal[] = 'Batas upload server (upload_max_filesize) kurang dari 5 MB — naikkan di php.ini.';
}
$fileProvided = isset($_FILES['foto']) && ($_FILES['foto']['name'] ?? '') !== '';
$fotoBaru = ($id > 0) ? process_menu_upload($id, $_FILES['foto'] ?? []) : null;
if ($fileProvided && $fotoBaru === null && !$hapusFoto) {
    $gagal[] = 'Foto tidak valid (format: JPG/PNG/WebP, maksimal 5 MB).';
}
if ($gagal) {
    flash('error', implode(' ', $gagal));
    redirect('menu/index.php' . ($id > 0 ? '?aksi=edit&id=' . $id : '?aksi=tambah'));
}

$tersedia   = $status === 'Diarsipkan' ? 0 : 1;
$diarsipkan = $status === 'Diarsipkan' ? 1 : 0;

try {
    if ($id > 0) {
        $st = db()->prepare('SELECT id FROM menu WHERE id=?');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            flash('error', 'Menu tidak ditemukan.');
            redirect('menu/index.php');
        }
        if ($hapusFoto || $fotoBaru !== null) {
            menu_delete_photo_files(db(), $id);
        }
        $sql  = 'UPDATE menu SET nama=?, kategori=?, harga=?, tersedia=?, diarsipkan=?, deskripsi=?, tampil_landing=?, urutan=?';
        $vals = [$nama, $kategori, $harga, $tersedia, $diarsipkan, $deskripsi, $tampilLand, $urutan];
        if ($hapusFoto || $fotoBaru !== null) {
            $sql  .= ', foto_path=?';
            $vals[] = ($hapusFoto) ? null : $fotoBaru;
        }
        $sql  .= ', foto_fit=?, foto_pos_x=?, foto_pos_y=?, foto_zoom=? WHERE id=?';
        $vals[] = $fotoFit;
        $vals[] = $posX;
        $vals[] = $posY;
        $vals[] = $zoom;
        $vals[] = $id;
        db()->prepare($sql)->execute($vals);
        flash('success', 'Perubahan menu berhasil disimpan.');
    } else {
        $kategoriId = (int) db()->query('SELECT id FROM kategori ORDER BY id LIMIT 1')->fetchColumn();
        if ($kategoriId <= 0) {
            $kategoriId = 1;
        }
        $st = db()->prepare('INSERT INTO menu (nama, kategori_id, kategori, harga, stok, tersedia, diarsipkan, deskripsi, tampil_landing, urutan, foto_path, foto_fit, foto_pos_x, foto_pos_y, foto_zoom) VALUES (?,?,?,?,0,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$nama, $kategoriId, $kategori, $harga, $tersedia, $diarsipkan, $deskripsi, $tampilLand, $urutan, null, $fotoFit, $posX, $posY, $zoom]);
        $id = (int) db()->lastInsertId();
        if ($id > 0) {
            $fotoBaru = process_menu_upload($id, $_FILES['foto'] ?? []);
            if ($fotoBaru !== null || $hapusFoto) {
                db()->prepare('UPDATE menu SET foto_path=? WHERE id=?')->execute([$hapusFoto ? null : $fotoBaru, $id]);
            }
        }
        flash('success', 'Menu baru berhasil ditambahkan. Tambahkan resepnya sebelum dijual.');
    }
} catch (PDOException $e) {
    error_log('[menu/simplan] ' . $e->getMessage());
    flash('error', 'Gagal menyimpan menu. Silakan coba lagi.');
}

redirect('menu/index.php?aksi=edit&id=' . $id);