<?php
/**
 * bahan/index.php — Bahan & stok: snapshot stok, pergerakan, dan riwayat.
 * Modul: Bahan & stok · Café Setara.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout_owner.php';

require_role(['owner']);

$pdo = db();

/* ------------------------------------------------------------ Aksi & filter */
$aksi       = isset($_GET['aksi']) ? (string)$_GET['aksi'] : '';
$editId     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cari       = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'semua');
if (!in_array($statusFilter, ['semua', 'kritis', 'aman'], true)) {
    $statusFilter = 'semua';
}
$dari   = trim((string)($_GET['dari'] ?? ''));
$sampai = trim((string)($_GET['sampai'] ?? ''));
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$perHal = 20;

/* --------------------------------------------------------------- Data bahan */
$semuaBahan = $pdo->query(
    'SELECT id, nama, satuan, harga_beli_per_satuan, stok, stok_minimum
       FROM bahan ORDER BY nama'
)->fetchAll();

if (!$semuaBahan) {
    flash('warning', 'Belum ada bahan. Tambahkan bahan pertama melalui tombol "+ Tambah bahan".');
}

// Peta bahan untuk JS pratinjau stok masuk.
$petaBahan = [];
foreach ($semuaBahan as $b) {
    $petaBahan[(string)$b['id']] = [
        'nama'  => $b['nama'],
        'satuan' => $b['satuan'],
        'stok'   => (float)$b['stok'],
        'harga'  => (float)$b['harga_beli_per_satuan'],
    ];
}
$jsonBahan = json_encode($petaBahan, JSON_UNESCAPED_UNICODE);

// Bahan terpilih untuk form stok masuk / edit / koreksi.
$bahanMasukId = (in_array($aksi, ['stok_masuk', 'koreksi'], true) && $editId > 0) ? $editId : 0;
$bahanEditId  = ($aksi === 'edit' && $editId > 0) ? $editId : 0;

$pilihMasuk = null;
$pilihEdit  = null;
foreach ($semuaBahan as $b) {
    if ($bahanMasukId > 0 && (int)$b['id'] === $bahanMasukId) { $pilihMasuk = $b; }
    if ($bahanEditId > 0 && (int)$b['id'] === $bahanEditId) { $pilihEdit = $b; }
}
if ($pilihMasuk === null && $semuaBahan) { $pilihMasuk = $semuaBahan[0]; }
if ($pilihEdit === null && $semuaBahan)  { $pilihEdit  = $semuaBahan[0]; }

/* ---------------------------------------------------------- Daftar + filter */
$daftarBahan = [];
foreach ($semuaBahan as $b) {
    if ($cari !== '' && stripos($b['nama'], $cari) === false) { continue; }
    $kritis = (float)$b['stok'] <= (float)$b['stok_minimum'];
    if ($statusFilter === 'kritis' && !$kritis) { continue; }
    if ($statusFilter === 'aman' && $kritis) { continue; }
    $daftarBahan[] = $b;
}

/* ------------------------------------------------------------ Banner kritis */
$kritis = bahan_kritis();
$jumlahKritis = count($kritis);
$namaKritis = array_column($kritis, 'nama');

/* ------------------------------------------------------- Riwayat pergerakan */
$where   = [];
$params  = [];
if ($dari !== '')   { $where[] = 'ps.tanggal >= ?'; $params[] = $dari . ' 00:00:00'; }
if ($sampai !== '') { $where[] = 'ps.tanggal <= ?'; $params[] = $sampai . ' 23:59:59'; }
$klausaWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stHitung = $pdo->prepare(
    'SELECT COUNT(*) FROM pergerakan_stok ps ' . $klausaWhere
);
$stHitung->execute($params);
$totalRiwayat = (int)$stHitung->fetchColumn();
$jumlahHal = max(1, (int)ceil($totalRiwayat / $perHal));
if ($hal > $jumlahHal) { $hal = $jumlahHal; }
$offset = ($hal - 1) * $perHal;

$stRiwayat = $pdo->prepare(
    'SELECT ps.id, ps.bahan_id, ps.tipe, ps.qty, ps.keterangan, ps.transaksi_id, ps.tanggal,
            b.nama AS bahan_nama, b.satuan, t.kode AS trx_kode
       FROM pergerakan_stok ps
       JOIN bahan b ON b.id = ps.bahan_id
       LEFT JOIN transaksi t ON t.id = ps.transaksi_id
       ' . $klausaWhere . '
      ORDER BY ps.tanggal DESC, ps.id DESC
      LIMIT ' . $perHal . ' OFFSET ' . $offset
);
$stRiwayat->execute($params);
$riwayat = $stRiwayat->fetchAll();

