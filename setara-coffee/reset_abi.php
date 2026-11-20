<?php
/**
 * reset_abi.php — Reset akun Abi:
 * - role -> kasir (siap login via Google; login lokal dinonaktifkan)
 * - peran -> kasir
 * - status_persetujuan -> approved, aktif = 1
 * - password lokal -> invalid (random hash; login via Google)
 * - hapus semua sesi lama milik abi@setara.coffee (Laragon + XAMPP tmp)
 * Idempoten: aman dijalankan berulang kali.
 */
require_once __DIR__ . '/config/database.php';
$pdo = db();
const EMAIL_ABI = 'abi@setara.coffee';

// 1. Tampilkan data akun sebelum diubah.
$st = $pdo->prepare('SELECT id, username, nama, email, nama_lengkap, peran, role, status_persetujuan, aktif, dibuat_pada FROM users WHERE LOWER(email)=LOWER(?)');
$st->execute([EMAIL_ABI]);
$u = $st->fetch();
if (!$u) { echo "Akun Abi tidak ditemukan.\n"; exit(0); }
echo "Sebelum: " . json_encode($u) . "\n";

// 2. Update akun.
$pdo->prepare("UPDATE users SET role='kasir', peran='kasir', status_persetujuan='approved', aktif=1 WHERE id=?")->execute([$u['id']]);

// 3. Nonaktifkan password lokal (login via Google saja).
$pdo->prepare("UPDATE users SET password_hash=?, password=NULL WHERE id=?")->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $u['id']]);

// 4. Hapus sesi lama milik Abi.
$tmpDirs = ['C:/laragon/laragon/tmp', 'C:/xampp/tmp'];
$hapus = 0;
foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob("$dir/sess_*") ?: [] as $f) {
        if (!is_file($f)) continue;
        $c = @file_get_contents($f);
        if ($c !== false && strpos($c, EMAIL_ABI) !== false) {
            @unlink($f); $hapus++;
            echo "Sesi dihapus: " . basename($f) . "\n";
        }
    }
}
if (!$hapus) echo "Tidak ada sesi lama ditemukan untuk Abi.\n";

// 5. Tampilkan data setelah.
$st = $pdo->prepare('SELECT id, username, email, peran, role, status_persetujuan, aktif FROM users WHERE LOWER(email)=LOWER(?)');
$st->execute([EMAIL_ABI]);
echo "Setelah: " . json_encode($st->fetch()) . "\n";
echo "Done.\n";
