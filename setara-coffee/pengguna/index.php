<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout_owner.php';

require_role(['owner']);

$pdo = db();

/* ------------------------------------ Data akun */
$semua = $pdo->query('SELECT id, username, nama, nama_lengkap, email, peran, role, status_persetujuan, aktif, terakhir_login FROM users ORDER BY FIELD(role,"owner","kasir"), id')->fetchAll();

$jumlahAkun = count($semua);
$jumlahOwner = 0;
$jumlahKasir = 0;
foreach ($semua as $u) {
    if ($u['role'] === 'owner') $jumlahOwner++;
    elseif ($u['role'] === 'kasir') $jumlahKasir++;
}

$userIdSaya = (int)($_SESSION['user']['id'] ?? 0);

/* Akun pending (diajukan pengguna) */
$pending = [];
foreach ($semua as $u) {
    if ($u['status_persetujuan'] === 'pending') $pending[] = $u;
}
$jumlahPending = count($pending);

/* Nama tampilan: nama_lengkap atau nama */
function nama_tampil(array $u): string
{
    return trim((string)($u['nama_lengkap'] ?? '')) !== '' ? $u['nama_lengkap'] : (string)($u['nama'] ?? '');
}

/* Peran yang diajukan: kolom peran lama lebih dulu, fallback role */
function peran_diajukan(array $u): string
{
    $peran = strtolower((string)($u['peran'] ?? ''));
    if ($peran === 'admin') return 'kasir';
    if ($peran === 'owner') return 'owner';
    $role = strtolower((string)($u['role'] ?? ''));
    return in_array($role, ['owner', 'kasir'], true) ? $role : 'kasir';
}

/* Sesi saat ini */
$waktuLogin = $_SESSION['login_time'] ?? null;
if ($waktuLogin === null) {
    $st = $pdo->prepare('SELECT terakhir_login FROM users WHERE id=?');
    $st->execute([$userIdSaya]);
    $waktuLogin = $st->fetchColumn() ?: date('Y-m-d H:i:s');
}
$loginTeks = is_numeric($waktuLogin)
    ? tanggal_indonesia(date('Y-m-d H:i:s', (int)$waktuLogin)) . ' pukul ' . date('H:i', (int)$waktuLogin)
    : tanggal_indonesia((string)$waktuLogin) . ' pukul ' . date('H:i', strtotime((string)$waktuLogin));

/* Matriks hak akses */
$matriks = [
    ['Dashboard', true, false],
    ['Kasir / POS & pembayaran', true, true],
    ['Lihat stok', true, false],
    ['Kelola menu, resep, dan HPP', true, false],
    ['Kelola bahan, stok, dan pembelian', true, false],
    ['Laporan & ekspor PDF', true, false],
    ['Kelola akun, peran, dan persetujuan', true, false],
];

layout_header('Pengguna & keamanan', 'Akses yang tepat untuk setiap pelayanan. Persetujuan akun berada pada pemilik.', 'pengguna');
?>

<div class="baris-atas mb16">
  <div></div>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('modalUndang').classList.add('tampil')">✉ Undang pengguna</button>
</div>

