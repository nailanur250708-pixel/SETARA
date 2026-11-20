<?php
/**
 * menu/index.php -- Manajemen menu, Diskon & promo, Galeri.
 * Akses: owner.
 */
require_once __DIR__ . '/../includes/layout_owner.php';
require_once __DIR__ . '/../includes/upload_menu_foto.php';

$tab = (string)($_GET['tab'] ?? 'menu');
if (!in_array($tab, ['menu', 'promo', 'galeri'], true)) {
    $tab = 'menu';
}
$aksi = (string)($_GET['aksi'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

$tabUrl = static function (string $t) use ($aksi, $id): string {
    $url = 'menu/index.php?tab=' . $t;
    if ($aksi !== '') {
        $url .= '&aksi=' . rawurlencode($aksi);
        if ($id > 0) $url .= '&id=' . $id;
    }
    return $url;
};

layout_header(
    'Manajemen menu',
    'Jaga kategori, harga jual, dan ketersediaan racikan kedai.',
    'menu'
);

$self = 'menu/index.php';
?>
<div class="baris-atas" style="margin-bottom:18px">
  <div class="tabs" style="margin-bottom:0;flex:1">
    <a class="tab <?= $tab === 'menu' ? 'aktif' : '' ?>" href="<?= e(base_url($self . '?tab=menu')) ?>">Menu</a>
    <a class="tab <?= $tab === 'promo' ? 'aktif' : '' ?>" href="<?= e(base_url($self . '?tab=promo')) ?>">Diskon &amp; promo</a>
    <a class="tab <?= $tab === 'galeri' ? 'aktif' : '' ?>" href="<?= e(base_url($self . '?tab=galeri')) ?>">Galeri</a>
  </div>
  <?php if ($tab === 'menu'): ?>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url($self . '?tab=menu&aksi=tambah')) ?>"><i data-lucide="plus"></i> Tambah menu</a>
  <?php endif; ?>
</div>

<?php if ($tab === 'menu'): ?>
<?php
/* ---------------------------------- TAB MENU ---------------------------------- */
$cari   = trim((string)($_GET['cari'] ?? ''));
$kat    = (string)($_GET['kategori'] ?? 'Semua kategori');
$status = (string)($_GET['status'] ?? 'Aktif'); // Aktif | Diarsipkan | Semua
if (!in_array($kat, ['Semua kategori', 'Kopi', 'Non-kopi'], true)) $kat = 'Semua kategori';
if (!in_array($status, ['Aktif', 'Diarsipkan', 'Semua'], true)) $status = 'Aktif';

$where  = [];
$params = [];
if ($cari !== '') {
    $where[] = 'nama LIKE ?';
    $params[] = '%' . $cari . '%';
}
if ($kat !== 'Semua kategori') {
    $where[] = 'kategori=?';
    $params[] = $kat;
}
if ($status === 'Aktif') {
    $where[] = 'diarsipkan=0';
} elseif ($status === 'Diarsipkan') {
    $where[] = 'diarsipkan=1';
}
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmtCount = db()->prepare("SELECT COUNT(*) FROM menu $sqlWhere");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$perHal = 10;
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$jmlHal = max(1, (int)ceil($total / $perHal));
if ($hal > $jmlHal) $hal = $jmlHal;
$offset = ($hal - 1) * $perHal;

$stmt = db()->prepare("SELECT * FROM menu $sqlWhere ORDER BY urutan DESC, id DESC LIMIT $perHal OFFSET $offset");
$stmt->execute($params);
$menus = $stmt->fetchAll();

$aktifCount = (int)db()->query('SELECT COUNT(*) FROM menu WHERE diarsipkan=0')->fetchColumn();

$paramQuery = static function (array $extra = []) use ($cari, $kat, $status): string {
    $q = [];
    if ($cari !== '') $q['cari'] = $cari;
    if ($kat !== 'Semua kategori') $q['kategori'] = $kat;
    if ($status !== 'Aktif') $q['status'] = $status;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return $q ? ('&' . http_build_query($q)) : '';
};
?>

<div class="form-grid" style="grid-template-columns:minmax(0,1fr) 340px;align-items:start">
  <div>
    <section class="kartu">
      <div class="kartu-head">
        <div><h2 style="font-family:'Fraunces',serif;font-size:20px;margin:0">Daftar menu</h2>
          <p style="color:var(--teks-muted);margin:3px 0 0"><?= $aktifCount ?> menu aktif · HPP dihitung dari resep</p></div>
      </div>
      <form class="filter-bar" method="get" action="<?= e(base_url($self)) ?>" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="hidden" name="tab" value="menu">
        <div class="input-ikon" style="flex:1;min-width:180px">
          <input type="text" name="cari" placeholder="Cari nama menu…" value="<?= e($cari) ?>">
        </div>
        <select name="kategori" style="width:auto;min-width:150px">
          <option value="Semua kategori" <?= $kat === 'Semua kategori' ? 'selected' : '' ?>>Semua kategori</option>
          <option value="Kopi" <?= $kat === 'Kopi' ? 'selected' : '' ?>>Kopi</option>
          <option value="Non-kopi" <?= $kat === 'Non-kopi' ? 'selected' : '' ?>>Non-kopi</option>
        </select>
        <select name="status" style="width:auto;min-width:130px">
          <option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="Diarsipkan" <?= $status === 'Diarsipkan' ? 'selected' : '' ?>>Diarsipkan</option>
          <option value="Semua" <?= $status === 'Semua' ? 'selected' : '' ?>>Semua</option>
        </select>
        <button class="btn btn-secondary btn-sm" type="submit">Terapkan</button>
      </form>

      <div class="tabel-wrap">
        <table class="tabel">
          <thead><tr>
            <th>Menu</th><th>Kategori</th><th>Harga jual</th><th>HPP</th><th>Margin</th><th>Foto</th><th>Urutan</th><th>Status</th><th>Tindakan</th>
          </tr></thead>
          <tbody>
          <?php if (!$menus): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--teks-muted);padding:28px">Tidak ada menu yang cocok.</td></tr>
          <?php else: foreach ($menus as $m): ?>
            <?php
              $hpp = hpp_menu((int)$m['id']);
              $harga = (float)$m['harga'];
              $margin = $harga > 0 ? round((($harga - $hpp) / $harga) * 100) : 0;
            ?>
            <tr>
              <td>
                <strong><?= e($m['nama']) ?></strong>
                <?php if ($margin < 30): ?><span class="badge badge-kuning" style="margin-left:6px">Margin rendah</span><?php endif; ?>
              </td>
              <td><span class="badge badge-krem"><?= e($m['kategori']) ?></span></td>
              <td><?= e(rupiah($m['harga'])) ?></td>
              <td><?= e(rupiah($hpp)) ?></td>
              <td><?= e($margin) ?>%</td>
              <td class="tengah">
                <?php $ft = $m['foto_path'] ?: $m['gambar']; if ($ft !== ''): ?>
                  <?php $ftAbs = dirname(__DIR__) . '/' . $ft; ?>
                  <img class="menu-tbl-thumb" src="<?= e(base_url($ft)) ?>?v=<?= is_file($ftAbs) ? filemtime($ftAbs) : 0 ?>" alt="Foto <?= e($m['nama']) ?>" style="--fit:<?= e($m['foto_fit'] ?: 'cover') ?>;--posX:<?= (int)($m['foto_pos_x'] ?? 50) ?>%;--posY:<?= (int)($m['foto_pos_y'] ?? 50) ?>%;--zoom:<?= $m['foto_zoom'] ?>" loading="lazy">
                <?php else: ?>
                  <span class="menu-tbl-thumb menu-tbl-thumb--empty"><i data-lucide="image-off"></i></span>
                <?php endif; ?>
              </td>
              <td class="tengah">
                <form method="post" action="<?= e(base_url('menu/urutan.php')) ?>" style="display:inline-flex;gap:4px">
                  <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                  <button type="submit" name="aksi" value="naik" class="btn btn-ghost btn-sm" title="Urutan naik">↑</button>
                  <button type="submit" name="aksi" value="turun" class="btn btn-ghost btn-sm" title="Urutan turun">↓</button>
                </form>
              </td>
              <td>
                <?php if ((int)$m['diarsipkan'] === 1): ?>
                  <span class="badge badge-abu">Diarsipkan</span>
                <?php else: ?>
                  <span class="badge badge-hijau">Aktif</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url($self . '?tab=menu&aksi=detail&id=' . (int)$m['id'])) ?>">Detail</a>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url($self . '?tab=menu&aksi=edit&id=' . (int)$m['id'])) ?>">Edit</a>
                <a class="btn btn-ghost btn-sm" style="color:var(--merah-teks)" href="<?= e(base_url($self . '?tab=menu&aksi=arsip&id=' . (int)$m['id'])) ?>">Arsip</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="baris-opsi" style="margin-top:14px;color:var(--teks-muted);font-size:12.5px">
        <span>Menampilkan <?= $total ? ($offset + 1) : 0 ?>–<?= min($offset + $perHal, $total) ?> dari <?= $total ?> menu · Margin = (harga jual − HPP) ÷ harga jual, dibulatkan.</span>
      </div>
      <?php if ($jmlHal > 1): ?>
      <nav class="paginasi" aria-label="Paginasi menu">
        <?php for ($p = 1; $p <= $jmlHal; $p++): ?>
          <?php if ($p === $hal): ?>
            <span class="aktif"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= e(base_url($self . '?tab=menu' . $paramQuery(['hal' => $p]))) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
    </section>

    <div class="garis-info" style="margin-top:16px">
      <i data-lucide="info"></i> Tambah menu baru: gunakan form yang sama. Foto opsional. Menu baru perlu resep sebelum dijual.
    </div>
  </div>

  <!-- Panel kanan: tambah / edit / detail / arsip -->
  <aside>
  <?php
  $panelAktif = in_array($aksi, ['tambah', 'edit', 'detail', 'arsip'], true);
  if (!$panelAktif): ?>
    <section class="kartu">
      <div class="foto-placeholder" style="max-width:none;height:110px"><span><i data-lucide="coffee"></i><br>Pilih menu untuk melihat detail</span></div>
      <p style="color:var(--teks-muted);font-size:12.5px;margin:12px 0 0;text-align:center">Klik <strong>Detail</strong> atau <strong>Edit</strong> pada tabel untuk membuka panel ini.</p>
    </section>
  <?php else:
    $menuRow = null;
    if ($id > 0) {
      $st = db()->prepare('SELECT * FROM menu WHERE id=?');
      $st->execute([$id]);
      $menuRow = $st->fetch();
      if (!$menuRow) {
        flash('error', 'Menu tidak ditemukan.');
        $panelAktif = false;
      }
    }
    if ($panelAktif && ($aksi === 'tambah' || $menuRow)):
      $editMode = ($aksi === 'edit' || $aksi === 'detail' || $aksi === 'arsip');
      $namaVal = $editMode ? (string)$menuRow['nama'] : '';
      $katVal = $editMode ? (string)$menuRow['kategori'] : 'Kopi';
      $hargaVal = $editMode ? (float)$menuRow['harga'] : 0;
      $arsipVal = $editMode && (int)$menuRow['diarsipkan'] === 1;
      $fotoVal = $editMode ? ($menuRow['foto_path'] ?: ($menuRow['gambar'] ?: '')) : '';
      $hppVal = $editMode ? hpp_menu((int)$menuRow['id']) : 0.0;
      $marginVal = $hargaVal > 0 ? round((($hargaVal - $hppVal) / $hargaVal) * 100) : 0;
      $deskripsiVal  = $editMode ? (string) ($menuRow['deskripsi'] ?? '') : '';
      $tampilLandVal = $editMode ? (int) ($menuRow['tampil_landing'] ?? 1) : 1;
      $urutanVal     = $editMode ? (int) ($menuRow['urutan'] ?? 0) : 0;
      $fotoFitVal    = $editMode ? (string) ($menuRow['foto_fit'] ?: 'cover') : 'cover';
      $fotoPosXVal   = $editMode ? (int) ($menuRow['foto_pos_x'] ?? 50) : 50;
      $fotoPosYVal   = $editMode ? (int) ($menuRow['foto_pos_y'] ?? 50) : 50;
      $fotoZoomVal   = $editMode ? (float) ($menuRow['foto_zoom'] ?? 1) : 1.2;
  ?>
    <section class="kartu">
      <?php if ($aksi === 'arsip'): ?>
        <h2 style="font-family:'Fraunces',serif;font-size:20px;margin:0">Arsipkan <?= e($menuRow['nama']) ?>?</h2>
        <p style="color:var(--teks-muted);font-size:13px">Menu yang diarsipkan tidak lagi tersedia untuk dipesan, tetapi data dan riwayat transaksinya tetap tersimpan.</p>
        <div class="form-actions">
          <a class="btn btn-secondary btn-sm" href="<?= e(base_url($self . '?tab=menu')) ?>">Batal</a>
          <form method="post" action="<?= e(base_url('menu/arsip.php')) ?>" onsubmit="return confirm('Arsipkan menu ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$menuRow['id'] ?>">
            <button class="btn btn-primary btn-sm" type="submit">Ya, arsipkan</button>
          </form>
        </div>
        <?php
          $stUsed = db()->prepare('SELECT COUNT(*) FROM detail_transaksi WHERE menu_id=?');
          $stUsed->execute([(int)$menuRow['id']]);
          $bolehHapus = (int)$stUsed->fetchColumn() === 0;
        ?>
        <?php if ($bolehHapus): ?>
          <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:14px">
            <form method="post" action="<?= e(base_url('menu/hapus.php')) ?>" onsubmit="return confirm('Hapus permanen menu ini? Tindakan ini tidak dapat dibatalkan.')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$menuRow['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><i data-lucide="trash-2"></i> Hapus permanen</button>
            </form>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <h2 style="font-family:'Fraunces',serif;font-size:20px;margin:0">
          <?= $aksi === 'tambah' ? 'Tambah menu baru' : e($menuRow['nama']) ?>
        </h2>
        <p style="color:var(--teks-muted);font-size:12.5px;margin:3px 0 14px">
          <?php if ($editMode): ?><?= e($menuRow['kategori']) ?> · ID menu <?= (int)$menuRow['id'] ?>
          <?php else: ?>Isi detail menu di bawah ini.<?php endif; ?>
        </p>

        <div class="foto-block">
          <?php $fotoAda = $fotoVal !== '' && is_file(dirname(__DIR__) . '/' . $fotoVal); ?>
          <div class="foto-pratinjau-box" id="fotoPratinjauBox">
            <?php if ($fotoAda): ?>
              <img id="fotoPratinjau" src="<?= e(base_url($fotoVal)) ?>?v=<?= $fotoAda ? filemtime(dirname(__DIR__) . '/' . $fotoVal) : 0 ?>" style="--fit:<?= e($fotoFitVal) ?>;--posX:<?= (int)$fotoPosXVal ?>%;--posY:<?= (int)$fotoPosYVal ?>%;--zoom:<?= e($fotoZoomVal) ?>;" alt="Foto <?= e($namaVal) ?>">
            <?php else: ?>
              <img id="fotoPratinjau" src="" style="display:none" alt="Pratinjau foto">
              <span class="foto-placeholder" id="fotoPlaceholder"><i data-lucide="image"></i><br>Belum ada foto</span>
            <?php endif; ?>
          </div>
          <label for="foto" style="display:block;font-size:12.5px;color:var(--teks-muted);margin-bottom:4px">Foto menu (JPG/PNG/WebP, maksimal 5 MB)</label>
          <input type="file" name="foto" id="foto" accept="image/jpeg,image/png,image/webp" style="height:auto;padding:8px">
          <div style="display:flex;gap:8px;margin-top:6px;align-items:center">
            <input type="checkbox" id="hapusFoto" name="hapus_foto" value="1" style="width:18px;height:18px" <?= $fotoAda ? '' : 'disabled' ?>>
            <label for="hapusFoto" style="font-size:12px;color:var(--teks-muted)">Hapus foto saat disimpan</label>
          </div>
        </div>

        <form method="post" action="<?= e(base_url('menu/simpan.php')) ?>" enctype="multipart/form-data" style="margin-top:14px">
          <?= csrf_field() ?>
          <?php if ($editMode): ?><input type="hidden" name="id" value="<?= (int)$menuRow['id'] ?>"><?php endif; ?>
          <div class="form-group">
            <label for="nama">Nama menu</label>
            <input type="text" name="nama" id="nama" value="<?= e($namaVal) ?>" required>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="kategori">Kategori</label>
              <select name="kategori" id="kategori">
                <option value="Kopi" <?= $katVal === 'Kopi' ? 'selected' : '' ?>>Kopi</option>
                <option value="Non-kopi" <?= $katVal === 'Non-kopi' ? 'selected' : '' ?>>Non-kopi</option>
              </select>
            </div>
            <div class="form-group">
              <label for="harga">Harga jual (Rp)</label>
              <input type="number" name="harga" id="harga" min="1" step="1" value="<?= e($hargaVal) ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label for="deskripsi">Deskripsi (tampil di kartu menu &amp; landing) </label>
            <textarea name="deskripsi" id="deskripsi" rows="2" maxlength="255" placeholder="Contoh: Konten kopi yang lembut dipadukan dengan foam busa krem."><?= e($deskripsiVal) ?></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="status">Status menu</label>
              <select name="status" id="status">
                <option value="Aktif" <?= !$arsipVal ? 'selected' : '' ?>>Aktif</option>
                <option value="Diarsipkan" <?= $arsipVal ? 'selected' : '' ?>>Diarsipkan</option>
              </select>
            </div>
            <div class="form-group">
              <label>Tampil di landing: <input type="checkbox" name="tampil_landing" value="1" style="margin-left:8px" <?= $tampilLandVal ? 'checked' : '' ?>></label>
            </div>
            <div class="form-group">
              <label for="urutan">Urutan di landing (lebih besar = lebih atas)</label>
              <input type="number" name="urutan" id="urutan" min="0" step="1" value="<?= (int)$urutanVal ?>" style="height:36px">
            </div>
          </div>
          <div class="foto-setting-grid">
            <div class="form-group">
              <label>Mode foto: <select name="foto_fit" id="foto_fit" style="height:36px"><option value="cover" <?= ($fotoFitVal === 'cover') ? 'selected' : '' ?>>Penuh (cover)</option><option value="contain" <?= ($fotoFitVal === 'contain') ? 'selected' : '' ?>>Utuh (contain)</option></select></label>
            </div>
            <div class="form-group">
              <label for="foto_zoom">Zoom foto</label>
              <input type="range" id="foto_zoom" name="foto_zoom" min="1" max="3" step="0.1" value="<?= e($fotoZoomVal) ?>" style="vertical-align:middle"><span id="foto_zoom_val" style="font-size:12px;margin-left:6px"><?= e($fotoZoomVal) ?>x</span>
            </div>
            <div class="form-group">
              <label for="foto_pos_x">Posisi X (%)</label>
              <input type="range" id="foto_pos_x" name="foto_pos_x" min="0" max="100" value="<?= (int)$fotoPosXVal ?>" style="vertical-align:middle"><span id="foto_pos_x_val" style="font-size:12px;margin-left:6px"><?= (int)$fotoPosXVal ?>%</span>
            </div>
            <div class="form-group">
              <label for="foto_pos_y">Posisi Y (%)</label>
              <input type="range" id="foto_pos_y" name="foto_pos_y" min="0" max="100" value="<?= (int)$fotoPosYVal ?>" style="vertical-align:middle"><span id="foto_pos_y_val" style="font-size:12px;margin-left:6px"><?= (int)$fotoPosYVal ?>%</span>
            </div>
          </div>
          <?php if ($editMode): ?>
          <div class="garis-info">
            HPP <?= e(rupiah($hppVal)) ?> · Margin <?= e($marginVal) ?>%
            <?php if ($marginVal < 30): ?><br><span class="badge badge-kuning" style="margin-top:6px">Margin rendah</span><?php endif; ?>
            <br><a href="<?= e(base_url('resep/index.php?menu=' . (int)$menuRow['id'])) ?>">Edit resep →</a>
          </div>
          <?php endif; ?>
          <div class="form-actions">
            <?php if ($aksi !== 'detail'): ?>
              <button class="btn btn-primary btn-sm" type="submit">Simpan perubahan</button>
            <?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(base_url($self . '?tab=menu')) ?>">Batal</a>
          </div>
        </form>
        <script>
        (function () {
          var input = document.getElementById('foto');
          var img = document.getElementById('fotoPratinjau');
          var ph = document.getElementById('fotoPlaceholder');
          var zoom = document.getElementById('foto_zoom');
          var zoomVal = document.getElementById('foto_zoom_val');
          var posX = document.getElementById('foto_pos_x');
          var posXVal = document.getElementById('foto_pos_x_val');
          var posY = document.getElementById('foto_pos_y');
          var posYVal = document.getElementById('foto_pos_y_val');
          function syncSpans() {
            if (zoomVal) zoomVal.textContent = zoom.value + 'x';
            if (posXVal) posXVal.textContent = posX.value + '%';
            if (posYVal) posYVal.textContent = posY.value + '%';
          }
          zoom.addEventListener('input', syncSpans);
          posX.addEventListener('input', syncSpans);
          posY.addEventListener('input', syncSpans);
          syncSpans();
          input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
              if (ph) ph.style.display = 'none';
              if (img) { img.style.display = ''; img.src = URL.createObjectURL(input.files[0]); }
            } else {
              if (ph) ph.style.display = '';
              if (img) { img.style.display = 'none'; }
            }
          });
          if (img) {
            var box = document.getElementById('fotoPratinjauBox');
            var dragging = false; var startX = 0; var startY = 0; var offX = parseFloat(posX.value); var offY = parseFloat(posY.value);
            img.addEventListener('dragstart', function (e) { dragging = true; startX = e.clientX; startY = e.clientY; offX = parseFloat(posX.value); offY = parseFloat(posY.value); });
            img.addEventListener('drag', function (e) {
              if (!dragging) return;
              var w = box ? box.clientWidth : 400;
              var h = box ? box.clientHeight : 400;
              var dx = (e.clientX - startX) / (w || 1) * 100;
              var dy = (e.clientY - startY) / (h || 1) * 100;
              posX.value = Math.max(0, Math.min(100, Math.round(offX + dx)));
              posY.value = Math.max(0, Math.min(100, Math.round(offY + dy)));
              syncSpans();
            });
            img.addEventListener('dragend', function () { dragging = false; });
          }
          document.getElementById('hapusFoto').addEventListener('change', function (e) {
            if (e.target.checked && img && ph) { ph.style.display = ''; img.style.display = 'none'; }
          });
        })();
        </script>
      <?php endif; ?>
    </section>
  <?php endif; endif; ?>
  </aside>
