<?php
/**
 * migrasi.php — Migrasi aman & idempoten untuk database setara_coffee yang SUDAH ADA.
 * Tidak pernah DROP database / tabel. Aman dijalankan berulang kali.
 * HAPUS FILE INI SETELAH BERHASIL (lihat README.md).
 */
require_once __DIR__ . '/config/database.php';

$log = [];
function catat(string $pesan): void { global $log; $log[] = $pesan; }

function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}
function add_column(PDO $pdo, string $table, string $column, string $definisi, ?string $after = null): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definisi" . ($after ? " AFTER `$after`" : ''));
        catat("Ditambah: `$table`.`$column`");
    }
}

$pdo = db();
catat('Koneksi database berhasil (' . env('DB_NAME') . ').');

/* ---------------------------------------------------------------- A. TABEL BARU */
if (!table_exists($pdo, 'bahan')) {
    $pdo->exec("CREATE TABLE bahan (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nama VARCHAR(100) NOT NULL UNIQUE,
      satuan VARCHAR(20) NOT NULL DEFAULT 'gram',
      harga_beli_per_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
      stok DECIMAL(12,2) NOT NULL DEFAULT 0,
      stok_minimum DECIMAL(12,2) NOT NULL DEFAULT 0,
      dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    catat('Dibuat tabel: bahan');
} else {
    // Tabel bahan lama: tambahkan kolom baru bila belum ada.
    add_column($pdo, 'bahan', 'harga_beli_per_satuan', "DECIMAL(12,2) DEFAULT 0", 'satuan');
    add_column($pdo, 'bahan', 'stok_minimum', "DECIMAL(12,2) DEFAULT 0", 'stok');
    catat('Kolom bahan disesuaikan (harga_beli_per_satuan, stok_minimum).');
}
if (!table_exists($pdo, 'resep')) {
    $pdo->exec("CREATE TABLE resep (
      id INT AUTO_INCREMENT PRIMARY KEY,
      menu_id INT NOT NULL,
      bahan_id INT NOT NULL,
      takaran DECIMAL(10,2) NOT NULL,
      UNIQUE KEY uq_resep (menu_id,bahan_id),
      CONSTRAINT fk_resep_menu2 FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE,
      CONSTRAINT fk_resep_bahan2 FOREIGN KEY (bahan_id) REFERENCES bahan(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    catat('Dibuat tabel: resep');
} else {
    // Tabel resep lama memakai jumlah_bahan.
    try { $pdo->exec('ALTER TABLE resep MODIFY COLUMN jumlah_bahan DECIMAL(10,2) DEFAULT 0'); } catch (PDOException $e) {}
    add_column($pdo, 'resep', 'takaran', "DECIMAL(10,2) NULL", 'bahan_id');
    if (column_exists($pdo, 'resep', 'jumlah_bahan')) {
        $pdo->exec('UPDATE resep SET takaran=jumlah_bahan WHERE takaran IS NULL OR takaran=0');
        catat('Kolom resep.takaran diisi dari jumlah_bahan (data lama).');
    }
    $pdo->exec('UPDATE resep SET takaran=0 WHERE takaran IS NULL');
}
if (!table_exists($pdo, 'pergerakan_stok')) {
    $pdo->exec("CREATE TABLE pergerakan_stok (
      id INT AUTO_INCREMENT PRIMARY KEY,
      bahan_id INT NOT NULL,
      tipe ENUM('masuk','penjualan','koreksi') NOT NULL,
      qty DECIMAL(12,2) NOT NULL,
      keterangan VARCHAR(255) NULL,
      transaksi_id INT NULL,
      user_id INT NULL,
      tanggal DATETIME NOT NULL,
      CONSTRAINT fk_ps_bahan2 FOREIGN KEY (bahan_id) REFERENCES bahan(id),
      CONSTRAINT fk_ps_transaksi2 FOREIGN KEY (transaksi_id) REFERENCES transaksi(id),
      CONSTRAINT fk_ps_user2 FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    catat('Dibuat tabel: pergerakan_stok');
} else {
    // Tabel lama memakai jenis/jumlah/users_id. Tambah kolom baru + migrasi data.
    add_column($pdo, 'pergerakan_stok', 'tipe', "ENUM('masuk','penjualan','koreksi') NULL", 'bahan_id');
    add_column($pdo, 'pergerakan_stok', 'qty', "DECIMAL(12,2) NULL", 'jumlah');
    try { $pdo->exec('ALTER TABLE pergerakan_stok MODIFY COLUMN users_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE pergerakan_stok MODIFY COLUMN transaksi_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE pergerakan_stok MODIFY COLUMN jumlah DECIMAL(12,2) DEFAULT 0'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE pergerakan_stok MODIFY COLUMN keterangan VARCHAR(255) NULL'); } catch (PDOException $e) {}
    add_column($pdo, 'pergerakan_stok', 'user_id', "INT NULL", 'users_id');
    add_column($pdo, 'pergerakan_stok', 'transaksi_id', "INT NULL", 'user_id');
    $pdo->exec("UPDATE pergerakan_stok SET tipe=CASE WHEN jenis='masuk' THEN 'masuk' WHEN jenis='keluar' THEN 'penjualan' ELSE 'koreksi' END WHERE tipe IS NULL OR tipe=''");
    $pdo->exec('UPDATE pergerakan_stok SET qty=CASE WHEN jenis=\'keluar\' THEN -ABS(jumlah) ELSE jumlah END WHERE qty IS NULL');
    $pdo->exec('UPDATE pergerakan_stok SET user_id=users_id WHERE user_id IS NULL');
    catat('Kolom pergerakan_stok disesuaikan (tipe, qty, user_id, transaksi_id).');
}
if (!table_exists($pdo, 'kas')) {
    $pdo->exec("CREATE TABLE kas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      tipe ENUM('masuk','keluar') NOT NULL,
      kategori VARCHAR(50) NOT NULL,
      jumlah DECIMAL(12,0) NOT NULL,
      keterangan TEXT NULL,
      tanggal DATETIME NOT NULL,
      CONSTRAINT fk_kas_user2 FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    catat('Dibuat tabel: kas (baru)');
} else {
    // Tabel kas lama memakai transaksi_id/users_id/jenis/jumlah.
    try { $pdo->exec('ALTER TABLE kas MODIFY COLUMN transaksi_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE kas MODIFY COLUMN users_id INT NULL'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE kas MODIFY COLUMN keterangan TEXT NULL'); } catch (PDOException $e) {}
    add_column($pdo, 'kas', 'user_id', "INT NULL", 'id');
    add_column($pdo, 'kas', 'tipe', "ENUM('masuk','keluar') NULL", 'jenis');
    add_column($pdo, 'kas', 'kategori', "VARCHAR(50) NULL", 'tipe');
    $pdo->exec('UPDATE kas SET user_id=users_id WHERE user_id IS NULL');
    $pdo->exec('UPDATE kas SET tipe=jenis WHERE tipe IS NULL OR tipe=\'\'');
    $pdo->exec("UPDATE kas SET kategori=CASE WHEN jenis='masuk' THEN 'Penjualan' ELSE 'Operasional' END WHERE kategori IS NULL OR kategori=''");
    catat('Kolom kas disesuaikan (user_id, tipe, kategori).');
}
if (!table_exists($pdo, 'login_attempts')) {
    $pdo->exec("CREATE TABLE login_attempts (
      email VARCHAR(100) NOT NULL,
      ip VARCHAR(45) NOT NULL DEFAULT '',
      attempts INT NOT NULL DEFAULT 1,
      first_attempt DATETIME NOT NULL,
      PRIMARY KEY (email, ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    catat('Dibuat tabel: login_attempts');
}

/* --------------------------------------------- B. KOLOM TAMBAHAN (users) */
// Tabel users lama memakai username/password_hash/peran/aktif.
add_column($pdo, 'users', 'nama_lengkap', "VARCHAR(100) NULL", 'nama');
add_column($pdo, 'users', 'email', "VARCHAR(100) NULL", 'nama');
add_column($pdo, 'users', 'password', "VARCHAR(255) NULL", 'password_hash');
add_column($pdo, 'users', 'google_id', "VARCHAR(100) NULL", 'password');
add_column($pdo, 'users', 'role', "ENUM('owner','kasir') NULL", 'peran');
add_column($pdo, 'users', 'status_persetujuan', "ENUM('pending','approved','rejected') DEFAULT 'pending'", 'role');
add_column($pdo, 'users', 'terakhir_login', "DATETIME NULL", 'dibuat_pada');
add_column($pdo, 'users', 'harus_ganti_password', "TINYINT(1) DEFAULT 0", 'terakhir_login');
// Isi nilai default untuk baris lama.
$pdo->exec("UPDATE users SET nama_lengkap=nama WHERE nama_lengkap IS NULL OR nama_lengkap=''");
$pdo->exec("UPDATE users SET password=password_hash WHERE password IS NULL OR password=''");
$pdo->exec("UPDATE users SET role=CASE WHEN peran='admin' THEN 'owner' WHEN peran='kasir' THEN 'kasir' ELSE 'kasir' END WHERE role IS NULL OR role=''");
$pdo->exec("UPDATE users SET status_persetujuan='approved' WHERE status_persetujuan IS NULL OR status_persetujuan='' OR status_persetujuan='pending'");
$pdo->exec("UPDATE users SET email=CONCAT('user', id, '@setara.coffee') WHERE email IS NULL OR email=''");
try { $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)'); catat('Ditambah: index UNIQUE users.email'); }
catch (PDOException $e) { /* sudah ada */ }
catat('Kolom users disesuaikan (nama_lengkap, email, password, google_id, role, status_persetujuan, terakhir_login).');

/* --------------------------------------------- B. menu */
add_column($pdo, 'menu', 'diarsipkan', "TINYINT(1) DEFAULT 0");
add_column($pdo, 'menu', 'kategori', "ENUM('Kopi','Non-kopi') DEFAULT 'Kopi'");
add_column($pdo, 'menu', 'foto_path', "VARCHAR(255) NULL", 'gambar');
// Konversi kategori lama: tabel kategori id=1 'Minuman' -> Kopi; sisanya Non-kopi.
$pdo->exec("UPDATE menu SET kategori='Kopi' WHERE kategori IS NULL OR kategori='' OR kategori='Minuman' OR kategori='Kopi'");
$pdo->exec("UPDATE menu SET kategori='Non-kopi' WHERE kategori NOT IN ('Kopi','Non-kopi')");
$pdo->exec("UPDATE menu SET kategori='Non-kopi' WHERE nama IN ('Setara Linimatcha','Summer Breeze Tea','Darken Bananen')");
catat('Kategori menu dinormalisasi menjadi Kopi / Non-kopi.');

/* --------------------------------------------- B. promo */
add_column($pdo, 'promo', 'berlaku_mulai', "DATE NULL");
add_column($pdo, 'promo', 'berlaku_sampai', "DATE NULL");

/* --------------------------------------------- B. transaksi */
add_column($pdo, 'transaksi', 'user_id', "INT NULL", 'users_id');
add_column($pdo, 'transaksi', 'jenis_pesanan', "ENUM('Makan di tempat','Bawa pulang') DEFAULT 'Makan di tempat'", 'metode_bayar');
add_column($pdo, 'transaksi', 'subtotal', "DECIMAL(12,2) DEFAULT 0", 'total');
add_column($pdo, 'transaksi', 'total_diskon', "DECIMAL(12,2) DEFAULT 0", 'subtotal');
add_column($pdo, 'transaksi', 'total_hpp', "DECIMAL(12,2) DEFAULT 0", 'total_diskon');
add_column($pdo, 'transaksi', 'uang_diterima', "DECIMAL(12,2) NULL", 'total_hpp');
add_column($pdo, 'transaksi', 'kembalian', "DECIMAL(12,2) DEFAULT 0", 'uang_diterima');
add_column($pdo, 'transaksi', 'status', "ENUM('lunas','batal') DEFAULT 'lunas'", 'kembalian');
add_column($pdo, 'transaksi', 'catatan', "TEXT NULL", 'status');
$pdo->exec('UPDATE transaksi SET user_id=users_id WHERE user_id IS NULL');
$pdo->exec('UPDATE transaksi SET subtotal=total WHERE subtotal=0');
$pdo->exec('UPDATE transaksi SET uang_diterima=total WHERE uang_diterima IS NULL');
catat('Kolom transaksi disesuaikan; data lama tidak diubah totalnya.');

/* --------------------------------------------- B. item_transaksi (memakai detail_transaksi) */
$itemTable = table_exists($pdo, 'item_transaksi') ? 'item_transaksi' : 'detail_transaksi';
foreach ([
    'hpp_satuan'    => "DECIMAL(10,2) DEFAULT 0",
    'promo_id'      => "INT NULL",
    'diskon_persen' => "DECIMAL(5,2) DEFAULT 0",
    'diskon_nilai'  => "DECIMAL(12,2) DEFAULT 0",
    'catatan'       => "VARCHAR(255) NULL",
] as $col => $def) {
    if ($col === 'catatan' && $itemTable === 'detail_transaksi') { /* opsional, tetap ditambahkan */ }
    add_column($pdo, $itemTable, $col, $def);
}
$pdo->exec("UPDATE `$itemTable` SET hpp_satuan=0 WHERE hpp_satuan IS NULL");
$pdo->exec("UPDATE `$itemTable` SET diskon_persen=0 WHERE diskon_persen IS NULL");
$pdo->exec("UPDATE `$itemTable` SET diskon_nilai=0 WHERE diskon_nilai IS NULL");
catat("Kolom HPP/promo/diskon ditambahkan pada `$itemTable`.");

/* --------------------------------------------- C. seed akun */
$cekVendor = $pdo->query("SELECT COUNT(*) FROM users WHERE password IN ('\$2y\$10\$contoh') OR password_hash='\$2y\$10\$contoh'")->fetchColumn();
$hash = password_hash('setara123', PASSWORD_DEFAULT);
$seed = [
    ['Rangga', 'rangga@setara.coffee', 'owner'],
    ['Abi',    'abi@setara.coffee',    'kasir'],
    ['Naila',  'naila@setara.coffee',  'kasir'],
];
foreach ($seed as [$nama, $email, $role]) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $st->execute([$email]);
    if (!$st->fetchColumn()) {
        $ins = $pdo->prepare("INSERT INTO users (username,password_hash,nama,nama_lengkap,email,password,peran,role,status_persetujuan,aktif) VALUES (?,?,?,?,?,?,?,?, 'approved',1)");
        $user = strtolower(explode('@', $email)[0]);
        $peranLama = $role === 'owner' ? 'admin' : $peranLama = $role === 'owner' ? 'admin' : 'kasir';;
        $ins->execute([$user, $hash, $nama, $nama, $email, $hash, $peranLama, $role]);
        catat("Dibuat akun seed: $nama ($email, $role)");
    } else {
        catat("Akun sudah ada: $nama ($email) — tidak diubah.");
    }
}
// Jika password masih placeholder "\$2y\$10\$contoh", perbarui.
$pdo->exec("UPDATE users SET password='$hash', password_hash='$hash', status_persetujuan='approved' WHERE password='\$2y\$10\$contoh' OR password_hash='\$2y\$10\$contoh'");
// Pastikan seed akun selalu punya password setara123 bila belum pernah diganti (password lama manager/kasir tetap bisa login).
foreach ($seed as [$nama, $email, $role]) {
    $pdo->prepare("UPDATE users SET password=?, password_hash=?, status_persetujuan='approved' WHERE email=? AND (password IS NULL OR password='' OR password='\$2y\$10\$contoh')")->execute([$hash, $hash, $email]);
}

/* --------------------------------------------- E. seed bahan & resep */
$jumlahBahan = (int)$pdo->query('SELECT COUNT(*) FROM bahan')->fetchColumn();
$bahanSeed = [
    ['Biji Kopi','gram',200,5000,1000],
    ['Susu UHT','ml',18,12000,2000],
    ['Susu Asik (SKM)','ml',25,6000,1000],
    ['Gula Palma','gram',30,3000,500],
    ['Cengkeh & Kayu Manis','gram',150,500,100],
    ['Berry Blend','ml',60,2000,300],
    ['Sirup Peach','ml',50,2000,300],
    ['Soda','ml',5,5000,1000],
    ['Sirup Vanilla','ml',40,2000,300],
    ['Sirup Pandan','ml',40,2000,300],
    ['Banana Essence','ml',60,1000,200],
    ['Bubuk Matcha','gram',250,2000,300],
    ['Coral Tea','gram',400,500,100],
    ['Honey Lime Syrup','ml',45,2000,300],
    ['Bubuk Cokelat','gram',110,2000,300],
    ['Caramel Syrup','ml',40,2000,300],
    ['Butter','gram',90,1000,200],
];
$insB = $pdo->prepare('INSERT INTO bahan (nama,satuan,harga_beli_per_satuan,stok,stok_minimum) VALUES (?,?,?,?,?)');
$insP = $pdo->prepare('INSERT INTO pergerakan_stok (bahan_id,tipe,qty,keterangan,user_id,tanggal) VALUES (?,\'masuk\',?,\'Stok awal\',?,NOW())');
$ownerId = (int)$pdo->query("SELECT id FROM users WHERE role='owner' ORDER BY id LIMIT 1")->fetchColumn();
$stCekBahanNama = $pdo->prepare('SELECT id FROM bahan WHERE nama=?');
$ditambah = 0;
foreach ($bahanSeed as [$nama, $satuan, $harga, $stok, $min]) {
    $stCekBahanNama->execute([$nama]);
    if ($stCekBahanNama->fetchColumn()) continue; // jangan timpa data yang sudah ada
    $insB->execute([$nama, $satuan, $harga, $stok, $min]);
    $insP->execute([(int)$pdo->lastInsertId(), $stok, $ownerId ?: null]);
    $ditambah++;
}
catat("Seed bahan: $ditambah bahan baru ditambahkan (dari " . count($bahanSeed) . " yang diharapkan). Data lama tidak diubah.");
// Resep: petakan nama menu ke id (berdasarkan 10 menu yang diminta).
$resepSeed = [
    'Setara Americano'   => ['Biji Kopi'=>18],
    'Setara Aromatique'  => ['Biji Kopi'=>18,'Cengkeh & Kayu Manis'=>2],
    'Merry-Berry-Cano'   => ['Biji Kopi'=>18,'Berry Blend'=>30,'Soda'=>150],
    'Morning Peach'      => ['Biji Kopi'=>18,'Sirup Peach'=>30,'Berry Blend'=>20,'Soda'=>150],
    'Kosu Klasik'        => ['Biji Kopi'=>18,'Susu Asik (SKM)'=>40,'Sirup Vanilla'=>20],
    'Kopsus Panklepon'   => ['Biji Kopi'=>18,'Susu Asik (SKM)'=>40,'Sirup Pandan'=>20,'Gula Palma'=>15],
    'Setara Kohibana'    => ['Biji Kopi'=>18,'Susu Asik (SKM)'=>40,'Banana Essence'=>20],
    'Setara Linimatcha'  => ['Bubuk Matcha'=>5,'Susu Asik (SKM)'=>40,'Susu UHT'=>120],
    'Summer Breeze Tea'  => ['Coral Tea'=>3,'Honey Lime Syrup'=>30,'Butter'=>5],
    'Darken Bananen'     => ['Bubuk Cokelat'=>25,'Caramel Syrup'=>20,'Banana Essence'=>15,'Susu UHT'=>150],
];
// Pastikan 10 menu ada (nama sesuai spesifikasi). Menu lama yang tidak cocok tetap dipertahankan.
$namaMenu = [
    'Setara Americano','Setara Aromatique','Merry-Berry-Cano','Morning Peach','Kosu Klasik',
    'Kopsus Panklepon','Setara Kohibana','Setara Linimatcha','Summer Breeze Tea','Darken Bananen'
];
$stCekMenu = $pdo->prepare('SELECT id FROM menu WHERE nama=?');
$kategoriDefault = (int)$pdo->query("SELECT id FROM kategori ORDER BY id LIMIT 1")->fetchColumn();
if (!$kategoriDefault) { $pdo->exec("INSERT INTO kategori (nama,aktif) VALUES ('Minuman',1)"); $kategoriDefault = (int)$pdo->lastInsertId(); }
$stInsMenu = $pdo->prepare("INSERT INTO menu (nama,kategori_id,kategori,harga,stok,tersedia,diarsipkan) VALUES (?,?,?,26000,50,1,0)");
$idMenu = [];
foreach ($namaMenu as $i => $nama) {
    $stCekMenu->execute([$nama]);
    $id = $stCekMenu->fetchColumn();
    if (!$id) {
        $stInsMenu->execute([$nama, $kategoriDefault, in_array($nama, ['Setara Linimatcha','Summer Breeze Tea','Darken Bananen'], true) ? 'Non-kopi' : 'Kopi']);
        $id = $pdo->lastInsertId();
        catat("Dibuat menu: $nama");
    }
    $idMenu[$i + 1] = (int)$id;
}
$stBahan = $pdo->prepare('SELECT id FROM bahan WHERE nama=?');
$stCekResep = $pdo->prepare('SELECT COUNT(*) FROM resep WHERE menu_id=? AND bahan_id=?');
$stInsResep = $pdo->prepare('INSERT INTO resep (menu_id,bahan_id,takaran) VALUES (?,?,?)');
$stHapusResep = $pdo->prepare('DELETE FROM resep WHERE menu_id=?');
// Menu yang resepnya di-seed: hapus komposisi lama agar tidak ada resep ganda.
foreach (array_keys($resepSeed) as $namaHapus) {
    $stCekMenu->execute([$namaHapus]);
    $midHapus = (int)$stCekMenu->fetchColumn();
    if ($midHapus) $stHapusResep->execute([$midHapus]);
}
catat('Resep lama pada 10 menu Setara dibersihkan sebelum seed.');
foreach ($resepSeed as $nama => $bahanList) {
    $stCekMenu->execute([$nama]);
    $menuId = (int)$stCekMenu->fetchColumn();
    if (!$menuId) continue;
    foreach ($bahanList as $namaBahan => $takaran) {
        $stBahan->execute([$namaBahan]);
        $bahanId = (int)$stBahan->fetchColumn();
        if (!$bahanId) continue;
        $stCekResep->execute([$menuId, $bahanId]);
        if (!$stCekResep->fetchColumn()) {
            $stInsResep->execute([$menuId, $bahanId, $takaran]);
        }
    }
}
catat('Resep 10 menu Setara dipastikan tersedia.');

/* --------------------------------------------- Selesai */
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Migrasi selesai · Café Setara</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>body{font-family:Inter,Arial,sans-serif;background:#FBF6EE;color:#5A4A3F;padding:32px}.box{max-width:760px;margin:0 auto;background:#fff;border:1px solid #E6DCCA;border-radius:14px;padding:28px}h1{font-family:Fraunces,serif;color:#3B1F0E;margin:0 0 6px}ol{padding-left:20px;line-height:1.9}li{font-size:13px}.peringatan{background:#FFF4D6;border:1px solid #eddbab;color:#8A5A00;border-radius:10px;padding:14px;margin-top:20px;font-size:13px}a.btn{display:inline-block;margin-top:8px;background:#5C3317;color:#fff;padding:11px 18px;border-radius:10px;text-decoration:none;font-weight:600}</style></head><body>
<div class="box"><h1>✓ Migrasi berhasil</h1><p>Berikut perubahan yang diterapkan pada database <strong><?= e(env('DB_NAME')) ?></strong>:</p><ol>
<?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
</ol><div class="peringatan"><strong>PENTING — hapus file migrasi.php sekarang.</strong><br>File ini tidak boleh tersisa di server produksi. Setelah dihapus, login dengan akun seed <code>rangga@setara.coffee</code> /-password <code>setara123</code>, lalu ganti passwordnya.</div>
<a class="btn" href="<?= e(base_url('login.php')) ?>">Lanjut ke halaman masuk →</a></div></body></html>
