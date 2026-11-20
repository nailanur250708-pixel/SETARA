# Café Setara — Sistem Kelola Kedai

Aplikasi PHP native (tanpa framework) untuk mengelola kedai kopi: login & OAuth Google, dashboard pemilik, kasir/POS, manajemen menu & promo & galeri, resep & HPP, bahan & stok, laporan dengan ekspor PDF, serta pengguna & keamanan.

---

## 1. Struktur teknis

- PHP 8.1+ (dikembangkan & diuji dengan PHP 8.3.33 di Laragon)
- MySQL 8.x, PDO dengan `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`
- Library: **dompdf** via Composer, Chart.js 4.4.1 & Lucide 0.383.0 via CDN, Google Fonts (Fraunces + Inter)
- Zona waktu: `Asia/Makassar` — semua tanggal ditampilkan dalam format Indonesia
- Semua URL dibangun dengan `base_url()` dari `APP_URL` di `.env` (tidak ada path hardcoded)

### Ekstensi PHP wajib (dicek saat boot)
`gd`, `mbstring`, `dom`, `pdo_mysql`, `fileinfo` (dan `zip`/`openssl` untuk Composer). Jika ada yang kurang, aplikasi menampilkan halaman pesan yang jelas, bukan error 500.

---

## 2. Instalasi lokal di Laragon

1. Pastikan Laragon berjalan (Apache + MySQL). Path dokument root Laragon di mesin ini: `C:\laragon\laragon\www`.
2. Salin folder `setara-coffee` ke `C:\laragon\laragon\www\setara-coffee`.
   - Cara cepat: jalankan `sync-to-laragon.ps1` dari folder proyek (atau copy manual). Skrip PowerShell mungkin perlu `Set-ExecutionPolicy -Scope Process Bypass`.
3. Pastikan database `setara_coffee` sudah ada (database lama dipertahankan, **TIDAK di-DROP**).
4. File `.env` sudah berisi konfigurasi Laragon:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=setara_coffee
   DB_USER=root
   DB_PASS=
   APP_URL=http://localhost/setara-coffee
   APP_ENV=development
   ```
5. Jalankan migrasi **sekali** dengan membuka `http://localhost/setara-coffee/migrasi.php`. Migrasi idempoten (aman diulang), menampilkan daftar perubahan, lalu **hapus file `migrasi.php` dari server**.
6. Install dependency Composer (jika `vendor/` belum ada):
   ```
   cd C:\laragon\laragon\www\setara-coffee
   composer install
   ```
   Di mesin ini Composer ada di `C:\laragon\laragon\bin\composer\composer.phar`; ekstensi `zip` di `php.ini` perlu aktif saat instalasi.
7. Buka `http://localhost/setara-coffee`.

### Akun seed (password semua: `setara123`)

| Nama | Email | Peran |
|---|---|---|
| Rangga | `rangga@setara.coffee` | owner |
| Abi | `abi@setara.coffee` | kasir |
| Naila | `naila@setara.coffee` | kasir |

Akun lama (`Manager Setara`, `Kasir Satu`) tetap dipertahankan dengan password semula. Setelah login, **ganti password seed** melalui menu Pengguna & keamanan (owner) atau sesuaikan langsung di database.

---

## 3. Deploy ke shared hosting

1. Upload semua file. Jika hosting tidak menjalankan Composer, jalankan `composer install` lokal lalu upload folder `vendor/` beserta `composer.lock`.
2. Pastikan PHP 8.1+ dengan ekstensi `gd`, `mbstring`, `dom`, `pdo_mysql`, `fileinfo`.
3. Buat database & user di panel hosting, lalu isi `.env`:
   ```
   DB_HOST=localhost
   DB_NAME=nama_database
   DB_USER=user_database
   DB_PASS=password_database
   APP_URL=https://domain-anda
   APP_ENV=production
   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   GOOGLE_REDIRECT_URI=https://domain-anda/login.php?google=callback
   ```
4. Jika database kosong, import dump (opsional) atau jalankan `migrasi.php` sekali lalu **hapus file tersebut**.
5. Pastikan folder `storage/logs`, `assets/img/menu`, `assets/img/galeri` writable (chmod 755/775).
6. Set `APP_ENV=production` — error ditampilkan sebagai halaman umum "Terjadi kesalahan", detail masuk ke `storage/logs/app.log`.
7. Ganti semua password seed.
8. `.htaccess` sudah memblokir akses ke `.env`, `composer.json`, `config/`, `includes/`, `views/`, `storage/`, `vendor/`, dan `migrasi.php`.

Aplikasi **tidak memakai** `mail()` maupun `exec()`, aman pada hosting yang membatasi fungsi tersebut.

---

## 4. Asumsi & keputusan desain

Berikut detail yang tidak ditulis eksplisit di spesifikasi dan keputusan yang diambil:

