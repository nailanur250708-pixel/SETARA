<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout_footer.php';

/** Header + sidebar untuk role owner. */
function layout_header(string $judul, string $subjudul, string $aktif = ''): void
{
    require_role(['owner']);
    $role = $_SESSION['user']['role'];
    $nama = $_SESSION['user']['nama'];
    $eyebrow = $role === 'owner' ? 'RUANG KERJA · PEMILIK' : 'RUANG KERWA · KASIR';
    $kritis = bahan_kritis();
    $jumlahKritis = count($kritis);
    $menu = [
        'dashboard' => ['Dashboard', 'dashboard.php', 'layout-dashboard'],
        'kasir'     => ['Kasir / POS', 'kasir/index.php', 'shopping-cart'],
        'menu'      => ['Manajemen menu', 'menu/index.php', 'book-open'],
        'resep'     => ['Resep & HPP', 'resep/index.php', 'flask-conical'],
        'bahan'     => ['Bahan & stok', 'bahan/index.php', 'boxes'],
        'laporan'   => ['Laporan', 'laporan/index.php', 'chart-column'],
        'pengguna'  => ['Pengguna & keamanan', 'pengguna/index.php', 'shield-check'],
    ];
    if ($role !== 'owner') {
        // Kasir boleh mengakses Kasir/POS; hanya Kelola akun yang khusus pemilik.
        unset($menu['pengguna']);
    }
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($judul) ?> · Café Setara</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script>
(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
</head>
<body class="halaman-app">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="logo" href="<?= e(base_url('dashboard.php')) ?>">
      <span class="logo-mark" data-lucide="coffee"></span>
      <span class="logo-teks"><strong>Café Setara</strong><small>RUANG KELOLA KEDAI</small></span>
    </a>
    <p class="sidebar-eyebrow"><?= e($eyebrow) ?></p>
    <nav class="sidebar-nav">
      <?php foreach ($menu as $key => [$label, $path, $icon]): ?>
        <a href="<?= e(base_url($path)) ?>" class="nav-item <?= $aktif === $key ? 'aktif' : '' ?>">
          <i data-lucide="<?= e($icon) ?>"></i><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-note">
      <i data-lucide="coffee"></i>
      <div><strong>Rapi di balik setiap cangkir.</strong><p>Menu, bahan, dan angka dalam satu ruang kerja.</p></div>
    </div>
    <div class="sidebar-bottom">
      <div class="profil">
        <span class="avatar"><?= e(inisial($nama)) ?></span>
        <span><strong><?= e($nama) ?></strong><small><?= e(role_label($role)) ?></small></span>
      </div>
      <form method="post" action="<?= e(base_url('logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-sm btn-block" type="submit"><i data-lucide="log-out"></i> Keluar</button>
      </form>
    </div>
  </aside>
  <div class="overlay" id="overlay"></div>
  <main class="konten">
    <header class="topbar">
      <div class="topbar-kiri">
        <button class="hamburger" id="hamburger" aria-label="Buka menu"><i data-lucide="menu"></i></button>
        <div class="breadcrumb">Café Setara <span>/</span> <strong><?= e($judul) ?></strong></div>
      </div>
      <div class="topbar-kanan">
        <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
          <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
          <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
        </button>
        <span class="tanggal-hari"><?= e(tanggal_indonesia(date('Y-m-d'))) ?></span>
        <a class="ikon-lonceng" href="<?= e(base_url('bahan/index.php')) ?>" title="<?= $jumlahKritis ?> bahan kritis">
          <i data-lucide="bell"></i><?php if ($jumlahKritis): ?><span class="badge-lonceng"><?= $jumlahKritis ?></span><?php endif; ?>
        </a>
        <span class="badge-peran"><?= e(role_label($role)) ?></span>
      </div>
    </header>
    <div class="konten-dalam">
      <?php tampilkan_flash(); ?>
      <div class="judul-halaman">
        <div><h1><?= e($judul) ?></h1><p><?= e($subjudul) ?></p></div>
      </div>
<?php
}
