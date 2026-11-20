<?php
/**
 * config/config.php -- Bootstrap aplikasi Café Setara.
 * Memuat .env, mengatur error reporting, zona waktu, konstanta, dan library.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cookie session aman: httponly + samesite Lax + secure saat HTTPS.
    $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('setara_session');
    session_start();
}

date_default_timezone_set('Asia/Makassar');

/** Parser .env sederhana: KEY=VALUE, mendukung comment # dan tanda kutip. */
function load_env(string $path): array
{
    $vars = [];
    if (!is_file($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $vars[$key] = $value;
    }
    return $vars;
}

$ENV = load_env(dirname(__DIR__) . '/.env');

function env(string $key, $default = '')
{
    global $ENV;
    return array_key_exists($key, $ENV) && $ENV[$key] !== '' ? $ENV[$key] : $default;
}

define('APP_ENV', env('APP_ENV', 'development'));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost/setara-app'), '/'));
define('APP_DEBUG', APP_ENV !== 'production');

// base_url() memakai APP_URL apa adanya (sudah termasuk path/subfolder bila ada).
define('APP_BASE_PATH', '');

define('STORAGE_LOG', dirname(__DIR__) . '/storage/logs/app.log');
if (!is_dir(dirname(STORAGE_LOG))) {
    @mkdir(dirname(STORAGE_LOG), 0775, true);
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_LOG);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log(sprintf('[%s] %s di %s:%d', date('Y-m-d H:i:s'), $message, $file, $line));
    if (APP_DEBUG) {
        echo '<div style="font:12px monospace;background:#FBE4E1;color:#B42318;padding:8px;border:1px solid #B42318;margin:4px">'
            . e($message) . ' <small>' . e(basename($file)) . ':' . $line . '</small></div>';
    }
    return true;
});

set_exception_handler(function ($e) {
    error_log(sprintf('[FATAL] %s di %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (APP_DEBUG) {
        echo '<h1>Terjadi kesalahan</h1><pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Terjadi kesalahan</title>'
            . '<style>body{font-family:Inter,Arial,sans-serif;background:#FBF6EE;color:#3B1F0E;display:grid;place-items:center;min-height:100vh;margin:0}'
            . '.box{background:#fff;border:1px solid #E6DCCA;border-radius:14px;padding:32px;max-width:480px;text-align:center}'
            . 'h1{font-family:Fraunces,serif;font-size:28px;margin:0 0 8px}p{color:#5A4A3F}</style></head><body>'
            . '<div class="box"><h1>Terjadi kesalahan</h1><p>Maaf, terjadi gangguan pada aplikasi. Detail error telah dicatat. Silakan coba beberapa saat lagi.</p>'
            . '<a href="' . e(base_url('login.php')) . '" style="color:#5C3317">Kembali ke login</a></div></body></html>';
    }
});

require_once __DIR__ . '/../includes/helpers.php';

// Cek ekstensi wajib. Tampilkan pesan jelas bila kurang.
$requiredExtensions = ['gd', 'mbstring', 'dom', 'pdo_mysql', 'fileinfo'];
$missing = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
if ($missing) {
    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Ekstensi PHP belum terpasang</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#FBF6EE;color:#3B1F0E;padding:40px}'
        . '.box{background:#fff;border:1px solid #E6DCCA;border-radius:14px;padding:28px;max-width:640px}'
        . 'code{background:#F4EBDD;padding:2px 6px;border-radius:4px}</style></head><body><div class="box">'
        . '<h2>Ekstensi PHP belum terpasang</h2><p>Aplikasi Café Setara membutuhkan ekstensi berikut:</p><ul>';
    foreach ($missing as $m) {
        echo '<li><code>' . e($m) . '</code></li>';
    }
    echo '</ul><p>Pasang / aktifkan ekstensi tersebut di php.ini Laragon atau panel hosting, lalu muat ulang halaman ini.</p></div></body></html>';
    exit;
}

// Composer autoload (dompdf). Jika belum ada, aplikasi tetap jalan; ekspor PDF yang akan memberi pesan jelas.
if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