</div>

<?php elseif ($tab === 'promo'): ?>
<?php
/* ------------------------------- TAB PROMO -------------------------------- */
$promoAksi = $aksi;
$promoId   = $id;
$stmtP = db()->query('SELECT p.*, m.nama AS menu_nama FROM promo p LEFT JOIN menu m ON m.id=p.menu_id ORDER BY p.id DESC');
$promos = $stmtP->fetchAll();

$promoEdit = null;
if (in_array($promoAksi, ['edit', 'tambah'], true) && ($promoAksi === 'tambah' || $promoId > 0)) {
    if ($promoId > 0) {
        $st = db()->prepare('SELECT * FROM promo WHERE id=?');
        $st->execute([$promoId]);
        $promoEdit = $st->fetch() ?: null;
    }
}
$semuaMenu = db()->query('SELECT id, nama, kategori FROM menu WHERE diarsipkan=0 ORDER BY nama')->fetchAll();
?>

<div class="form-grid" style="grid-template-columns:minmax(0,1fr) 340px;align-items:start">
  <div>
    <section class="kartu">
      <div class="kartu-head">
        <div><h2 style="font-family:'Fraunces',serif;font-size:20px;margin:0">Diskon &amp; promo</h2>
          <p style="color:var(--teks-muted);margin:3px 0 0">Atur potongan harga dan masa berlaku tiap promo.</p></div>
      </div>
      <div class="tabel-wrap">
        <table class="tabel">
          <thead><tr>
            <th>Kode</th><th>Menu</th><th>Potongan</th><th>Berlaku</th><th>Status</th><th>Tindakan</th>
          </tr></thead>
          <tbody>
          <?php if (!$promos): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--teks-muted);padding:28px">Belum ada promo.</td></tr>
          <?php else: foreach ($promos as $p): ?>
            <?php
              $berlaku = 'Tanpa batas';
              if ($p['berlaku_mulai'] || $p['berlaku_sampai']) {
                  $mulai = $p['berlaku_mulai'] ? tanggal_indonesia($p['berlaku_mulai'], false) : '—';
                  $sampai = $p['berlaku_sampai'] ? tanggal_indonesia($p['berlaku_sampai'], false) : '—';
                  $berlaku = $mulai . ' – ' . $sampai;
              }
            ?>
            <tr>
              <td><strong><?= e($p['kode']) ?></strong></td>
              <td><?= e($p['menu_nama'] ?? '—') ?></td>
              <td><?= e(rtrim(rtrim(number_format((float)$p['potongan_persen'], 2, ',', '.'), '0'), ',')) ?>%</td>
              <td style="font-size:12.5px"><?= e($berlaku) ?></td>
              <td>
                <form method="post" action="<?= e(base_url('promo/toggle.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="badge <?= (int)$p['aktif'] === 1 ? 'badge-hijau' : 'badge-abu' ?>" style="border:0;cursor:pointer" title="Klik untuk <?= (int)$p['aktif'] === 1 ? 'nonaktifkan' : 'aktifkan' ?>">
                    <?= (int)$p['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                  </button>
                </form>
              </td>
              <td style="white-space:nowrap">
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url($self . '?tab=promo&aksi=edit&id=' . (int)$p['id'])) ?>">Edit</a>
                <form method="post" action="<?= e(base_url('promo/hapus.php')) ?>" style="display:inline" onsubmit="return confirm('Hapus promo ini?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--merah-teks)" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <aside>
    <section class="kartu">
      <h2 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px">
        <?= $promoEdit ? 'Edit promo' : 'Tambah promo' ?>
      </h2>
      <p style="color:var(--teks-muted);font-size:12.5px;margin:0 0 14px">Satu menu hanya boleh satu promo aktif per rentang tanggal.</p>
      <form method="post" action="<?= e(base_url('promo/simpan.php')) ?>">
        <?= csrf_field() ?>
        <?php if ($promoEdit): ?><input type="hidden" name="id" value="<?= (int)$promoEdit['id'] ?>"><?php endif; ?>
        <div class="form-group">
          <label for="menu_id">Menu</label>
          <select name="menu_id" id="menu_id" required>
            <option value="">— Pilih menu —</option>
            <?php foreach ($semuaMenu as $m): ?>
              <?php $pilih = $promoEdit && (int)$promoEdit['menu_id'] === (int)$m['id']; ?>
              <option value="<?= (int)$m['id'] ?>" <?= $pilih ? 'selected' : '' ?>><?= e($m['nama']) ?> (<?= e($m['kategori']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="kode">Kode promo</label>
          <input type="text" name="kode" id="kode" pattern="[A-Z0-9_-]+" maxlength="30" required
                 value="<?= e($promoEdit['kode'] ?? '') ?>" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_-]/g,'')"
                 placeholder="MISAL: MATCHA2">
          <small style="color:var(--teks-muted)">Huruf kapital, angka, “-” atau “_”. Unik.</small>
        </div>
        <div class="form-group">
          <label for="potongan_persen">Potongan (%)</label>
          <input type="number" name="potongan_persen" id="potongan_persen" min="0.01" max="100" step="0.01" required
                 value="<?= e($promoEdit['potongan_persen'] ?? '') ?>" placeholder="2">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="berlaku_mulai">Berlaku mulai</label>
            <input type="date" name="berlaku_mulai" id="berlaku_mulai" value="<?= e($promoEdit['berlaku_mulai'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="berlaku_sampai">Berlaku sampai</label>
            <input type="date" name="berlaku_sampai" id="berlaku_sampai" value="<?= e($promoEdit['berlaku_sampai'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="baris-opsi" style="font-weight:600">
            <span>Aktif</span>
            <input type="checkbox" name="aktif" value="1" style="width:18px;height:18px" <?= (!$promoEdit || (int)$promoEdit['aktif'] === 1) ? 'checked' : '' ?>>
          </label>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" type="submit">Simpan promo</button>
          <?php if ($promoEdit): ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(base_url($self . '?tab=promo')) ?>">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </section>
  </aside>
