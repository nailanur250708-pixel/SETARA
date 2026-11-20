<?php
require_once __DIR__ . '/auth.php';

/** Topbar POS. $tab = kasir|pembayaran|struk */
function kasir_header(string $tab, bool $adaItem = false): void
{
    require_role(['kasir', 'owner']);
    $role = $_SESSION['user']['role'];
    $nama = $_SESSION['user']['nama'];
    $tanggalPendek = tanggal_indonesia(date('Y-m-d'), false);
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ucfirst($tab) ?> · Café Setara</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script>
(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
</head>
<body class="halaman-kasir">
<header class="kasir-topbar">
  <div class="kasir-topbar-kiri">
    <a class="logo" href="<?= e(base_url(in_array($role, ['owner'], true) ? 'dashboard.php' : 'kasir/index.php')) ?>">
      <span class="logo-mark" data-lucide="coffee"></span>
      <span class="logo-teks"><strong>Café Setara</strong><small>RUANG KELOLA KEDAI</small></span>
    </a>
    <?php if (in_array($role, ['owner'], true)): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(base_url('dashboard.php')) ?>"><i data-lucide="arrow-left"></i> Dashboard</a>
    <?php endif; ?>
  </div>
  <nav class="kasir-tabs">
    <a href="<?= e(base_url('kasir/index.php')) ?>" class="kasir-tab <?= $tab === 'kasir' ? 'aktif' : '' ?>"><i data-lucide="shopping-cart"></i> Kasir/POS</a>
    <a href="<?= $adaItem ? e(base_url('kasir/pembayaran.php')) : '#' ?>" class="kasir-tab <?= $tab === 'pembayaran' ? 'aktif' : '' ?> <?= $adaItem ? '' : 'nonaktif' ?>" <?= $adaItem ? '' : 'aria-disabled="true" title="Tambahkan item terlebih dahulu"' ?>><i data-lucide="credit-card"></i> Pembayaran</a>
    <a href="<?= e(base_url('kasir/struk.php')) ?>" class="kasir-tab <?= $tab === 'struk' ? 'aktif' : '' ?>"><i data-lucide="receipt"></i> Struk</a>
  </nav>
  <div class="kasir-topbar-kanan">
    <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
      <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
    </button>
    <span class="badge-peran"><?= e($nama) ?> · <?= e(role_label($role)) ?></span>
    <span class="tanggal-hari"><?= e($tanggalPendek) ?></span>
    <form method="post" action="<?= e(base_url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><i data-lucide="log-out"></i> Keluar</button></form>
  </div>
</header>
<div class="kasir-konten">
<?php tampilkan_flash(); ?>
<?php
}

function kasir_footer(): void
{
    ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){ if (window.lucide) lucide.createIcons();
  document.querySelectorAll('.flash-close').forEach(function(b){ b.addEventListener('click', function(){ b.parentElement.remove(); }); });
  var tema=document.getElementById('themeToggle');
  if(tema) tema.addEventListener('click',function(){
    var s=document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light';
    var n=s==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',n);
    try{localStorage.setItem('setara_theme',n);}catch(e){}
    tema.setAttribute('aria-label',n==='dark'?'Ganti ke mode terang':'Ganti ke mode gelap');
  });
});
</script>
</body>
</html>
<?php
}
