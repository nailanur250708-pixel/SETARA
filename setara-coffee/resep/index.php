<?php
/**
 * resep/index.php — Atur komposisi resep & lihat HPP per porsi.
 * Modul: Resep & HPP · Café Setara.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout_owner.php';

require_role(['owner']);

$pdo = db();

/* ---------------------------------------------------------------- Data menu */
$semuaMenu = $pdo->query(
    'SELECT id, nama, kategori, harga, foto_path, gambar, diarsipkan
       FROM menu ORDER BY diarsipkan, nama'
)->fetchAll();

if (!$semuaMenu) {
    flash('warning', 'Belum ada menu. Tambahkan menu terlebih dahulu sebelum mengatur resep.');
    redirect('menu/index.php');
}

$menuId = isset($_GET['menu']) ? (int)$_GET['menu'] : (int)$semuaMenu[0]['id'];
$menuDipilih = null;
foreach ($semuaMenu as $m) {
    if ((int)$m['id'] === $menuId) { $menuDipilih = $m; break; }
}
if ($menuDipilih === null) {
    $menuDipilih = $semuaMenu[0];
    $menuId = (int)$menuDipilih['id'];
}

/* --------------------------------------------------------------- Data bahan */
$semuaBahan = $pdo->query(
    'SELECT id, nama, satuan, harga_beli_per_satuan FROM bahan ORDER BY nama'
)->fetchAll();

// Peta bahan untuk JS (id => {nama, satuan, harga}). JSON_UNESCAPED_UNICODE.
$petaBahan = [];
foreach ($semuaBahan as $b) {
    $petaBahan[(string)$b['id']] = [
        'nama'  => $b['nama'],
        'satuan' => $b['satuan'],
        'harga'  => (float)$b['harga_beli_per_satuan'],
    ];
}
$jsonBahan = json_encode($petaBahan, JSON_UNESCAPED_UNICODE);

/* -------------------------------------------------------------- Resep menu */
$stResep = $pdo->prepare(
    'SELECT r.id AS resep_id, r.bahan_id, r.takaran, b.nama AS bahan_nama, b.satuan, b.harga_beli_per_satuan
       FROM resep r JOIN bahan b ON b.id = r.bahan_id
      WHERE r.menu_id = ? ORDER BY b.nama'
);
$stResep->execute([$menuId]);
$barisResep = $stResep->fetchAll();

$hpp = 0.0;
foreach ($barisResep as $r) {
    $hpp += (float)$r['takaran'] * (float)$r['harga_beli_per_satuan'];
}
$hargaJual = (float)$menuDipilih['harga'];
$laba = $hargaJual - $hpp;
$margin = $hargaJual > 0 ? round(($laba / $hargaJual) * 100) : 0;

/* ------------------------------------------------------------------ Foto */
$foto = null;
if (!empty($menuDipilih['foto_path'])) {
    $foto = $menuDipilih['foto_path'];
} elseif (!empty($menuDipilih['gambar'])) {
    $foto = $menuDipilih['gambar'];
}

layout_header('Resep & HPP', 'Atur takaran per porsi. HPP dan margin mengikuti komposisi resep.', 'resep');
?>

