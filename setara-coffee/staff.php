<?php
require_once __DIR__ . '/config/config.php';

$staffUser = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laman Staff · Café Setara</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script>
(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
</head>
<body class="halaman-auth">
<section class="auth-kiri">
  <a class="logo" href="<?= e(base_url('staff.php')) ?>">
    <span class="logo-mark" data-lucide="coffee"></span>
    <span class="logo-teks"><strong>Café Setara</strong><small>STAFF · RUANG KEDAI</small></span>
  </a>
  <h1 class="auth-judul">Laman Staff.<br>Ruang kerja yang tertata.</h1>
  <p class="auth-desk">Halaman khusus staff Café Setara. Masuk sebagai staff untuk membuka ruang kerja: menu, resep, stok, kasir, dan laporan.</p>
  <p class="auth-caption">Café Setara / Laman Staff / 2026</p>
</section>
<section class="auth-kanan">
  <div class="auth-form">
    <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
      <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
        <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
      </button>
    </div>
    <p class="eyebrow">STAFF · RUANG KEDAI</p>
    <h1>Selamat datang di Café Setara</h1>
    <?php if ($staffUser): ?>
      <p class="sub">Halo, <strong><?= e($staffUser['nama']) ?></strong>. Akun staff Anda sudah siap dipakai.</p>
      <a class="btn btn-secondary btn-block" href="<?= e(base_url($staffUser['role'] === 'kasir' ? 'kasir/index.php' : 'dashboard.php')) ?>">→ Ke Dashboard</a>
    <?php else: ?>
      <p class="sub">Masuk ke aplikasi staff untuk membuka ruang kerja Café Setara.</p>
      <a class="btn btn-primary btn-block" href="<?= e(base_url('login.php')) ?>">→ Masuk ke Aplikasi</a>
    <?php endif; ?>
  </div>
</section>
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
</body>
</html>
