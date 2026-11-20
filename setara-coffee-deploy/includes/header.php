<?php
/** Header +Navbar bersama. Variabel $active: home|app|kasir|menu */
$active = $active ?? '';
require_once __DIR__ . '/auth.php';
?>
<script>
/* Tema: atur <html data-theme="dark|light"> dari localStorage; jika belum
   ada preferensi, ikuti prefsistem sistem (prefers-color-scheme). */
(function(){try{var t=localStorage.getItem('setara_theme');if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<header class="navbar">
  <div class="container navbar__inner">
    <a class="navbar__logo" href="index.php">
      <span class="navbar__logo-mark" aria-hidden="true">CS</span>
      Café Setara
    </a>
    <nav class="navbar__menu" aria-label="Navigasi utama">
      <a class="navbar__link" href="index.php#fitur">Fitur</a>
      <a class="navbar__link" href="index.php#alur">Alur kerja</a>
      <a class="navbar__link" href="menu.php" <?= $active === 'menu' ? 'aria-current="page"' : '' ?>>Menu</a>
      <a class="navbar__link" href="index.php#faq">FAQ</a>
    </nav>
    <div class="navbar__actions">
      <button class="theme-toggle" type="button" id="themeToggle" aria-label="Ganti mode tampilan" title="Ganti mode terang / gelap">
        <svg class="theme-icon theme-icon--matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="theme-icon theme-icon--bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
      </button>
      <?php if (sudah_login()): ?>
        <span class="badge <?= peran_login() === 'admin' ? 'badge--accent' : 'badge--success' ?>">
          <?= htmlspecialchars(nama_login(), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a class="btn btn--ghost btn--sm" href="logout.php">Keluar</a>
        <?php if (peran_login() === 'admin'): ?>
          <a class="btn btn--primary btn--sm" href="app.php">Dashboard</a>
        <?php endif; ?>
      <?php else: ?>
        <a class="btn btn--secondary btn--sm" href="login.php?peran=pelanggan">Masuk Pelanggan</a>
        <a class="btn btn--primary" href="login.php?peran=admin">Masuk aplikasi</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<script>
/* Handler tombol tema: ganti data-theme + simpan preferensi di localStorage. */
(function(){var t=document.getElementById('themeToggle');if(t)t.addEventListener('click',function(){
  var s=document.documentElement.getAttribute('data-theme')==='dark'?'dark':'light';
  var n=s==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',n);
  try{localStorage.setItem('setara_theme',n);}catch(e){}
});})();
</script>
