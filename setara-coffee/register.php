<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$errors = [];
$sukses = false;
$nilai = ['nama'=>'','email'=>'','role'=>'kasir'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    if (!csrf_check()) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nilai['nama'] = trim($_POST['nama'] ?? '');
        $nilai['email'] = strtolower(trim($_POST['email'] ?? ''));
        $nilai['role'] = $_POST['role'] ?? 'kasir';
        $pass = $_POST['password'] ?? '';

        if ($nilai['nama'] === '') $errors[] = 'Nama lengkap wajib diisi.';
        if (!filter_var($nilai['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
        if (!in_array($nilai['role'], ['kasir'], true)) $errors[] = 'Peran yang diajukan tidak valid.';
        if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $errors[] = 'Password harus minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.';
        }
        if (stripos($pass, $nilai['nama']) !== false || ($nilai['email'] && stripos($pass, explode('@', $nilai['email'])[0]) !== false)) {
            $errors[] = 'Jangan gunakan nama atau email sebagai password.';
        }
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email)=LOWER(?)');
        $st->execute([$nilai['email']]);
        if ($st->fetchColumn()) $errors[] = 'Email sudah terdaftar.';

        if (!$errors) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $peranLama = 'kasir';
            $ins = db()->prepare("INSERT INTO users (username,password_hash,nama,nama_lengkap,email,password,peran,role,status_persetujuan,aktif) VALUES (?,?,?,?,?,?,?,?,'pending',1)");
            $user = preg_replace('/[^a-z0-9]/','', strtolower($nilai['email']));
            $ins->execute([$user, $hash, $nilai['nama'], $nilai['nama'], $nilai['email'], $hash, $peranLama, $nilai['role']]);
            $sukses = true;
        }
    }
}
$googleUrl = '';
if (env('GOOGLE_CLIENT_ID') && env('GOOGLE_CLIENT_SECRET')) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'=>env('GOOGLE_CLIENT_ID'),'redirect_uri'=>env('GOOGLE_REDIRECT_URI'),
        'response_type'=>'code','scope'=>'openid email profile','state'=>$_SESSION['oauth_state'],'prompt'=>'select_account',
    ]);
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar · Café Setara</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>"><script src="https://cdn.jsdelivr.net/npm/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script>(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script></head>
<body class="halaman-auth">
<section class="auth-kiri">
  <a class="logo" href="<?= e(base_url('login.php')) ?>"><span class="logo-mark" data-lucide="coffee"></span><span class="logo-teks"><strong>Café Setara</strong><small>RUANG KELOLA KEDAI</small></span></a>
  <h1 class="auth-judul">Racikan yang hangat.<br>Operasional yang tertata.</h1>
  <p class="auth-desk">Ruang kerja untuk menjaga setiap menu, setiap bahan, dan setiap keputusan Café Setara.</p>
  <p class="auth-caption">Café Setara / Ruang kelola kedai / 2026</p>
</section>
<section class="auth-kanan"><div class="auth-form">
  <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
    <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
      <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
    </button>
  </div>
  <a class="kembali" href="<?= e(base_url('login.php')) ?>">← Kembali ke login</a>
  <p class="eyebrow">BERGABUNG KE RUANG KERJA</p><h1>Ajukan akses akun.</h1>
  <p class="sub">Pemilik meninjau peran sebelum akun dapat digunakan.</p>

  <?php if ($sukses): ?>
    <div class="kotak-status kuning"><strong>State setelah daftar: menunggu persetujuan</strong>Permohonan terkirim. Anda belum memiliki akses operasional sampai pemilik menyetujui akun dan peran.</div>
    <a class="btn btn-secondary btn-block" href="<?= e(base_url('login.php')) ?>">Kembali ke halaman masuk</a>
  <?php else: ?>
    <?php if ($errors): ?><div class="kotak-status merah"><strong>Periksa kembali pengajuan Anda</strong><?php foreach ($errors as $er): ?><div>• <?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_url('register.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="form" value="register">
      <div class="form-group"><label for="nama">Nama lengkap</label><input id="nama" name="nama" required value="<?= e($nilai['nama']) ?>" placeholder="Nama Anda"></div>
      <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" required value="<?= e($nilai['email']) ?>" placeholder="nama@setara.coffee"></div>
      <div class="form-group"><label for="password">Password</label>
        <div class="input-ikon"><input id="password" name="password" type="password" required><button class="ikon-kanan" type="button" onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password'" data-lucide="eye" aria-label="Tampilkan password"></button></div>
        <p class="small muted" style="margin-top:6px">Minimal 8 karakter, huruf besar/kecil, dan angka. Jangan gunakan nama atau email.</p>
      </div>
      <div class="form-group"><label for="role">Peran yang diajukan</label>
        <select id="role" name="role"><option value="kasir" <?= $nilai['role']==='kasir'?'selected':'' ?>>Kasir</option></select>
      </div>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:8px">→ Kirim permohonan akun</button>
    </form>
    <div class="pemisah">atau</div>
    <?php if ($googleUrl): ?><a class="btn btn-secondary btn-block" href="<?= e($googleUrl) ?>">Daftar dengan Google</a>
    <?php else: ?><span class="tooltip-wrap btn btn-secondary btn-block" style="opacity:.6;cursor:not-allowed"><span class="tooltip">Belum dikonfigurasi</span>Daftar dengan Google</span><?php endif; ?>
    <p class="auth-foot">Sudah memiliki akun? <a href="<?= e(base_url('login.php')) ?>">Masuk →</a></p>
  <?php endif; ?>
</div></section>
<script>document.addEventListener('DOMContentLoaded',function(){
  if(window.lucide)lucide.createIcons();
  var t=document.getElementById('themeToggle');
  if(t)t.addEventListener('click',function(){
    var s=document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light';
    var n=s==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',n);
    try{localStorage.setItem('setara_theme',n);}catch(e){}
  });
});</script></body></html>