<?php if ($jumlahPending > 0): ?>
  <div class="banner-kuning">
    <strong><?= $jumlahPending ?> akun menunggu persetujuan</strong>
    <div style="margin-top:8px">
      <?php foreach ($pending as $p): ?>
        <div class="baris-atas" style="padding:8px 0;border-top:1px solid rgba(138,90,0,.18)">
          <div>
            <?= e(nama_tampil($p)) ?> &middot; <?= e($p['email']) ?>
            <span class="badge badge-krem" style="margin-left:6px"><?= e(role_label(peran_diajukan($p))) ?></span>
          </div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <form method="post" action="<?= e(base_url('pengguna/setujui.php')) ?>" onsubmit="return confirm('Tolak akun <?= e(nama_tampil($p)) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="aksi" value="rejected">
              <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
            </form>
            <form method="post" action="<?= e(base_url('pengguna/setujui.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="aksi" value="approved">
              <button type="submit" class="btn btn-primary btn-sm">✓ Setujui <?= e(role_label(peran_diajukan($p))) ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<section class="kartu">
  <div class="kartu-head">
    <div>
      <h2>Akun ruang kerja</h2>
      <p><?= $jumlahAkun ?> akun operasional: Pemilik (<?= $jumlahOwner ?>), Kasir (<?= $jumlahKasir ?>)</p>
    </div>
  </div>
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama / email</th>
          <th>Peran</th>
          <th>Status</th>
          <th>Akses terakhir</th>
          <th class="kanan">Tindakan</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($semua as $u): ?>
        <?php
          $adalahSaya = ((int)$u['id'] === $userIdSaya);
          $status = $u['status_persetujuan'];
          if ($status === 'approved') {
              $badge = '<span class="badge badge-hijau">Aktif</span>';
          } elseif ($status === 'pending') {
              $badge = '<span class="badge badge-kuning">Pending</span>';
          } else {
              $badge = '<span class="badge badge-abu">Nonaktif</span>';
          }
          $akses = !empty($u['terakhir_login'])
              ? e(tanggal_indonesia($u['terakhir_login'], false) . ' ' . date('H:i', strtotime($u['terakhir_login'])))
              : '<span class="muted">—</span>';
        ?>
        <tr>
          <td>
            <strong style="color:var(--teks)"><?= e(nama_tampil($u)) ?></strong>
            <?php if ($adalahSaya): ?><span class="badge badge-krem" style="margin-left:6px">Akun Anda</span><?php endif; ?>
            <div class="small muted"><?= e($u['email']) ?></div>
          </td>
          <td><span class="badge badge-krem"><?= e(role_label($u['role'])) ?></span></td>
          <td><?= $badge ?></td>
          <td><?= $akses ?></td>
          <td class="kanan">
            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
              <?php if ($u['role'] !== 'owner'): ?>
                <form method="post" action="<?= e(base_url('pengguna/simpan.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form" value="peran">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <select name="role" aria-label="Edit peran <?= e(nama_tampil($u)) ?>" style="height:36px;width:auto;padding:0 8px;font-size:13px">
                    <option value="kasir" <?= $u['role'] === 'kasir' ? 'selected' : '' ?>>Kasir</option>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Edit peran</button>
                </form>
              <?php else: ?>
                <span class="badge badge-krem">Peran pemilik</span>
              <?php endif; ?>

              <?php if ($adalahSaya): ?>
                <span class="small muted" style="align-self:center">Akun Anda</span>
              <?php else: ?>
                <form method="post" action="<?= e(base_url('pengguna/setujui.php')) ?>" onsubmit="return confirm('<?= $status === 'rejected' ? 'Aktifkan' : 'Nonaktifkan' ?> akun <?= e(nama_tampil($u)) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="aksi" value="<?= $status === 'rejected' ? 'approved' : 'rejected' ?>">
                  <button type="submit" class="btn <?= $status === 'rejected' ? 'btn-primary' : 'btn-danger' ?> btn-sm"><?= $status === 'rejected' ? 'Aktifkan' : 'Nonaktifkan' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-head">
    <div>
      <h2>Matriks hak akses</h2>
      <p>Rancangan akses &middot; ✓ diizinkan, — tidak diizinkan</p>
    </div>
  </div>
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr><th>Kemampuan</th><th class="tengah">Pemilik</th><th class="tengah">Kasir</th></tr>
      </thead>
      <tbody>
      <?php foreach ($matriks as [$label, $owner, $kasir]): ?>
        <tr>
          <td><?= e($label) ?></td>
          <?php foreach ([$owner, $kasir] as $boleh): ?>
            <td class="tengah"><?= $boleh ? '<span style="color:var(--sage-teks);font-weight:700">✓</span>' : '<span class="muted">—</span>' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-head">
    <div>
      <h2>Sesi saat ini</h2>
      <p>Browser desktop &middot; <?= e($_SESSION['user']['nama'] ?? '') ?>, Aktif sejak <?= e($loginTeks) ?></p>
    </div>
    <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('modalKeluar').classList.add('tampil')">⎋ Keluar dari perangkat ini</button>
  </div>
  <div class="garis-info">
    Gunakan HTTPS, hash password, validasi sesi, pembatasan sesi, dan periksa peran pada setiap aksi.
  </div>
</section>

<!-- Modal: undang pengguna -->
<div class="modal" id="modalUndang">
  <div class="modal-kartu">
    <h2>Undang pengguna</h2>
    <p class="muted small" style="margin-bottom:16px">Akun langsung disetujui. Pengguna wajib mengganti password pada login pertama.</p>
    <form method="post" action="<?= e(base_url('pengguna/simpan.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="undang">
      <div class="form-group"><label for="undangNama">Nama</label>
        <input id="undangNama" name="nama" type="text" required placeholder="Nama pengguna">
      </div>
      <div class="form-group"><label for="undangEmail">Email</label>
        <input id="undangEmail" name="email" type="email" required placeholder="nama@setara.coffee">
      </div>
      <div class="form-group"><label for="undangPeran">Peran</label>
        <select id="undangPeran" name="peran" required>
          <option value="kasir">Kasir</option>
        </select>
      </div>
      <div class="form-group"><label for="undangPassword">Password sementara</label>
        <input id="undangPassword" name="password" type="text" required minlength="8" placeholder="Minimal 8 karakter">
        <p class="small muted" style="margin-top:6px">Password sementara ditampilkan sekali setelah submit. Sampaikan kepada pengguna secara privat.</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">✉ Undang</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalUndang').classList.remove('tampil')">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: konfirmasi keluar -->
<div class="modal" id="modalKeluar">
  <div class="modal-kartu">
    <h2>Keluar dari Café Setara?</h2>
    <p class="muted" style="margin:8px 0 18px">Anda perlu masuk kembali untuk menggunakan ruang kerja. Pastikan pembukuan yang masih terbuka sudah disimpan.</p>
    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalKeluar').classList.remove('tampil')">Tetap di sini</button>
      <form method="post" action="<?= e(base_url('logout.php')) ?>" id="formLogoutModal">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">⎋ Ya, keluar</button>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.modal').forEach(function (m) {
  m.addEventListener('click', function (ev) {
    if (ev.target === m) m.classList.remove('tampil');
  });
});
</script>

<?php
layout_footer();
