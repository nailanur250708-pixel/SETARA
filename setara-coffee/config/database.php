<?php
require_once __DIR__ . '/config.php';

/**
 * Koneksi PDO singleton ke database setara_coffee.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'setara_coffee'));
    try {
        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        error_log('[DB] Koneksi gagal: ' . $e->getMessage());
        http_response_code(500);
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Koneksi database gagal</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#FBF6EE;color:#3B1F0E;padding:40px}'
            . '.box{background:#fff;border:1px solid #E6DCCA;border-radius:14px;padding:28px;max-width:640px}</style></head><body>'
            . '<div class="box"><h2>Koneksi database gagal</h2><p>Periksa pengaturan DB_HOST, DB_PORT, DB_NAME, DB_USER, dan DB_PASS di file <code>.env</code>. Pastikan MySQL/Laragon sudah berjalan.</p></div></body></html>';
        exit;
    }
    return $pdo;
}