/** Susun query string untuk pagination/filter riwayat. */
function qs_riwayat(array $over = []): string
{
    global $cari, $statusFilter, $dari, $sampai;
    $q = array_filter([
        'q'      => $cari,
        'status' => $statusFilter !== 'semua' ? $statusFilter : '',
        'dari'   => $dari,
        'sampai' => $sampai,
    ], fn($v) => $v !== '' && $v !== null);
    foreach ($over as $k => $v) { $q[$k] = $v; }
    return http_build_query($q);
}

/** Format angka stok: 800 -> "800", 1000.5 -> "1.000,5". */
function fmt_angka($n): string
{
    return number_format((float)$n, 2, ',', '.');
}

/** Ambil argumen filter dalam bentuk query string untuk link aksi tabel. */
function qs_filter(): string
{
    global $cari, $statusFilter, $dari, $sampai, $hal;
    return http_build_query(array_filter([
        'q'      => $cari,
        'status' => $statusFilter !== 'semua' ? $statusFilter : '',
        'dari'   => $dari,
        'sampai' => $sampai,
        'hal'    => $hal > 1 ? $hal : '',
    ], fn($v) => $v !== '' && $v !== null));
}

$qs = qs_filter();

layout_header('Bahan & stok', 'Snapshot stok sebelum transaksi contoh. Pantau batas minimum dan jejak perubahan.', 'bahan');
?>

<?php if ($jumlahKritis > 0): ?>
  <div class="banner-kuning">
    <strong><i data-lucide="alert-triangle"></i> <?= $jumlahKritis ?> bahan di bawah minimum.</strong>
    <span><?= e(implode(', ', $namaKritis)) ?> perlu diisi ulang sebelum jam operasional.</span>
  </div>
<?php endif; ?>

<div class="baris-atas mb16">
  <div class="filter-bar" style="margin-bottom:0">
    <form method="get" action="<?= e(base_url('bahan/index.php')) ?>" class="filter-bar" style="margin-bottom:0">
      <div class="input-ikon">
        <input type="text" name="q" id="cari-bahan" placeholder="Cari bahan..." value="<?= e($cari) ?>">
        <span class="ikon-kanan"><i data-lucide="search"></i></span>
      </div>
      <select name="status" aria-label="Status stok" style="max-width:170px">
        <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua status</option>
        <option value="kritis" <?= $statusFilter === 'kritis' ? 'selected' : '' ?>>Kritis</option>
        <option value="aman" <?= $statusFilter === 'aman' ? 'selected' : '' ?>>Aman</option>
      </select>
      <?php if ($dari !== ''): ?><input type="hidden" name="dari" value="<?= e($dari) ?>"><?php endif; ?>
      <?php if ($sampai !== ''): ?><input type="hidden" name="sampai" value="<?= e($sampai) ?>"><?php endif; ?>
      <button class="btn btn-secondary btn-sm" type="submit"><i data-lucide="filter"></i> Terapkan</button>
      <a class="btn btn-secondary btn-sm" href="#riwayat"><i data-lucide="history"></i> Riwayat pergerakan</a>
    </form>
  </div>
  <button type="button" class="btn btn-primary" id="buka-modal-tambah">
    <i data-lucide="plus"></i> Tambah bahan
  </button>
</div>

