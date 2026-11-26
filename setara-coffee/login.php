<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$error = '';
$statusBox = null; // ['kelas'=>..., 'judul'=>..., 'isi'=>...]

/* ------------------------------------ Google OAuth callback */
$googleError = '';
if (isset($_GET['google']) && $_GET['google'] === 'callback') {
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (!$code || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
        $googleError = 'Permintaan Google tidak valid. Silakan coba lagi.';
    } else {
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirect = env('GOOGLE_REDIRECT_URI');
        if (!$clientId || !$clientSecret) {
            $googleError = 'Google belum dikonfigurasi.';
        } else {
            $post = http_build_query([
                'code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret,
                'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
            ]);
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $post, 'timeout' => 15,
            ]]);
            $tokenJson = @file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
            $token = $tokenJson ? json_decode($tokenJson, true) : null;
            if (!empty($token['access_token'])) {
                $ctx2 = stream_context_create(['http' => ['header' => "Authorization: Bearer {$token['access_token']}\r\n", 'timeout' => 15]]);
                $userJson = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, $ctx2);
                $gUser = $userJson ? json_decode($userJson, true) : null;
                if (!empty($gUser['email'])) {
                    $st = db()->prepare('SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
                    $st->execute([trim($gUser['email'])]);
                    $u = $st->fetch();
                    if (!$u) {
                        $googleError = 'Email Google belum terdaftar. Silakan daftar lewat halaman register.php terlebih dahulu.';
                    } elseif ($u['status_persetujuan'] === 'pending') {
                        $statusBox = ['kelas'=>'kuning','judul'=>'Akun menunggu persetujuan','isi'=>'Akun Anda belum disetujui pemilik. Silakan tunggu persetujuan.'];
                    } elseif ($u['status_persetujuan'] === 'rejected') {
                        $statusBox = ['kelas'=>'merah','judul'=>'Akun tidak aktif','isi'=>'Akun Anda tidak aktif. Hubungi pemilik kedai.'];
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user'] = ['id'=>(int)$u['id'],'nama'=>$u['nama_lengkap'] ?: $u['nama'],'email'=>$u['email'],'role'=>$u['role']];
                        db()->prepare('UPDATE users SET terakhir_login=NOW(), google_id=? WHERE id=?')->execute([$gUser['sub'] ?? null, $u['id']]);
                        redirect($u['role'] === 'kasir' ? 'kasir/index.php' : 'dashboard.php');
                    }
                } else {
                    if ($googleError === '') {
                        $googleError = 'Google tidak dapat memverifikasi akun Anda. Silakan coba lagi.';
                    }
                }
            } else {
                $googleError = 'Google tidak dapat memverifikasi akun Anda. Silakan coba lagi.';
            }
        }
    }
}

