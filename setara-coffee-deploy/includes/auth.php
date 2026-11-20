<?php
/**
 * Autentikasi sederhana berbasis session — Café Setara
 * Login dummy (tanpa database): kredensial disimpan di array di bawah.
 * Role: admin (kasir & kelola menu) | pelanggan (lihat menu / pemesanan).
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Kredensial dummy. Ganti dengan query database bila diperlukan. */
const AKUN_SETARA = [
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
        'nama'     => 'Admin Setara',
        'role'     => 'admin',
    ],
    'kasir' => [
        'username' => 'kasir',
        'password' => 'kasir123',
        'nama'     => 'Kasir Setara',
        'role'     => 'admin',
    ],
    'pelanggan' => [
        'username' => 'pelanggan',
        'password' => 'pelanggan123',
        'nama'     => 'Pelanggan Setara',
        'role'     => 'pelanggan',
    ],
];

/** Proses percobaan login. Mengembalikan array error (kosong bila sukses). */
function coba_login(string $username, string $password, string $peran): array
{
    foreach (AKUN_SETARA as $akun) {
        if ($akun['username'] === $username
            && $akun['password'] === $password
            && $akun['role'] === $peran) {
            session_regenerate_id(true);
            $_SESSION['login'] = [
                'username' => $akun['username'],
                'nama'     => $akun['nama'],
                'role'     => $akun['role'],
            ];
            return [];
        }
    }
    return ['Username, password, atau peran tidak sesuai.'];
}

function sudah_login(): bool
{
    return isset($_SESSION['login']) && is_array($_SESSION['login']);
}

function peran_login(): ?string
{
    return sudah_login() ? ($_SESSION['login']['role'] ?? null) : null;
}

function nama_login(): string
{
    return sudah_login() ? (string) ($_SESSION['login']['nama'] ?? '') : '';
}

/** Halaman khusus admin/kasir. Redirect ke login bila belum masuk. */
function wajib_admin(): void
{
    if (!sudah_login() || peran_login() !== 'admin') {
        header('Location: login.php?peran=admin&redirect=' . urlencode(basename($_SERVER['PHP_SELF'])));
        exit;
    }
}

/** Halaman untuk pelanggan (atau admin juga boleh). */
function wajib_login(): void
{
    if (!sudah_login()) {
        header('Location: login.php?redirect=' . urlencode(basename($_SERVER['PHP_SELF'])));
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