<!-- ===================================================== Persediaan bahan -->
<div class="kartu">
  <div class="kartu-head">
    <div><h2>Persediaan bahan</h2><p class="muted">Satuan konsisten dengan resep</p></div>
    <span class="badge badge-abu"><?= count($daftarBahan) ?> bahan</span>
  </div>
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th>Bahan</th>
          <th>Unit</th>
          <th class="kanan">Stok</th>
          <th class="kanan">Minimum</th>
          <th>Status</th>
          <th>Tindakan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$daftarBahan): ?>
          <tr><td colspan="6" class="tengah muted">Tidak ada bahan yang cocok dengan filter.</td></tr>
        <?php else: foreach ($daftarBahan as $b): ?>
          <?php $kritisBaris = (float)$b['stok'] <= (float)$b['stok_minimum']; ?>
          <tr>
            <td><strong style="color:var(--teks)"><?= e($b['nama']) ?></strong></td>
            <td><?= e($b['satuan']) ?></td>
            <td class="kanan"><strong><?= e(fmt_angka($b['stok'])) ?></strong> <?= e($b['satuan']) ?></td>
            <td class="kanan muted"><?= e(fmt_angka($b['stok_minimum'])) ?> <?= e($b['satuan']) ?></td>
            <td>
              <?php if ($kritisBaris): ?>
                <span class="badge badge-merah"><i data-lucide="circle-alert"></i> Kritis</span>
              <?php else: ?>
                <span class="badge badge-hijau"><i data-lucide="circle-check"></i> Aman</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="filter-bar" style="margin-bottom:0;gap:4px">
                <a class="btn btn-secondary btn-sm" href="<?= e(base_url('bahan/index.php?aksi=edit&id=' . (int)$b['id'] . ($qs !== '' ? '&' . $qs : ''))) ?>">Edit</a>
                <a class="btn btn-secondary btn-sm" href="<?= e(base_url('bahan/index.php?aksi=stok_masuk&id=' . (int)$b['id'] . ($qs !== '' ? '&' . $qs : ''))) ?>">Masuk</a>
                <a class="btn btn-secondary btn-sm" href="<?= e(base_url('bahan/index.php?aksi=koreksi&id=' . (int)$b['id'] . ($qs !== '' ? '&' . $qs : ''))) ?>">Koreksi</a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small" style="margin-top:12px">
    Form stok masuk umum: <a href="<?= e(base_url('bahan/index.php?aksi=stok_masuk' . ($qs !== '' ? '&' . $qs : ''))) ?>">catat stok masuk tanpa memilih bahan →</a>
  </p>
</div>

