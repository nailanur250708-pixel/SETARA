<?php
require_once __DIR__ . '/../config/database.php';

// Idle timeout 2 jam.
$__idle = 2 * 60 * 60;
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $__idle) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    flash('warning', 'Sesi berakhir, silakan masuk kembali.');
    redirect('login.php');
}
if (isset($_SESSION['user'])) {
    $_SESSION['last_activity'] = time();
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }
}

/** Pastikan pengguna memiliki salah satu peran yang diizinkan. */
function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        render_403();
    }
}

function render_403(): void
{
    http_response_code(403);
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Akses ditolak · Café Setara</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . e(base_url('assets/css/app.css')) . '"></head>'
        . '<body class="halaman-error"><div class="kotak-error"><div class="ikon-error">⛔</div>'
        . '<p class="eyebrow">CAFÉ SETARA</p><h1>Akses ditolak</h1>'
        . '<p>Peran Anda tidak memiliki izin untuk membuka halaman ini. Jika Anda merasa ini keliru, hubungi pemilik kedai.</p>'
        . '<a class="btn btn-primary" href="' . e(base_url('dashboard.php')) . '">Kembali ke dashboard</a></div></body></html>';
    exit;
}

/** Semua endpoint POST/JSON: CSRF + role. */
function require_post_role(array $roles): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'pesan' => 'Permintaan tidak valid (CSRF).']);
        exit;
    }
    require_role($roles);
}
