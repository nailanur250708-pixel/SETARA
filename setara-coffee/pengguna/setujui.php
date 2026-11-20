<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    exit('Permintaan tidak valid (CSRF).');
}

$pdo = db();
$id = (int)($_POST['id'] ?? 0);
$aksi = (string)($_POST['aksi'] ?? '');
$idSaya = (int)($_SESSION['user']['id'] ?? 0);

if (!in_array($aksi, ['approved', 'rejected'], true)) {
    flash('error', 'Aksi tidak valid.');
    redirect('pengguna/index.php');
}

if ($id === $idSaya && $aksi === 'rejected') {
    flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
    redirect('pengguna/index.php');
}

$st = $pdo->prepare('SELECT id, role, peran, status_persetujuan FROM users WHERE id=?');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) {
    flash('error', 'Pengguna tidak ditemukan.');
    redirect('pengguna/index.php');
}

if ($aksi === 'approved') {
    $peranLama = strtolower((string)($u['peran'] ?? ''));
    if ($u['role'] === 'owner') {
        // Pemilik tetap owner; hanya status yang disetujui.
        $pdo->prepare("UPDATE users SET status_persetujuan='approved', aktif=1 WHERE id=?")->execute([$id]);
    } else {
        $roleBaru = $peranLama === 'admin' ? 'kasir' : ($peranLama === 'kasir' ? 'kasir' : $u['role']);
        if (!in_array($roleBaru, ['kasir'], true)) {
            $roleBaru = 'kasir';
        }
        $pdo->prepare("UPDATE users SET status_persetujuan='approved', aktif=1, role=? WHERE id=?")->execute([$roleBaru, $id]);
    }
    flash('success', 'Akun berhasil disetujui dan diaktifkan.');
} else {
    $pdo->prepare("UPDATE users SET status_persetujuan='rejected', aktif=0 WHERE id=?")->execute([$id]);
    flash('success', 'Akun berhasil dinonaktifkan.');
}

redirect('pengguna/index.php');