<!-- ============================================== Dua kartu berdampingan -->
<div class="grid grid-2 mt20">

  <!-- KIRI: Catat stok masuk -->
  <div class="kartu" id="form-stok-masuk">
    <div class="kartu-head">
      <div><h2>Catat stok masuk</h2><p class="muted">Form berbasis dokumen. Belum disimpan.</p></div>
      <span class="badge badge-krem"><i data-lucide="package-plus"></i> Masuk</span>
    </div>

    <form method="post" action="<?= e(base_url('bahan/stok_masuk.php')) ?>" id="form-masuk">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="bahan_masuk_id">Bahan</label>
        <select name="bahan_id" id="bahan_masuk_id">
          <?php foreach ($semuaBahan as $b): ?>
            <option value="<?= (int)$b['id'] ?>" data-satuan="<?= e($b['satuan']) ?>"
              <?= ($pilihMasuk && (int)$pilihMasuk['id'] === (int)$b['id']) ? 'selected' : '' ?>>
              <?= e($b['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="jumlah_masuk">Jumlah masuk</label>
          <input type="number" name="jumlah" id="jumlah_masuk" step="0.01" min="0.01" value="0" required>
        </div>
        <div class="form-group">
          <label for="tanggal_masuk">Tanggal</label>
          <input type="date" name="tanggal" id="tanggal_masuk" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="total_harga">Total harga pembelian (Rp, opsional)</label>
          <div class="input-prefix">
            <span>Rp</span>
            <input type="number" name="total_harga" id="total_harga" min="0" step="1" value="">
          </div>
        </div>
        <div class="form-group">
          <label for="ket_pembelian">Keterangan pembelian</label>
          <input type="text" name="keterangan" id="ket_pembelian" maxlength="255" placeholder="Contoh: Faktur supplier 12/11">
        </div>
      </div>

      <div class="garis-info" id="pratinjau-masuk">
        <i data-lucide="eye"></i>
        <span id="teks-pratinjau">Pratinjau: stok — + 0 = —. Harga baru per satuan —.</span>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" style="flex:1">
          <i data-lucide="check"></i> Simpan stok masuk
        </button>
        <a class="btn btn-secondary" href="<?= e(base_url('bahan/index.php' . ($qs !== '' ? '?' . $qs : ''))) ?>">Batal</a>
      </div>
    </form>
  </div>

  <!-- KANAN: Edit bahan + koreksi -->
  <div class="kartu" id="form-edit">
    <div class="kartu-head">
      <div>
        <h2>Edit bahan</h2>
        <p class="muted">Bahan ID <?= $pilihEdit ? (int)$pilihEdit['id'] : '—' ?></p>
      </div>
      <span class="badge badge-krem"><i data-lucide="pencil"></i> Edit</span>
    </div>

    <form method="post" action="<?= e(base_url('bahan/simpan.php')) ?>" id="form-simpan">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $pilihEdit ? (int)$pilihEdit['id'] : '' ?>">
      <div class="form-group">
        <label for="nama_bahan">Nama bahan</label>
        <input type="text" name="nama" id="nama_bahan" maxlength="100" required value="<?= e($pilihEdit ? $pilihEdit['nama'] : '') ?>">
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="satuan_bahan">Satuan</label>
          <input type="text" name="satuan" id="satuan_bahan" maxlength="20" required value="<?= e($pilihEdit ? $pilihEdit['satuan'] : 'gram') ?>">
        </div>
        <div class="form-group">
          <label for="harga_bahan">Harga beli / satuan (Rp)</label>
          <div class="input-prefix">
            <span>Rp</span>
            <input type="number" name="harga_beli_per_satuan" id="harga_bahan" min="0" step="0.01" required value="<?= e($pilihEdit ? number_format((float)$pilihEdit['harga_beli_per_satuan'], 2, '.', '') : '0') ?>">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label for="minimum_bahan">Stok minimum</label>
        <input type="number" name="stok_minimum" id="minimum_bahan" min="0" step="0.01" required value="<?= e($pilihEdit ? number_format((float)$pilihEdit['stok_minimum'], 2, '.', '') : '0') ?>">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" style="flex:1"><i data-lucide="save"></i> Simpan bahan</button>
      </div>
      <p class="muted small" style="margin-top:10px">Stok tidak bisa diubah langsung dari form ini.</p>
    </form>

    <hr class="hr">

    <div class="baris-atas">
      <div><h3>Koreksi stok perlu alasan</h3></div>
    </div>
    <p class="muted small" style="margin-top:6px">
      Stok awal <?= e(fmt_angka($pilihEdit ? $pilihEdit['stok'] : 0)) ?> <?= e($pilihEdit ? $pilihEdit['satuan'] : '') ?>.
      Gunakan koreksi bila ada selisih stok fisik; perubahan dicatat di riwayat.
    </p>

    <form method="post" action="<?= e(base_url('bahan/koreksi.php')) ?>" id="form-koreksi" <?= ($aksi === 'koreksi' && $pilihEdit) ? 'data-fokus="1"' : '' ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="bahan_id" value="<?= $pilihEdit ? (int)$pilihEdit['id'] : '' ?>">
      <div class="form-grid">
        <div class="form-group">
          <label for="stok_fisik">Stok fisik</label>
          <input type="number" name="stok_fisik" id="stok_fisik" step="0.01" min="0" required value="<?= e($pilihEdit ? number_format((float)$pilihEdit['stok'], 2, '.', '') : '0') ?>">
        </div>
        <div class="form-group">
          <label>Selisih</label>
          <div class="garis-info" style="margin:0" id="selisih-tampil">0</div>
        </div>
      </div>
      <div class="form-group">
        <label for="ket_koreksi">Keterangan (wajib)</label>
        <input type="text" name="keterangan" id="ket_koreksi" maxlength="255" required placeholder="Contoh: Selisih hitung fisik saat stok opname">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-secondary" style="flex:1"><i data-lucide="refresh-cw"></i> Koreksi stok</button>
      </div>
    </form>
  </div>
</div>

<!-- ================================================= Riwayat pergerakan -->
<div class="kartu mt20" id="riwayat">
  <div class="kartu-head">
    <div>
      <h2>Riwayat pergerakan bahan</h2>
      <p class="muted">Simulasi dampak penjualan. Stok masuk, koreksi, dan penjualan mempengaruhi stok.</p>
    </div>
    <span class="badge badge-abu"><?= $totalRiwayat ?> catatan</span>
  </div>

  <form method="get" action="<?= e(base_url('bahan/index.php')) ?>" class="filter-bar" id="form-filter-riwayat">
    <?php if ($cari !== ''): ?><input type="hidden" name="q" value="<?= e($cari) ?>"><?php endif; ?>
    <?php if ($statusFilter !== 'semua'): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <div class="form-group" style="margin:0">
      <label for="dari" style="margin-bottom:2px">Dari</label>
      <input type="date" name="dari" id="dari" value="<?= e($dari) ?>" style="max-width:170px">
    </div>
    <div class="form-group" style="margin:0">
      <label for="sampai" style="margin-bottom:2px">Sampai</label>
      <input type="date" name="sampai" id="sampai" value="<?= e($sampai) ?>" style="max-width:170px">
    </div>
    <button type="submit" class="btn btn-secondary btn-sm" style="align-self:flex-end"><i data-lucide="filter"></i> Filter</button>
    <a class="btn btn-ghost btn-sm" style="align-self:flex-end" href="<?= e(base_url('bahan/index.php?' . http_build_query(array_filter(['q' => $cari, 'status' => $statusFilter !== 'semua' ? $statusFilter : ''], fn($v) => $v !== '' && $v !== null)))) ?>">Reset</a>
  </form>

  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Bahan</th>
          <th>Tipe</th>
          <th class="kanan">Jumlah</th>
          <th>Referensi</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$riwayat): ?>
          <tr><td colspan="6" class="tengah muted">Belum ada pergerakan stok.</td></tr>
        <?php else: foreach ($riwayat as $r): ?>
          <?php
            $tipe = $r['tipe'];
            $badge = $tipe === 'masuk' ? 'badge-hijau' : ($tipe === 'penjualan' ? 'badge-krem' : 'badge-kuning');
            $label = ['masuk' => 'Masuk', 'penjualan' => 'Penjualan', 'koreksi' => 'Koreksi'][$tipe] ?? ucfirst($tipe);
            $qty   = (float)$r['qty'];
            $tanda = $qty < 0 ? '−' : '+';
            $angka = fmt_angka(abs($qty));
            $ref   = !empty($r['transaksi_id'])
                ? (!empty($r['trx_kode']) ? $r['trx_kode'] : 'TRX-' . (int)$r['transaksi_id'])
                : '—';
          ?>
          <tr>
            <td class="nowrap"><?= e(date('d/m/Y H.i', strtotime($r['tanggal']))) ?></td>
            <td><?= e($r['bahan_nama']) ?></td>
            <td><span class="badge <?= $badge ?>"><?= e($label) ?></span></td>
            <td class="kanan nowrap"><strong><?= e($tanda . $angka . ' ' . $r['satuan']) ?></strong></td>
            <td><?= e($ref) ?></td>
            <td class="muted small"><?= e($r['keterangan'] ?: '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($jumlahHal > 1): ?>
    <nav class="paginasi" aria-label="Halaman riwayat">
      <?php if ($hal > 1): ?>
        <a href="<?= e(base_url('bahan/index.php?' . qs_riwayat(['hal' => $hal - 1]) . '#riwayat')) ?>">&laquo;</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $jumlahHal; $i++): ?>
        <?php if ($i === $hal): ?>
          <span class="aktif"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= e(base_url('bahan/index.php?' . qs_riwayat(['hal' => $i]) . '#riwayat')) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($hal < $jumlahHal): ?>
        <a href="<?= e(base_url('bahan/index.php?' . qs_riwayat(['hal' => $hal + 1]) . '#riwayat')) ?>">&raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<!-- ==================================================== Modal tambah bahan -->
<div class="modal" id="modal-tambah" role="dialog" aria-modal="true" aria-labelledby="judul-modal-tambah">
  <div class="modal-kartu">
    <div class="baris-atas">
      <div><h2 id="judul-modal-tambah">Tambah bahan</h2><p class="muted small">Stok awal dicatat sebagai pergerakan masuk.</p></div>
      <button type="button" class="btn btn-ghost btn-icon" id="tutup-modal" aria-label="Tutup"><i data-lucide="x"></i></button>
    </div>
    <form method="post" action="<?= e(base_url('bahan/simpan.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="tambah">
      <div class="form-group">
        <label for="t_nama">Nama bahan</label>
        <input type="text" name="nama" id="t_nama" maxlength="100" required>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="t_satuan">Satuan</label>
          <input type="text" name="satuan" id="t_satuan" maxlength="20" value="gram" required>
        </div>
        <div class="form-group">
          <label for="t_harga">Harga beli per satuan (Rp)</label>
          <div class="input-prefix">
            <span>Rp</span>
            <input type="number" name="harga_beli_per_satuan" id="t_harga" min="0" step="0.01" value="0" required>
          </div>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="t_stok">Stok awal</label>
          <input type="number" name="stok_awal" id="t_stok" min="0" step="0.01" value="0" required>
        </div>
        <div class="form-group">
          <label for="t_minimum">Stok minimum</label>
          <input type="number" name="stok_minimum" id="t_minimum" min="0" step="0.01" value="0" required>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" style="flex:1"><i data-lucide="plus"></i> Simpan bahan</button>
        <button type="button" class="btn btn-secondary" id="batal-modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  var dataBahan = <?= $jsonBahan ?>;

  function fmt(n, desimal) {
    var d = desimal || 0;
    return Number(n).toLocaleString('id-ID', {minimumFractionDigits: d, maximumFractionDigits: d});
  }
  function rupiah(n) { return 'Rp' + fmt(Math.round(n), 0); }

  /* ---------- Pratinjau stok masuk ---------- */
  var selectBahan = document.getElementById('bahan_masuk_id');
  var inputJumlah = document.getElementById('jumlah_masuk');
  var inputTotal  = document.getElementById('total_harga');
  var teksPreview = document.getElementById('teks-pratinjau');

  function perbaruiPratinjau() {
    if (!selectBahan || !inputJumlah || !inputTotal || !teksPreview) return;
    var info = dataBahan[selectBahan.value] || {nama: '', satuan: '', stok: 0, harga: 0};
    var jumlah = parseFloat(inputJumlah.value) || 0;
    var total = parseFloat(inputTotal.value) || 0;
    var stokBaru = (parseFloat(info.stok) || 0) + jumlah;
    var hargaBaru = jumlah > 0 && total > 0 ? (total / jumlah) : (parseFloat(info.harga) || 0);
    var desimal = (String(jumlah).indexOf('.') !== -1) ? 2 : 0;
    teksPreview.textContent =
      'Pratinjau: stok ' + fmt(info.stok, desimal) + ' ' + info.satuan +
      ' + ' + fmt(jumlah, desimal) + ' ' + info.satuan +
      ' = ' + fmt(stokBaru, desimal) + ' ' + info.satuan + '. ' +
      (total > 0 && jumlah > 0
        ? 'Harga baru per satuan ' + rupiah(hargaBaru) + ' (dihitung otomatis).'
        : 'Harga per satuan tetap ' + rupiah(info.harga) + '.');
  }
  if (selectBahan) selectBahan.addEventListener('change', perbaruiPratinjau);
  if (inputJumlah) inputJumlah.addEventListener('input', perbaruiPratinjau);
  if (inputTotal)  inputTotal.addEventListener('input', perbaruiPratinjau);
  perbaruiPratinjau();

  /* ---------- Selisih koreksi ---------- */
  var stokSekarang = <?= $pilihEdit ? (float)$pilihEdit['stok'] : 0 ?>;
  var inputFisik = document.getElementById('stok_fisik');
  var tampilSelisih = document.getElementById('selisih-tampil');
  function perbaruiSelisih() {
    if (!inputFisik || !tampilSelisih) return;
    var fisik = parseFloat(inputFisik.value) || 0;
    var selisih = fisik - stokSekarang;
    var tanda = selisih < 0 ? '−' : (selisih > 0 ? '+' : '');
    tampilSelisih.textContent = tanda + fmt(Math.abs(selisih), 2);
  }
  if (inputFisik) inputFisik.addEventListener('input', perbaruiSelisih);
  perbaruiSelisih();

  /* ---------- Fokus form koreksi bila diminta ---------- */
  var formKoreksi = document.getElementById('form-koreksi');
  if (formKoreksi && formKoreksi.getAttribute('data-fokus') === '1') {
    formKoreksi.scrollIntoView({behavior: 'smooth', block: 'center'});
    var ket = document.getElementById('ket_koreksi');
    if (ket) setTimeout(function () { ket.focus(); }, 350);
  }

  /* ---------- Modal tambah ---------- */
  var modal = document.getElementById('modal-tambah');
  function bukaModal() { if (modal) modal.classList.add('tampil'); }
  function tutupModal() { if (modal) modal.classList.remove('tampil'); }
  var tombolBuka = document.getElementById('buka-modal-tambah');
  if (tombolBuka) tombolBuka.addEventListener('click', bukaModal);
  ['tutup-modal', 'batal-modal'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', tutupModal);
  });
  if (modal) {
    modal.addEventListener('click', function (ev) { if (ev.target === modal) tutupModal(); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') tutupModal(); });
  }
})();
</script>

<?php
layout_footer();
