<?php
require_once __DIR__ . '/includes/auth.php';

$peran     = ($_GET['peran'] ?? '') === 'pelanggan' ? 'pelanggan' : 'admin';
$redirect  = $_GET['redirect'] ?? '';
$redirect  = preg_match('/^[a-z0-9_.-]+\.php$/i', $redirect) ? $redirect : '';
$errors    = [];

if (sudah_login()) {
    $tujuan = peran_login() === 'admin' ? 'app.php' : 'menu.php';
    header('Location: ' . ($redirect ?: $tujuan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peran    = ($_POST['peran'] ?? '') === 'pelanggan' ? 'pelanggan' : 'admin';
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $redirect = preg_match('/^[a-z0-9_.-]+\.php$/i', $_POST['redirect'] ?? '') ? $_POST['redirect'] : '';

    $errors = coba_login($username, $password, $peran);
    if (!$errors) {
        $tujuan = $redirect ?: (peran_login() === 'admin' ? 'app.php' : 'menu.php');
        header('Location: ' . $tujuan);
        exit;
    }
}

$judul = $peran === 'admin' ? 'Masuk Admin / Kasir' : 'Masuk Pelanggan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $judul ?> — Café Setara</title>
  <link rel="stylesheet" href="css/tokens.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
</head>
<body class="login-page">

<main class="login-wrap">
  <div class="login-card card card--elevated">
    <a class="navbar__logo" href="index.php">
      <span class="navbar__logo-mark" aria-hidden="true">CS</span>
      Café Setara
    </a>

    <h1 class="login-card__title"><?= $judul ?></h1>
    <p class="login-card__subtitle">Silakan masuk untuk melanjutkan.</p>

    <?php if ($errors): ?>
      <div class="notifikasi notifikasi--error mb-6" role="alert">
        <p><?= htmlspecialchars($errors[0], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    <?php endif; ?>

    <form class="stack mt-6" action="login.php" method="post">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label class="field__label" for="peran">Masuk sebagai</label>
        <select class="select" id="peran" name="peran">
          <option value="admin" <?= $peran === 'admin' ? 'selected' : '' ?>>Admin / Kasir</option>
          <option value="pelanggan" <?= $peran === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="username">Username</label>
        <input class="input" type="text" id="username" name="username" required autocomplete="username" placeholder="admin">
      </div>

      <div class="field">
        <label class="field__label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>

      <button class="btn btn--primary btn--block" type="submit">Masuk</button>
    </form>

    <div class="login-hint">
      <p class="caption text-muted">Akun demo (session saja):</p>
      <ul>
        <li><code>admin</code> / <code>admin123</code> — Admin</li>
        <li><code>kasir</code> / <code>kasir123</code> — Kasir</li>
        <li><code>pelanggan</code> / <code>pelanggan123</code> — Pelanggan</li>
      </ul>
    </div>

    <a class="navbar__link" href="index.php">← Kembali ke beranda</a>
  </div>
</main>

</body>
</html>
