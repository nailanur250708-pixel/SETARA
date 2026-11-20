# CARA UPLOAD CAFÉ SETARA KE WEB (HOSTING)

Panduan paling singkat untuk langsung bisa dibuka di browser.

---

## LANGKAH 1 — Daftar hosting (gratis dulu boleh)

Rekomendasi cepat (pilih salah satu):

| Hosting | Catatan |
|---|---|
| **InfinityFree** | Gratis, ada PHP + MySQL + phpMyAdmin. URL: `namakamu.infinityfreeapp.com` |
| **000webhost** | Gratis, mirip cPanel |
| **Hostinger / Niagahoster** | Berbayar, paling stabil untuk proyek nyata |

> Catatan: hosting gratis biasanya memasang iklan atau subdomain `.infinityfreeapp.com`. Untuk kafe sungguhan, sewa hosting berbayar + domain sendiri.

---

## LANGKAH 2 — Buat database di hosting

1. Login ke panel hosting → buka **MySQL Databases** / **phpMyAdmin**.
2. Buat database, misalnya: `u1234567_setara`.
3. Buat user database & password, lalu **hubungkan user ke database** dengan hak *ALL PRIVILEGES*.
4. Catat 4 informasi ini:
   - Host: biasanya `localhost` (atau sesuai panel)
   - Nama database: `u1234567_setara`
   - Username: `u1234567_naila`
   - Password: `********`

---

## LANGKAH 3 — Import tabel

1. Buka **phpMyAdmin** → pilih database yang tadi dibuat.
2. Klik tab **Import** → pilih file `config/schema.sql` (dari folder ini).
3. Klik **Go / Import**.
4. Pastikan muncul 3 tabel: `menu`, `transaksi`, `item_transaksi`, dan 5 data menu.

> Kalau hosting tidak mengizinkan `CREATE DATABASE` di schema.sql, itu normal — database sudah dibuat di panel, jadi impor tetap jalan karena ada `USE setara_coffee;`.
> **Penting:** edit baris `USE setara_coffee;` di `schema.sql` menjadi nama database hosting Anda (mis. `USE u1234567_setara;`) sebelum import kalau nama beda.

---

## LANGKAH 4 — Atur koneksi PHP

Buka `config/database.php`, ubah 4 baris ini:

```php
$dbHost = 'localhost';              // host DB dari panel
$dbName = 'u1234567_setara';        // nama database hosting
$dbUser = 'u1234567_naila';         // username DB hosting
$dbPass = 'password-anda';          // password DB hosting
```

Simpan.

---

## LANGKAH 5 — Upload file

1. Di panel hosting buka **File Manager** → masuk ke folder `public_html` (atau `htdocs` / `www`).
2. Upload **semua isi folder ini** (jangan foldernya), atau:
   - Zip dulu isi folder ini → upload → Extract di `public_html`.
3. Pastikan struktur di hosting jadi seperti ini:

```
public_html/
├── index.php
├── app.php
├── kasir.php
├── menu.php
├── .htaccess
├── config/
│   ├── database.php
│   └── schema.sql
├── includes/
├── css/
└── assets/
```

> `.htaccess` mungkin tersembunyi — aktifkan "Show hidden files" di File Manager.

---

## LANGKAH 6 — Buka di browser

Buka:
```
https://namadomain-atau-subdomain-anda/
```

Seharusnya langsung muncul landing page Café Setara. Coba klik **Masuk aplikasi** → dashboard, **Menu**, dan **Kasir** untuk memastikan koneksi DB jalan.

---

## Troubleshooting cepat

| Gejala | Penyebab & solusi |
|---|---|
| "Koneksi database gagal" | Salah `$dbUser`/`$dbPass`/`$dbName` di `config/database.php`, atau user belum dihubungkan ke DB di panel. |
| Halaman putih | PHP error disembunyikan. Cek Error Log di panel hosting, atau tambahkan di baris paling atas `index.php`: `ini_set('display_errors', 1);` (hanya saat debug, matikan lagi nanti). |
| 404 di `/kasir` | `.htaccess` tidak terbaca. Pastikan upload dan Apache mengizinkan `AllowOverride All` (di hosting shared biasanya sudah). Atau pakai URL `/kasir.php`. |
| CSS tidak load | Cek path `css/tokens.css` — pastikan folder `css` ikut ter-upload. |
| Font tidak muncul | Google Fonts diblokir some hosting; masih ada fallback Georgia/system-ui. |

---

## Keamanan sebelum go-live

- [ ] Ubah password database dengan yang kuat.
- [ ] Jangan biarkan `display_errors` aktif di production.
- [ ] File `config/schema.sql` sebaiknya dihapus dari hosting setelah import (tidak perlu diakses publik).
- [ ] Jika mau, pindahkan `config/` dan `includes/` keluar dari `public_html` (advanced).

---

## Setelah deploy

Kalau mau, saya bisa bantu:
- buat **pretty URL** tanpa `.php` (sudah ada di `.htaccess`),
- tambah **halaman login** untuk kasir,
- atau siapkan **GitHub Actions** supaya setiap `git push` otomatis ter-upload ke hosting (butuh kredensial FTP dari panel hosting).
