<?php
/** Helper umum Café Setara. Dimuat dari config/config.php setelah APP_URL didefinisikan. */

/** Bangun URL absolut dari APP_URL. */
function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return APP_URL . APP_BASE_PATH . ($path !== '' ? '/' . $path : '');
}

/** Format rupiah tanpa desimal: Rp1.486.000 */
function rupiah($angka): string
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

/** Format tanggal Indonesia: Kamis, 5 November 2026 */
function tanggal_indonesia($tanggal, bool $denganHari = true): string
{
    if ($tanggal instanceof DateTimeInterface) {
        $dt = $tanggal;
    } else {
        $dt = new DateTime((string)$tanggal);
    }
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return ($denganHari ? $hari[(int)$dt->format('w')] . ', ' : '') . $dt->format('j') . ' ' . $bulan[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

/** Escape output. */
function e($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/** Redirect ke path dalam aplikasi. */
function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

/** Simpan pesan flash di session. */
function flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

/** Ambil dan hapus semua pesan flash. */
function flash_all(): array
{
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

/** Tampilkan flash (dipanggil di layout). */
function tampilkan_flash(): void
{
    foreach (flash_all() as $f) {
        $tipe = $f['tipe'];
        $cls = in_array($tipe, ['error', 'danger'], true) ? 'flash-error' : ($tipe === 'warning' ? 'flash-warning' : 'flash-success');
        echo '<div class="flash ' . $cls . '"><span>' . e($f['pesan']) . '</span>'
            . '<button type="button" class="flash-close" aria-label="Tutup">&times;</button></div>';
    }
}

/** Token CSRF sesi. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Input hidden CSRF untuk form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validasi CSRF dari POST / JSON body. */
function csrf_check(): bool
{
    $token = $_POST['csrf_token'] ?? null;
    if ($token === null && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $body = json_decode(file_get_contents('php://input'), true);
        $token = $body['csrf_token'] ?? null;
    }
    if (!is_string($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Alias lama dipakai sebagian endpoint. */
function verify_csrf(): bool
{
    return csrf_check();
}

/** Role label. */
function role_label(string $role): string
{
    return ['owner' => 'Pemilik', 'kasir' => 'Kasir'][$role] ?? ucfirst($role);
}

/** Inisial nama untuk avatar. */
function inisial(string $nama): string
{
    $bagian = preg_split('/\s+/', trim($nama));
    $out = strtoupper(mb_substr($bagian[0] ?? '?', 0, 1));
    if (count($bagian) > 1) {
        $out .= strtoupper(mb_substr(end($bagian), 0, 1));
    }
    return $out;
}

/** Sapaan sesuai jam. */
function sapaan(): string
{
    $j = (int)date('G');
    if ($j < 11) return 'Selamat pagi';
    if ($j < 15) return 'Selamat siang';
    if ($j < 18) return 'Selamat sore';
    return 'Selamat malam';
}

/** Nama depan dari session. */
function nama_depan(): string
{
    return explode(' ', trim($_SESSION['user']['nama'] ?? 'Pengguna'))[0];
}

/** Hitung HPP satu menu dari resep (bahan harga_beli_per_satuan). */
function hpp_menu(int $menuId): float
{
    static $cache = [];
    if (array_key_exists($menuId, $cache)) return $cache[$menuId];
    $st = db()->prepare('SELECT COALESCE(SUM(r.takaran * b.harga_beli_per_satuan),0) FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=?');
    $st->execute([$menuId]);
    return $cache[$menuId] = (float)$st->fetchColumn();
}

/** Bahan yang stoknya di bawah / sama dengan minimum. */
function bahan_kritis(): array
{
    return db()->query('SELECT id,nama,satuan,stok,stok_minimum FROM bahan WHERE stok <= stok_minimum ORDER BY nama')->fetchAll();
}

/** Promo aktif untuk sebuah menu pada hari ini. */
function promo_aktif_menu(int $menuId): array
{
    $today = date('Y-m-d');
    $st = db()->prepare("SELECT * FROM promo WHERE menu_id=? AND aktif=1 AND (berlaku_mulai IS NULL OR berlaku_mulai<=?) AND (berlaku_sampai IS NULL OR berlaku_sampai>=?) ORDER BY kode");
    $st->execute([$menuId, $today, $today]);
    return $st->fetchAll();
}

/** Cek kecukupan bahan untuk satu menu & qty, mengabaikan keranjang. */
function cek_bahan_menu(int $menuId, int $qty): ?string
{
    $st = db()->prepare('SELECT b.nama, r.takaran, b.stok FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=?');
    $st->execute([$menuId]);
    foreach ($st->fetchAll() as $r) {
        if ((float)$r['stok'] < (float)$r['takaran'] * $qty) {
            return 'Stok ' . $r['nama'] . ' tidak cukup untuk menambah pesanan ini';
        }
    }
    return null;
}

/** Upload gambar tervalidasi. Mengembalikan path relatif web atau null. */
function upload_gambar(array $file, string $folder): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return null;
    }
    $nama = bin2hex(random_bytes(12)) . '.' . $map[$mime];
    $dest = dirname(__DIR__) . '/assets/img/' . $folder . '/' . $nama;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'assets/img/' . $folder . '/' . $nama;
}
