<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    exit('Permintaan tidak valid (CSRF).');
}

$pdo = db();
$form = $_POST['form'] ?? '';

if ($form === 'undang') {
    $nama = trim((string)($_POST['nama'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $peran = (string)($_POST['peran'] ?? '');

    $gagal = [];
    if ($nama === '') $gagal[] = 'Nama wajib diisi.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $gagal[] = 'Email tidak valid.';
    if (strlen($password) < 8) $gagal[] = 'Password minimal 8 karakter.';
    if (!in_array($peran, ['kasir'], true)) $gagal[] = 'Peran tidak valid.';

    if (!$gagal) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email)=LOWER(?)');
        $st->execute([$email]);
        if ((int)$st->fetchColumn() > 0) {
            $gagal[] = 'Email sudah terdaftar.';
        }
    }

    if ($gagal) {
        flash('error', implode(' ', $gagal));
        redirect('pengguna/index.php');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $peranLama = 'kasir';

    $st = $pdo->prepare('INSERT INTO users
        (username, password_hash, nama, nama_lengkap, email, password, peran, role, status_persetujuan, aktif, harus_ganti_password, dibuat_pada)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'approved\', 1, 1, NOW())');
    $st->execute([
        $email,
        $hash,
        $nama,
        $nama,
        $email,
        $hash,
        $peranLama,
        $peran,
    ]);

    flash('success', 'Pengguna ' . $nama . ' diundang. Password sementara: ' . $password . ' — sampaikan secara privat; pengguna wajib menggantinya saat login pertama.');
    redirect('pengguna/index.php');
}

if ($form === 'peran') {
    $id = (int)($_POST['id'] ?? 0);
    $roleBaru = (string)($_POST['role'] ?? '');

    if ($id <= 0) {
        flash('error', 'Pengguna tidak ditemukan.');
        redirect('pengguna/index.php');
    }
    if (!in_array($roleBaru, ['kasir'], true)) {
        flash('error', 'Peran baru hanya boleh Kasir.');
        redirect('pengguna/index.php');
    }

    $st = $pdo->prepare('SELECT id, role FROM users WHERE id=?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) {
        flash('error', 'Pengguna tidak ditemukan.');
        redirect('pengguna/index.php');
    }
    if ($u['role'] === 'owner') {
        flash('error', 'Peran pemilik tidak dapat diubah.');
        redirect('pengguna/index.php');
    }

    $peranLama = 'kasir';
    $pdo->prepare('UPDATE users SET role=?, peran=? WHERE id=?')->execute([$roleBaru, $peranLama, $id]);

    flash('success', 'Peran pengguna berhasil diperbarui menjadi ' . role_label($roleBaru) . '.');
    redirect('pengguna/index.php');
}

flash('error', 'Form tidak dikenal.');
redirect('pengguna/index.php');