<div class="baris-atas mb16">
  <div>
    <a class="btn btn-secondary btn-sm" href="<?= e(base_url('menu/index.php')) ?>">
      <i data-lucide="arrow-left"></i> Kembali ke menu
    </a>
  </div>
  <div class="filter-bar" style="margin-bottom:0">
    <label for="pilih-menu" style="margin:0;white-space:nowrap">Pilih menu</label>
    <select id="pilih-menu" style="max-width:360px" onchange="if(this.value)window.location.href=this.value">
      <?php foreach ($semuaMenu as $m): ?>
        <?php $label = e($m['nama']) . ' · ' . e($m['kategori']) . ((int)$m['diarsipkan'] === 1 ? ' (Diarsipkan)' : ''); ?>
        <option value="<?= e(base_url('resep/index.php?menu=' . (int)$m['id'])) ?>" <?= (int)$m['id'] === $menuId ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="grid" style="grid-template-columns:minmax(0, 62fr) minmax(320px, 38fr)">

  <!-- ======================================================== KIRI -->
  <div>
    <!-- Kartu menu terpilih -->
    <div class="kartu">
      <div class="baris-atas">
        <div style="display:flex;align-items:center;gap:14px">
          <?php if ($foto !== null): ?>
            <img src="<?= e(base_url($foto)) ?>" alt="<?= e($menuDipilih['nama']) ?>"
                 style="width:62px;height:62px;object-fit:cover;border-radius:10px;border:1px solid var(--border);background:var(--krem-2)">
          <?php else: ?>
            <div style="width:62px;height:62px;border-radius:10px;background:var(--krem-2);border:1px dashed var(--input-border);display:grid;place-items:center;color:var(--teks-muted)">
              <i data-lucide="coffee"></i>
            </div>
          <?php endif; ?>
          <div>
            <h2 style="font-size:20px"><?= e($menuDipilih['nama']) ?></h2>
            <p class="muted small">Resep untuk 1 porsi · Menu ID <?= $menuId ?></p>
          </div>
        </div>
        <?php if ((int)$menuDipilih['diarsipkan'] === 1): ?>
          <span class="badge badge-abu"><i data-lucide="archive"></i> Diarsipkan</span>
        <?php else: ?>
          <span class="badge badge-hijau"><i data-lucide="circle-check"></i> Aktif</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form resep -->
    <form method="post" action="<?= e(base_url('resep/simpan.php')) ?>" id="form-resep">
      <?= csrf_field() ?>
      <input type="hidden" name="menu_id" value="<?= $menuId ?>">

      <div class="kartu">
        <div class="kartu-head">
          <div>
            <h2>Komposisi bahan</h2>
            <p class="muted">Takaran × harga beli per satuan = subtotal HPP</p>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" id="tambah-bahan">
            <i data-lucide="plus"></i> Tambah bahan
          </button>
        </div>

        <div class="tabel-wrap">
          <table id="tabel-resep">
            <thead>
              <tr>
                <th style="width:34%">Bahan</th>
                <th style="width:16%">Takaran</th>
                <th style="width:12%">Unit</th>
                <th style="width:17%" class="kanan">Harga/unit</th>
                <th style="width:17%" class="kanan">Subtotal</th>
                <th style="width:4%" class="tengah">Hapus</th>
              </tr>
            </thead>
            <tbody id="badan-resep">
              <?php if (!$barisResep): ?>
                <?php
                  $pilihId = !empty($semuaBahan) ? (string)$semuaBahan[0]['id'] : '';
                  $pilihSatuan = !empty($semuaBahan) ? $semuaBahan[0]['satuan'] : '';
                  $pilihHarga = !empty($semuaBahan) ? (float)$semuaBahan[0]['harga_beli_per_satuan'] : 0.0;
                ?>
                <tr class="baris-resep">
                  <td>
                    <select name="bahan_id[]" class="pilih-bahan">
                      <?php foreach ($semuaBahan as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (string)$b['id'] === $pilihId ? 'selected' : '' ?>><?= e($b['nama']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="number" name="takaran[]" class="takaran" step="0.01" min="0" value="0"></td>
                  <td class="unit" data-satuan="<?= e($pilihSatuan) ?>"><?= e($pilihSatuan) ?></td>
                  <td class="kanan harga-unit"><?= rupiah($pilihHarga) ?></td>
                  <td class="kanan subtotal"><?= rupiah(0) ?></td>
                  <td class="tengah"><button type="button" class="btn btn-danger btn-sm btn-icon hapus-baris" title="Hapus baris"><i data-lucide="trash-2"></i></button></td>
                </tr>
              <?php else: ?>
                <?php foreach ($barisResep as $r): ?>
                  <tr class="baris-resep">
                    <td>
                      <select name="bahan_id[]" class="pilih-bahan">
                        <?php foreach ($semuaBahan as $b): ?>
                          <option value="<?= (int)$b['id'] ?>" data-satuan="<?= e($b['satuan']) ?>" data-harga="<?= (float)$b['harga_beli_per_satuan'] ?>" <?= (int)$b['id'] === (int)$r['bahan_id'] ? 'selected' : '' ?>><?= e($b['nama']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="number" name="takaran[]" class="takaran" step="0.01" min="0" value="<?= e(number_format((float)$r['takaran'], 2, '.', '')) ?>"></td>
                    <td class="unit" data-satuan="<?= e($r['satuan']) ?>"><?= e($r['satuan']) ?></td>
                    <td class="kanan harga-unit"><?= rupiah($r['harga_beli_per_satuan']) ?></td>
                    <td class="kanan subtotal"><?= rupiah((float)$r['takaran'] * (float)$r['harga_beli_per_satuan']) ?></td>
                    <td class="tengah"><button type="button" class="btn btn-danger btn-sm btn-icon hapus-baris" title="Hapus baris"><i data-lucide="trash-2"></i></button></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <hr class="hr">
        <div class="baris-atas">
          <div>
            <span class="eyebrow">HPP per porsi</span>
            <div id="hpp-tampil" class="angka" style="font-family:'Fraunces',serif;font-size:22px;color:var(--teks)"><?= rupiah($hpp) ?></div>
          </div>
          <span class="muted small">Dihitung otomatis dari komposisi di atas</span>
        </div>

        <div class="catatan-hijau" style="margin-top:16px">
          <i data-lucide="info"></i>
          <span style="font-size:12.5px">Takaran harus lebih dari 0 dan satuan mengikuti bahan. Bahan yang sama tidak boleh ditambahkan dua kali.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ======================================================== KANAN -->
  <div>
    <div class="kartu" style="position:sticky;top:88px">
      <div class="kartu-head"><div><h2>Harga & margin</h2><p class="muted">Simulasi keuntungan per porsi</p></div></div>

      <div class="form-group">
        <label for="harga">Harga jual / porsi</label>
        <div class="input-prefix">
          <span>Rp</span>
          <input type="number" name="harga" id="harga" form="form-resep" min="0" step="1" value="<?= e(number_format($hargaJual, 0, '.', '')) ?>">
        </div>
      </div>

      <div class="ringkasan" style="margin-top:6px">
        <div class="baris"><span class="muted">Harga jual</span><strong id="harga-tampil"><?= rupiah($hargaJual) ?></strong></div>
        <div class="baris"><span class="muted">HPP resep</span><strong id="hpp-ringkas"><?= rupiah($hpp) ?></strong></div>
        <div class="baris"><span class="muted">Laba kotor / porsi</span><strong id="laba-tampil"><?= rupiah($laba) ?></strong></div>
      </div>

      <div class="catatan-hijau" style="text-align:center;padding:20px;margin-top:18px">
        <span class="eyebrow">Margin kotor</span>
        <div id="margin-tampil" class="angka" style="font-family:'Fraunces',serif;font-size:40px;line-height:1.2"><?= $margin ?>%</div>
        <span class="small" style="opacity:.8">(harga jual − HPP) ÷ harga jual</span>
      </div>

      <div class="form-actions">
        <button type="submit" form="form-resep" class="btn btn-primary" style="flex:1">
          <i data-lucide="check"></i> Simpan resep
        </button>
        <a class="btn btn-secondary" href="<?= e(base_url('resep/index.php?menu=' . $menuId)) ?>">Batal</a>
      </div>

      <p class="muted small" style="margin-top:14px;line-height:1.5">
        HPP dan margin adalah kalkulasi rancangan. HPP setiap item disimpan sebagai snapshot saat penjualan.
      </p>
    </div>
  </div>

</div>

<!-- ============================================================ JS -->
<script>
(function () {
  'use strict';

  var dataBahan = <?= $jsonBahan ?>;

  var badan     = document.getElementById('badan-resep');
  var tombolTambah = document.getElementById('tambah-bahan');
  var inputHarga = document.getElementById('harga');

  function rupiah(angka) {
    return 'Rp' + Math.round(angka).toLocaleString('id-ID');
  }

  // Ambil data bahan dari select yang sedang dipilih (data JSON + atribut option).
  function infoBahan(select) {
    var id = select.value;
    if (dataBahan[id]) {
      return { satuan: dataBahan[id].satuan, harga: dataBahan[id].harga };
    }
    var opt = select.options[select.selectedIndex];
    return {
      satuan: opt ? (opt.getAttribute('data-satuan') || '') : '',
      harga: opt ? parseFloat(opt.getAttribute('data-harga') || '0') || 0 : 0
    };
  }

  function hitungUlangBaris(baris) {
    var select  = baris.querySelector('.pilih-bahan');
    var takaran = parseFloat(baris.querySelector('.takaran').value) || 0;
    var info    = infoBahan(select);
    var unitEl  = baris.querySelector('.unit');
    unitEl.textContent = info.satuan;
    unitEl.setAttribute('data-satuan', info.satuan);
    baris.querySelector('.harga-unit').textContent = rupiah(info.harga);
    baris.querySelector('.subtotal').textContent    = rupiah(takaran * info.harga);
  }

  function hitungSemua() {
    var total = 0;
    badan.querySelectorAll('.baris-resep').forEach(function (baris) {
      hitungUlangBaris(baris);
      var select  = baris.querySelector('.pilih-bahan');
      var takaran = parseFloat(baris.querySelector('.takaran').value) || 0;
      total += takaran * infoBahan(select).harga;
    });

    var harga = parseFloat(inputHarga.value) || 0;
    var laba  = harga - total;
    var margin = harga > 0 ? Math.round((laba / harga) * 100) : 0;

    document.getElementById('hpp-tampil').textContent  = rupiah(total);
    document.getElementById('hpp-ringkas').textContent = rupiah(total);
    document.getElementById('harga-tampil').textContent = rupiah(harga);
    document.getElementById('laba-tampil').textContent  = rupiah(laba);
    document.getElementById('margin-tampil').textContent = margin + '%';
  }

  // Delegasi event untuk select & takaran (termasuk baris hasil clone).
  badan.addEventListener('change', function (ev) {
    if (ev.target.classList.contains('pilih-bahan') || ev.target.classList.contains('takaran')) {
      hitungSemua();
    }
  });
  badan.addEventListener('input', function (ev) {
    if (ev.target.classList.contains('takaran')) hitungSemua();
  });
  inputHarga.addEventListener('input', hitungSemua);

  // Hapus baris (minimal satu baris tersisa).
  badan.addEventListener('click', function (ev) {
    var tombol = ev.target.closest('.hapus-baris');
    if (!tombol) return;
    if (badan.querySelectorAll('.baris-resep').length <= 1) {
      alert('Minimal satu bahan harus tetap ada.');
      return;
    }
    tombol.closest('.baris-resep').remove();
    hitungSemua();
  });

  // Tambah baris baru: clone baris pertama, pilih bahan pertama, takaran 0.
  tombolTambah.addEventListener('click', function () {
    var baris = badan.querySelector('.baris-resep');
    if (!baris) return;
    var clone = baris.cloneNode(true);
    var firstId = '';
    var firstOpt = clone.querySelector('.pilih-bahan').options[0];
    if (firstOpt) firstId = firstOpt.value;
    clone.querySelector('.pilih-bahan').value = firstId;
    clone.querySelector('.takaran').value = '0';
    badan.appendChild(clone);
    hitungSemua();
  });

  // Inisialisasi saat halaman dimuat.
  hitungSemua();
})();
</script>

<?php
layout_footer();