/* ------------------------------------ Login biasa */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    if (!csrf_check()) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

        // Rate limit: 5 gagal / 15 menit per email.
        $st = db()->prepare('SELECT attempts, first_attempt FROM login_attempts WHERE email=? AND ip=?');
        $st->execute([$email, $ip]);
        $attempt = $st->fetch();
        if ($attempt && (int)$attempt['attempts'] >= 5 && strtotime($attempt['first_attempt']) > time() - 900) {
            $error = 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.';
        } else {
            $st = db()->prepare('SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
            $st->execute([$email]);
            $u = $st->fetch();
            $ok = $u && password_verify($pass, $u['password'] ?: $u['password_hash']);
            if (!$ok) {
                // Pesan generik: tidak membocorkan apakah email terdaftar.
                if ($attempt && strtotime($attempt['first_attempt']) > time() - 900) {
                    db()->prepare('UPDATE login_attempts SET attempts=attempts+1 WHERE email=? AND ip=?')->execute([$email, $ip]);
                } else {
                    db()->prepare('INSERT INTO login_attempts (email,ip,attempts,first_attempt) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=1, first_attempt=NOW()')->execute([$email, $ip]);
                }
                $error = 'Email atau password tidak sesuai. Periksa kembali lalu coba lagi.';
            } elseif ($u['status_persetujuan'] !== 'approved') {
                if ($u['status_persetujuan'] === 'pending') {
                    $statusBox = ['kelas'=>'kuning','judul'=>'Akun menunggu persetujuan','isi'=>'Pemilik belum menyetujui akun Anda. Silakan tunggu atau hubungi pemilik kedai.'];
                } else {
                    $statusBox = ['kelas'=>'merah','judul'=>'Akun tidak aktif','isi'=>'Akun Anda tidak aktif. Hubungi pemilik kedai untuk mengaktifkannya kembali.'];
                }
            } else {
                db()->prepare('DELETE FROM login_attempts WHERE email=? AND ip=?')->execute([$email, $ip]);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id'=>(int)$u['id'],'nama'=>$u['nama_lengkap'] ?: $u['nama'],'email'=>$u['email'],'role'=>$u['role']];
                $_SESSION['login_time'] = time();
                db()->prepare('UPDATE users SET terakhir_login=NOW() WHERE id=?')->execute([$u['id']]);
                redirect($u['role'] === 'kasir' ? 'kasir/index.php' : 'dashboard.php');
            }
        }
    }
}

$googleUrl = '';
if (env('GOOGLE_CLIENT_ID') && env('GOOGLE_CLIENT_SECRET')) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'response_type' => 'code', 'scope' => 'openid email profile',
        'state' => $_SESSION['oauth_state'], 'prompt' => 'select_account',
    ]);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk · Café Setara</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script>