</div>

<?php else: ?>
<?php
/* ------------------------------- TAB GALERI -------------------------------- */
$galeris = db()->query('SELECT * FROM galeri ORDER BY urutan, id')->fetchAll();
?>

<div class="form-grid" style="grid-template-columns:minmax(0,1fr) 320px;align-items:start">
  <div>
    <section class="kartu">
      <div class="kartu-head">
        <div><h2 style="font-family:'Fraunces',serif;font-size:20px;margin:0">Galeri kedai</h2>
          <p style="color:var(--teks-muted);margin:3px 0 0">Foto-foto suasana dan racikan Café Setara.</p></div>
      </div>
      <?php if (!$galeris): ?>
        <p style="color:var(--teks-muted)">Belum ada foto di galeri.</p>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
        <?php foreach ($galeris as $g): ?>
          <?php
            $gambar = (string)$g['gambar'];
            $ada = $gambar !== '' && is_file(dirname(__DIR__, 2) . '/' . $gambar);
          ?>
          <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:#fff">
            <?php if ($ada): ?>
              <img src="<?= e(base_url($gambar)) ?>" alt="<?= e($g['judul']) ?>" style="width:100%;height:150px;object-fit:cover;display:block;background:var(--krem-2)">
            <?php else: ?>
              <div class="foto-placeholder" style="max-width:none;height:150px;border-radius:0;border:0"><span><i data-lucide="image-off"></i><br>Berkas tidak ditemukan</span></div>
            <?php endif; ?>
            <div style="padding:10px 12px">
              <form method="post" action="<?= e(base_url('galeri/simpan.php')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <div style="flex:1;min-width:90px">
                  <label style="font-size:11px">Judul</label>
                  <input type="text" name="judul" value="<?= e($g['judul']) ?>" style="height:34px;font-size:12px">
                </div>
                <div style="width:64px">
                  <label style="font-size:11px">Urutan</label>
                  <input type="number" name="urutan" value="<?= (int)$g['urutan'] ?>" style="height:34px;font-size:12px">
                </div>
                <button class="btn btn-secondary btn-sm" type="submit" title="Simpan perubahan">Simpan</button>
              </form>
              <form method="post" action="<?= e(base_url('galeri/hapus.php')) ?>" style="margin-top:8px" onsubmit="return confirm('Hapus foto ini?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><i data-lucide="trash-2"></i> Hapus</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="kartu">
      <h2 style="font-family:'Fraunces',serif;font-size:19px;margin:0 0 4px">Tambah foto</h2>
      <p style="color:var(--teks-muted);font-size:12.5px;margin:0 0 14px">jpg/png/webp, maksimal 2MB.</p>
      <form method="post" action="<?= e(base_url('galeri/simpan.php')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="g_judul">Judul (opsional)</label>
          <input type="text" name="judul" id="g_judul" placeholder="Misal: Suasana kedai">
        </div>
        <div class="form-group">
          <label for="g_foto">Foto</label>
          <input type="file" name="foto" id="g_foto" accept="image/jpeg,image/png,image/webp" required style="height:auto;padding:8px">
        </div>
        <div class="form-group">
          <label for="g_urutan">Urutan</label>
          <input type="number" name="urutan" id="g_urutan" value="0">
        </div>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" type="submit">Unggah foto</button>
        </div>
      </form>
    </section>
  </aside>
</div>

<?php endif; ?>

<?php
layout_footer();