1. **Database yang sudah ada berbeda dengan spesifikasi.** Tabel `users` lama memakai `username/password_hash/peran/aktif`; `menu` memakai `kategori_id/harga/gambar`; tabel item bernama `detail_transaksi` (bukan `item_transaksi`); tabel `kas` & `pergerakan_stok` memakai nama kolom lama. Sesuai keputusan, migrasi **menambah kolom** ke tabel lama (tanpa DROP) dan memakai `detail_transaksi` sebagai tabel item transaksi. Data transaksi lama tetap dipertahankan; kolom baru diisi default (HPP 0, diskon 0) tanpa mengubah total lama.
2. **Akun seed.** Database lama tidak memiliki kolom email, jadi tiga akun seed (Rangga/Abi/Naila) ditambahkan dengan email `@setara.coffee` (bukan mengganti nama user lama).
3. **Bahan & resep.** Karena tabel `bahan` lama sudah berisi 5 bahan, seed menambahkan 17 bahan yang belum ada (tanpa menimpa data lama). Resep 10 menu yang di-seed **mengganti** resep lama menu-menu tersebut agar tidak ada komposisi ganda.
4. **Foto login.** Aset foto cangkir dengan figur Lego (`gambar 1`) tidak tersedia di workspace. `assets/img/login-hero.jpg` memakai foto hero kedai yang sudah ada. Ganti file tersebut dengan foto yang dimaksud jika tersedia.
5. **Versi dompdf.** Spesifikasi meminta `^2.0`, tetapi Composer menolak semua rilis 2.x karena advisory keamanan. Dipakai `dompdf/dompdf:^3.1` (API `Dompdf\Dompdf` kompatibel). Footer PDF disesuaikan dengan API `Canvas::text()` v3.
6. **Pengguna & keamanan — ganti password pertama.** Field `harus_ganti_password` disiapkan, tetapi alur paksa ganti password belum diwajibkan di login (fitur opsional sesuai spesifikasi). Undang pengguna menampilkan password sementara sekali.
7. **Google OAuth.** Tombol tampil aktif hanya jika `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` diisi. Jika kosong, tombol tampil nonaktif dengan tooltip "Belum dikonfigurasi". Akun Google yang belum terdaftar ditolak (harus daftar via `register.php`).
8. **Landing page staff.** `staff.php` adalah laman masuk staf: menampilkan salam staff dan tombol "Masuk ke Aplikasi" → `login.php` (atau dashboard kalau sudah login). Permintaan ke `/` dan `/index.html` di‑redirect ke `staff.php` via `.htaccess`.
9. **File JS pendukung** (`app.js`, `dashboard.js`, `pos.js`, `pembayaran.js`, `laporan.js`) berisi utilitas; logika utama per halaman ditanamkan inline agar tidak ada dependensi tambahan.
10. **Satuan stok** dibiarkan apa adanya (gram/ml) sesuai data seed; konversi antar satuan tidak dilakukan karena tidak ada tabel konversi di spesifikasi.
11. **Mode gelap.** Tombol di kanan atas navbar/topbar (juga di login & register) mengganti tema terang/gelap. Pilihan disimpan di `localStorage`; jika belum ada pilihan, sistem mengikuti `prefers-color-scheme`. Palet gelap memakai coklat tua `#2A1609`, kartu `#3B2415`, teks krem `#FBF6EE`, aksen emas `#C9A55B`. PDF sengaja tetap memakai skema terang agar mudah dicetak.
12. **Pemilik vs kasir.** Setelah login, role owner diarahkan ke `dashboard.php` dengan sidebar 7/5 menu; role kasir diarahkan ke `kasir/index.php` dengan topbar POS (tanpa sidebar). Pemisahan ditegakkan di server (`require_role`).

---

## 5. Daftar file

```
setara-coffee/
  login.php, register.php, logout.php, dashboard.php
  staff.php                       Landing page staff (masuk ke login.php)
  migrasi.php                    HAPUS setelah dipakai
  .env, .env.example, .htaccess, composer.json, composer.lock
  README.md, sync-to-laragon.ps1
  config/config.php, config/database.php
  includes/helpers.php, auth.php, csrf.php, flash.php,
           layout_owner.php, layout_footer.php, layout_kasir.php
  api/penjualan_5hari.php
  kasir/index.php, pembayaran.php, struk.php, batalkan.php
  kasir/api/keranjang.php, bayar.php
  menu/index.php, simpan.php, arsip.php, hapus.php
  promo/simpan.php, hapus.php, toggle.php
  galeri/simpan.php, hapus.php
  resep/index.php, simpan.php
  bahan/index.php, simpan.php, stok_masuk.php, koreksi.php, riwayat.php
  laporan/index.php, export_pdf.php, simpan_kas.php
  views/pdf/layout_pdf.php, pendapatan.php, stok.php, cashflow.php
  pengguna/index.php, simpan.php, setujui.php
  assets/css/app.css
  assets/js/app.js, dashboard.js, pos.js, pembayaran.js, laporan.js
  assets/img/login-hero.jpg, menu/, galeri/, assets/img/kopi-susu.jpg, assets/img/kopi-hitam.jpg, assets/img/cokelat.jpg, assets/img/milky.jpg
  storage/logs/                  (app.log dibuat otomatis)
  vendor/                        (dari Composer)
```

---

## 6. Hak akses (ditegakkan di server)

| Halaman | Pemilik | Kasir |
|---|---|---|
| Dashboard | ✓ | — |
| Kasir / POS & pembayaran | ✓ | ✓ |
| Lihat stok (bahan) | ✓ | — |
| Kelola menu, resep, HPP | ✓ | — |
| Kelola bahan, stok, pembelian | ✓ | — |
| Laporan & ekspor PDF | ✓ | — |
| Kelola akun, peran, persetujuan | ✓ | — |

Akses langsung ke halaman yang tidak diizinkan menghasilkan halaman **403 Akses ditolak**. Semua endpoint JSON juga memanggil `require_role()`.

---

## 7. Keamanan

- Prepared statement PDO untuk semua query; semua output melalui `e()`.
- CSRF wajib pada semua POST (form & JSON).
- Password `password_hash` / `password_verify`.
- Upload: cek MIME dengan `finfo`, ekstensi jpg/png/webp, maks 2 MB, nama file random.
- Session: `httponly`, `SameSite=Lax`, `secure` saat HTTPS, `session_regenerate_id` saat login, idle timeout 2 jam.
- Rate limit login: 5 gagal / 15 menit per email (tabel `login_attempts`).
- Production: `display_errors=0`, error/log ke `storage/logs/app.log`.