(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<script>
function togglePassword(){
  var i=document.getElementById('password');
  var c=document.getElementById('showPass');
  i.type=c.checked?'text':'password';
}
function toggleMata(){
  var i=document.getElementById('password');
  i.type=i.type==='password'?'text':'password';
}
</script>
</head>
<body class="halaman-auth">
<section class="auth-kiri">
  <a class="logo" href="<?= e(base_url('login.php')) ?>">
    <span class="logo-mark" data-lucide="coffee"></span>
    <span class="logo-teks"><strong>Café Setara</strong><small>RUANG KELOLA KEDAI</small></span>
  </a>
  <h1 class="auth-judul">Racikan yang hangat.<br>Operasional yang tertata.</h1>
  <p class="auth-desk">Ruang kerja untuk menjaga setiap menu, setiap bahan, dan setiap keputusan Café Setara.</p>
  <p class="auth-caption">Café Setara / Ruang kelola kedai / 2026</p>
</section>
<section class="auth-kanan">
  <div class="auth-form">
    <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
      <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
        <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
      </button>
    </div>
    <a class="kembali" href="<?= e(base_url('login.php')) ?>">← Kembali ke login</a>
    <p class="eyebrow">SELAMAT DATANG KEMBALI</p>
    <h1>Masuk ke ruang kerja.</h1>
    <p class="sub">Gunakan akun yang sudah disetujui pemilik.</p>

    <?php if ($googleError): ?><div class="kotak-status merah"><strong>Tidak dapat masuk dengan Google</strong><?= e($googleError) ?></div><?php endif; ?>
    <?php if ($statusBox): ?><div class="kotak-status <?= e($statusBox['kelas']) ?>"><strong><?= e($statusBox['judul']) ?></strong><?= e($statusBox['isi']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="kotak-status merah"><strong>Tidak dapat masuk</strong><?= e($error) ?></div><?php endif; ?>
    <?php tampilkan_flash(); ?>

    <form method="post" action="<?= e(base_url('login.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="form" value="login">
      <div class="form-group"><label for="email">Email</label>
        <div class="input-ikon"><input id="email" name="email" type="email" required autocomplete="username" placeholder="nama@setara.coffee"><span class="ikon-kanan" data-lucide="mail"></span></div>
      </div>
      <div class="form-group"><label for="password">Password</label>
        <div class="input-ikon"><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••"><button class="ikon-kanan" type="button" onclick="toggleMata()" data-lucide="eye" aria-label="Tampilkan password"></button></div>
      </div>
      <div class="baris-opsi">
        <label class="cek-baris"><input type="checkbox" name="ingat" value="1"> Ingat perangkat ini</label>
        <a href="#" onclick="document.getElementById('modalLupa').classList.add('tampil');return false;">Lupa password?</a>
      </div>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:18px">→ Masuk</button>
    </form>

    <div class="pemisah">atau</div>
    <?php if ($googleUrl): ?>
      <a class="btn btn-secondary btn-block" href="<?= e($googleUrl) ?>"><svg class="google-ikon" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.3 13.2 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.6c0-1.6-.1-3.1-.4-4.6H24v9.1h12.6c-.5 2.9-2.2 5.4-4.8 7l7.7 6c4.5-4.2 7-10.3 7-17.5z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.8-2.9-.8-4.7s.3-3.3.8-4.7l-7.8-6.1C1 16.1 0 19.9 0 24s1 7.9 2.6 11.2l7.8-6.5z"/><path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.5l-7.7-6c-2.1 1.4-4.8 2.3-7.5 2.3-6.3 0-11.7-3.7-13.6-9.1l-7.8 6.5C6.5 42.6 14.6 48 24 48z"/></svg> Lanjutkan dengan Google</a>
    <?php else: ?>
      <span class="tooltip-wrap btn btn-secondary btn-block" style="opacity:.6;cursor:not-allowed" title="Belum dikonfigurasi"><span class="tooltip">Belum dikonfigurasi</span><svg class="google-ikon" viewBox="0 0 48 48"><path fill="#4285F4" d="M45 24c0-1.5-.1-2.9-.4-4.3H24v8.5h11.8c-.5 2.7-2.1 5-4.5 6.6v5.5h7.3c4.3-4 6.4-9.7 6.4-16.3z"/><path fill="#34A853" d="M24 46c6.1 0 11.2-2 14.9-5.4l-7.3-5.6c-2 1.4-4.6 2.2-7.6 2.2-5.8 0-10.8-3.9-12.6-9.1H4v5.7C7.8 41.2 15.4 46 24 46z"/><path fill="#FBBC05" d="M11.4 27.1c-.4-1.3-.7-2.6-.7-4.1s.3-2.8.7-4.1V13.2H4C1.5 17 0 21.4 0 26s1.5 9 4 12.8l7.4-5.7z"/><path fill="#EA4335" d="M24 10.2c3.3 0 6.3 1.1 8.6 3.3l6.5-6.5C35.1 3 30 1 24 1 15.4 1 7.8 5.8 4 13.2l7.4 5.7C13.2 14.1 18.2 10.2 24 10.2z"/></svg> Lanjutkan dengan Google</span>
    <?php endif; ?>

    <p class="auth-foot">Belum punya akun? <a href="<?= e(base_url('register.php')) ?>">Daftar akun →</a></p>
  </div>
</section>

<div class="modal" id="modalLupa">
  <div class="modal-kartu"><h2>Lupa password?</h2><p class="muted">Hubungi pemilik untuk reset password. Pemilik dapat mengatur ulang password di menu <strong>Pengguna &amp; keamanan</strong>.</p>
    <div class="form-actions"><button class="btn btn-primary" type="button" onclick="document.getElementById('modalLupa').classList.remove('tampil')">Mengerti</button></div></div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){
  if(window.lucide)lucide.createIcons();
  var t=document.getElementById('themeToggle');
  if(t)t.addEventListener('click',function(){
    var s=document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light';
    var n=s==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',n);
    try{localStorage.setItem('setara_theme',n);}catch(e){}
  });
});</script>
</body></html>
