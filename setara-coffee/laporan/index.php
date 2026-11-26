<?php
/**
 * laporan/index.php — Laporan operasional Café Setara.
 * Tab: stok | pendapatan (default) | cashflow, dengan filter periode dan ekspor PDF.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout_owner.php';

require_role(['owner']);

$pdo = db();

/* ------------------------------------------------------------ Filter GET */
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'pendapatan';
if (!in_array($tab, ['stok', 'pendapatan', 'cashflow'], true)) {
    $tab = 'pendapatan';
}

function laporan_tanggal_valid($s): bool
{
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

$mulai = isset($_GET['mulai']) && laporan_tanggal_valid($_GET['mulai']) ? (string)$_GET['mulai'] : date('Y-m-01');
$sampai = isset($_GET['sampai']) && laporan_tanggal_valid($_GET['sampai']) ? (string)$_GET['sampai'] : date('Y-m-d');
$tampilkanPesanTanggal = isset($_GET['mulai'], $_GET['sampai'])
    && laporan_tanggal_valid($_GET['mulai'])
    && laporan_tanggal_valid($_GET['sampai'])
    && strtotime((string)$_GET['sampai']) < strtotime((string)$_GET['mulai']);
if ($tampilkanPesanTanggal) {
    $sampai = $mulai;
}

$pengelompokan = isset($_GET['pengelompokan']) ? (string)$_GET['pengelompokan'] : 'harian';
if (!in_array($pengelompokan, ['harian', 'mingguan', 'bulanan'], true)) {
    $pengelompokan = 'harian';
}

$filter = [
    'tab'           => $tab,
    'mulai'         => $mulai,
    'sampai'        => $sampai,
    'pengelompokan' => $pengelompokan,
];
$pdfFilter = [
    'jenis'          => $tab,
    'tanggal_mulai'  => $mulai,
    'tanggal_akhir'  => $sampai,
    'pengelompokan'  => $pengelompokan,
];
$urlExport = base_url('laporan/pdf.php?' . http_build_query($pdfFilter));

function laporan_url_tab(string $tabTarget, array $filter): string
{
    $q = $filter;
    $q['tab'] = $tabTarget;
    return base_url('laporan/index.php?' . http_build_query($q));
}

/** Nama bulan Indonesia (pendek atau lengkap). */
function laporan_nama_bulan(int $bulan, bool $pendek = false): string
{
    $lengkap = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $pendekArr = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $daftar = $pendek ? $pendekArr : $lengkap;
    return $daftar[$bulan] ?? '';
}

$rentangTeks = tanggal_indonesia($mulai, false) . ' – ' . tanggal_indonesia($sampai, false);
$mulaiBesok = date('Y-m-d', strtotime($sampai . ' +1 day'));

/* ----------------------------------------------------- Data: pendapatan */
$kpi = ['pendapatan' => 0.0, 'jumlah' => 0, 'hpp' => 0.0, 'laba' => 0.0];
$rincian = [];

if ($tab === 'pendapatan') {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total),0) AS pendapatan,
                COUNT(*) AS jumlah,
                COALESCE(SUM(total_hpp),0) AS hpp
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $baris = $st->fetch();
    $kpi['pendapatan'] = (float)($baris['pendapatan'] ?? 0);
    $kpi['jumlah'] = (int)($baris['jumlah'] ?? 0);
    $kpi['hpp'] = (float)($baris['hpp'] ?? 0);
    $kpi['laba'] = $kpi['pendapatan'] - $kpi['hpp'];

    if ($pengelompokan === 'mingguan') {
        $sql = "SELECT YEARWEEK(tanggal,1) AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY YEARWEEK(tanggal,1)
                 ORDER BY kunci";
    } elseif ($pengelompokan === 'bulanan') {
        $sql = "SELECT DATE_FORMAT(tanggal,'%Y-%m') AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY DATE_FORMAT(tanggal,'%Y-%m')
                 ORDER BY kunci";
    } else {
        $sql = "SELECT DATE(tanggal) AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY DATE(tanggal)
                 ORDER BY kunci";
    }
    $st = $pdo->prepare($sql);
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    foreach ($st->fetchAll() as $r) {
        $contoh = $r['contoh'] ?: $mulai;
        $ts = strtotime((string)$contoh);
        if ($pengelompokan === 'mingguan') {
            $label = 'Minggu ke-' . date('W', $ts) . ' (' . date('j', $ts) . ' ' . laporan_nama_bulan((int)date('n', $ts), true) . ')';
        } elseif ($pengelompokan === 'bulanan') {
            $label = laporan_nama_bulan((int)date('n', $ts)) . ' ' . date('Y', $ts);
        } else {
            $label = tanggal_indonesia((string)$r['kunci'], false);
        }
        $rincian[] = [
            'label'      => $label,
            'jumlah'     => (int)$r['jumlah'],
            'pendapatan' => (float)$r['pendapatan'],
            'hpp'        => (float)$r['hpp'],
            'laba'       => (float)$r['pendapatan'] - (float)$r['hpp'],
        ];
    }
}

/* ------------------------------------------------------------ Data: stok */
$bahan = [];
$kritis = [];
if ($tab === 'stok') {
    foreach ($pdo->query('SELECT nama, satuan, stok, stok_minimum FROM bahan ORDER BY nama')->fetchAll() as $b) {
        $stok = (float)$b['stok'];
        $min = (float)$b['stok_minimum'];
        if ($stok <= $min) {
            $status = 'Kritis';
            $kelas = 'badge-merah';
        } elseif ($stok <= $min * 1.5) {
            $status = 'Waspada';
            $kelas = 'badge-kuning';
        } else {
            $status = 'Aman';
            $kelas = 'badge-hijau';
        }
        $bahan[] = [
            'nama' => (string)$b['nama'],
            'satuan' => (string)$b['satuan'],
            'stok' => $stok,
            'minimum' => $min,
            'status' => $status,
            'kelas' => $kelas,
        ];
    }
    foreach (bahan_kritis() as $b) {
        $kritis[] = (string)$b['nama'];
    }
}

/* -------------------------------------------------------- Data: cashflow */
$kasData = ['pendapatan' => 0.0, 'pembelian' => 0.0, 'operasional' => 0.0, 'saldo' => 0.0];
if ($tab === 'cashflow') {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total),0) FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $kasData['pendapatan'] = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah),0) FROM kas
          WHERE tipe = 'keluar' AND kategori = 'Pembelian bahan'
            AND tanggal >= ? AND tanggal < ?"
    );
    $st->execute([$mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $kasData['pembelian'] = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah),0) FROM kas
          WHERE tipe = 'keluar' AND (kategori <> 'Pembelian bahan' OR kategori IS NULL)
            AND tanggal >= ? AND tanggal < ?"
    );
    $st->execute([$mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $kasData['operasional'] = (float)$st->fetchColumn();

    $kasData['saldo'] = $kasData['pendapatan'] - $kasData['pembelian'] - $kasData['operasional'];
}

/* ------------------------------------------------------------------ View */
layout_header('Laporan operasional', 'Tinjau pendapatan, kondisi bahan, dan arus kas dalam satu periode.', 'laporan');
?>

<div class="baris-atas mb16">
  <div class="tabs" style="margin-bottom:0;flex:1">
    <a class="tab <?= $tab === 'stok' ? 'aktif' : '' ?>" href="<?= e(laporan_url_tab('stok', $filter)) ?>">Stok</a>
    <a class="tab <?= $tab === 'pendapatan' ? 'aktif' : '' ?>" href="<?= e(laporan_url_tab('pendapatan', $filter)) ?>">Pendapatan</a>
    <a class="tab <?= $tab === 'cashflow' ? 'aktif' : '' ?>" href="<?= e(laporan_url_tab('cashflow', $filter)) ?>">Cash flow</a>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= e($urlExport) ?>" target="_blank" rel="noopener"><i data-lucide="file-text"></i> Lihat PDF</a>
    <a class="btn btn-secondary" href="<?= e($urlExport . '&mode=download') ?>"><i data-lucide="download"></i> Unduh PDF</a>
  </div>
</div>

<!-- ============================================================ FILTER -->
<form class="kartu" method="get" action="<?= e(base_url('laporan/index.php')) ?>" style="margin-bottom:20px">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php if ($tampilkanPesanTanggal): ?>
    <p class="flash flash-warning" style="margin:0 0 14px"><?= e('Tanggal akhir tidak boleh lebih awal dari Tanggal mulai.') ?></p>
  <?php endif; ?>
  <div class="filter-bar" style="margin-bottom:0">
    <div style="min-width:180px">
      <label for="mulai">Tanggal mulai</label>
      <input type="date" id="mulai" name="mulai" value="<?= e($mulai) ?>">
    </div>
    <div style="min-width:180px">
      <label for="sampai">Tanggal akhir</label>
      <input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>">
    </div>
    <div style="min-width:160px">
      <label for="pengelompokan">Pengelompokan</label>
      <select id="pengelompokan" name="pengelompokan" <?= $tab !== 'pendapatan' ? 'disabled' : '' ?>>
        <option value="harian" <?= $pengelompokan === 'harian' ? 'selected' : '' ?>>Harian</option>
        <option value="mingguan" <?= $pengelompokan === 'mingguan' ? 'selected' : '' ?>>Mingguan</option>
        <option value="bulanan" <?= $pengelompokan === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
      </select>
      <?php if ($tab !== 'pendapatan'): ?>
        <input type="hidden" name="pengelompokan" value="<?= e($pengelompokan) ?>">
      <?php endif; ?>
    </div>
    <div style="align-self:flex-end">
      <button class="btn btn-primary" type="submit"><i data-lucide="funnel"></i> Terapkan filter</button>
    </div>
  </div>
</form>

<script>
  (function () {
    var form = document.querySelector('form[action$="laporan/index.php"]');
    if (!form) { return; }
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (!btn) { return; }
      btn.disabled = true;
      btn.querySelectorAll('i[data-lucide]').forEach(function (el) { el.remove(); });
      btn.textContent = 'Memuat...';
    });
  })();
</script>

<?php if ($tab === 'pendapatan'): ?>
  <!-- =================================================== KPI PENDAPATAN -->
  <div class="grid grid-4" style="margin-bottom:20px">
    <div class="kartu kpi">
      <span class="label">Pendapatan</span>
      <span class="angka"><?= e(rupiah($kpi['pendapatan'])) ?></span>
      <span class="sub">Transaksi lunas dalam periode ini</span>
      <span class="kpi-ikon"><i data-lucide="banknote"></i></span>
    </div>
    <div class="kartu kpi">
      <span class="label">Total transaksi</span>
      <span class="angka"><?= $kpi['jumlah'] ?></span>
      <span class="sub">Rata-rata <?= e(rupiah($kpi['jumlah'] > 0 ? $kpi['pendapatan'] / $kpi['jumlah'] : 0)) ?> / transaksi</span>
      <span class="kpi-ikon"><i data-lucide="receipt"></i></span>
    </div>
    <div class="kartu kpi">
      <span class="label">HPP penjualan</span>
      <span class="angka"><?= e(rupiah($kpi['hpp'])) ?></span>
      <span class="sub">Snapshot HPP saat transaksi terjadi</span>
      <span class="kpi-ikon"><i data-lucide="layers"></i></span>
    </div>
    <div class="kartu kpi">
      <span class="label">Laba kotor</span>
      <span class="angka"><?= e(rupiah($kpi['laba'])) ?></span>
      <span class="sub"><?= $kpi['pendapatan'] > 0 ? round(($kpi['laba'] / $kpi['pendapatan']) * 100) : 0 ?>% dari pendapatan</span>
      <span class="kpi-ikon"><i data-lucide="trending-up"></i></span>
    </div>
  </div>

  <!-- ====================================================== RINCIAN -->
  <div class="kartu">
    <div class="kartu-head">
      <div>
        <h2>Rincian pendapatan <?= e(ucfirst($pengelompokan)) ?></h2>
        <p class="muted"><?= e('Menampilkan ' . date('j M Y', strtotime($mulai)) . ' – ' . date('j M Y', strtotime($sampai)) . ', ' . ucfirst($pengelompokan)) ?></p>
        <p class="muted"><?= e($rentangTeks) ?></p>
      </div>
      <a class="btn btn-secondary btn-sm" href="<?= e($urlExport) ?>">Cetak ringkasan →</a>
    </div>
    <div class="tabel-wrap">
      <table>
        <thead>
          <tr>
            <th>Periode</th>
            <th class="kanan">Transaksi</th>
            <th class="kanan">Pendapatan</th>
            <th class="kanan">HPP</th>
            <th class="kanan">Laba</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rincian): ?>
            <tr><td colspan="5" class="tengah muted">Tidak ada transaksi lunas pada periode ini.</td></tr>
          <?php else: ?>
            <?php foreach ($rincian as $r): ?>
              <tr>
                <td><?= e($r['label']) ?></td>
                <td class="kanan"><?= $r['jumlah'] ?></td>
                <td class="kanan"><?= e(rupiah($r['pendapatan'])) ?></td>
                <td class="kanan"><?= e(rupiah($r['hpp'])) ?></td>
                <td class="kanan"><?= e(rupiah($r['laba'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          <tr class="tabel-total">
            <td>TOTAL</td>
            <td class="kanan"><?= $kpi['jumlah'] ?></td>
            <td class="kanan"><?= e(rupiah($kpi['pendapatan'])) ?></td>
            <td class="kanan"><?= e(rupiah($kpi['hpp'])) ?></td>
            <td class="kanan"><?= e(rupiah($kpi['laba'])) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="margin-top:22px">
      <span class="eyebrow">Grafik pendapatan per periode</span>
      <div class="grafik-wrap"><canvas id="grafik-pendapatan"></canvas></div>
    </div>
  </div>

  <script>
  (function () {
    var canvas = document.getElementById('grafik-pendapatan');
    if (!canvas || !window.Chart) return;
    var labels = <?= json_encode(array_column($rincian, 'label'), JSON_UNESCAPED_UNICODE) ?>;
    var nilai  = <?= json_encode(array_map('floatval', array_column($rincian, 'pendapatan'))) ?>;
    var warna  = nilai.map(function (_i, idx) {
      return idx === nilai.length - 1 ? '#5C3317' : '#D9B98E';
    });
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Pendapatan',
          data: nilai,
          backgroundColor: warna,
          borderRadius: 5,
          maxBarThickness: 46
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return 'Pendapatan: Rp' + Math.round(ctx.parsed.y).toLocaleString('id-ID');
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: function (v) { return 'Rp' + Number(v).toLocaleString('id-ID'); } }
          }
        }
      }
    });
  })();
  </script>

<?php elseif ($tab === 'stok'): ?>
  <!-- ====================================================== STOK -->
  <div class="kartu">
    <div class="kartu-head">
      <div><h2>Ringkasan laporan stok</h2><p class="muted">Snapshot stok saat ini</p></div>
    </div>
    <div class="tabel-wrap">
      <table>
        <thead>
          <tr><th>Bahan</th><th class="kanan">Stok / minimum</th><th class="tengah">Status</th></tr>
        </thead>
        <tbody>
          <?php if (!$bahan): ?>
            <tr><td colspan="3" class="tengah muted">Belum ada bahan tercatat.</td></tr>
          <?php else: ?>
            <?php foreach ($bahan as $b): ?>
              <tr>
                <td><?= e($b['nama']) ?></td>
                <td class="kanan"><?= e(rupiah($b['stok'])) ?> <?= e($b['satuan']) ?> / <?= e(rupiah($b['minimum'])) ?> <?= e($b['satuan']) ?></td>
                <td class="tengah"><span class="badge <?= e($b['kelas']) ?>"><?= e($b['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="<?= $kritis ? 'banner-kuning' : 'catatan-hijau' ?>" style="margin-top:18px;margin-bottom:0">
      <?php if ($kritis): ?>
        <strong>Perlu segera dibeli</strong>
        <?= e(implode(', ', $kritis)) ?><?= count($kritis) > 1 ? ' dan bahan kritis lainnya' : '' ?> perlu segera dibeli.
      <?php else: ?>
        <strong>Stok aman</strong>
        Semua bahan dalam kondisi aman.
      <?php endif; ?>
    </div>
    <div style="margin-top:18px">
      <a class="btn btn-secondary" href="<?= e(base_url('bahan/index.php')) ?>"><i data-lucide="boxes"></i> Buka laporan stok</a>
    </div>
  </div>

<?php else: ?>
  <!-- ===================================================== CASH FLOW -->
  <div class="kartu">
    <div class="kartu-head">
      <div><h2>Cash flow</h2><p class="muted">Arus kas dalam periode terpilih · <?= e($rentangTeks) ?></p></div>
    </div>
    <div class="tabel-wrap">
      <table>
        <thead>
          <tr><th>Komponen</th><th>Arus</th><th class="kanan">Jumlah</th></tr>
        </thead>
        <tbody>
          <tr><td>Pendapatan</td><td><span class="badge badge-hijau">Masuk</span></td><td class="kanan"><?= e(rupiah($kasData['pendapatan'])) ?></td></tr>
          <tr><td>Pembelian bahan</td><td><span class="badge badge-merah">Keluar</span></td><td class="kanan"><?= e(rupiah($kasData['pembelian'])) ?></td></tr>
          <tr><td>Operasional</td><td><span class="badge badge-merah">Keluar</span></td><td class="kanan"><?= e(rupiah($kasData['operasional'])) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="catatan-hijau" style="margin-top:18px;text-align:center;padding:22px">
      <span class="eyebrow">Saldo periode</span>
      <div class="angka" style="font-size:36px"><?= e(rupiah($kasData['saldo'])) ?></div>
    </div>
    <p class="muted small" style="margin-top:14px">
      Saldo periode tersebut bukan saldo kas riil; belum termasuk modal awal dan HPP non-kas.
    </p>
  </div>

  <div class="kartu">
    <div class="kartu-head"><div><h2>Catat pengeluaran operasional</h2><p class="muted">Pengeluaran dicatat sebagai kas keluar.</p></div></div>
    <form method="post" action="<?= e(base_url('laporan/simpan_kas.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="mulai" value="<?= e($mulai) ?>">
      <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
      <input type="hidden" name="pengelompokan" value="<?= e($pengelompokan) ?>">
      <div class="form-grid">
        <div class="form-group">
          <label for="kas-tanggal">Tanggal</label>
          <input type="date" id="kas-tanggal" name="tanggal" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
          <label for="kas-kategori">Kategori</label>
          <input type="text" id="kas-kategori" name="kategori" placeholder="Contoh: Listrik" maxlength="50" required>
        </div>
        <div class="form-group">
          <label for="kas-jumlah">Jumlah (Rp)</label>
          <input type="number" id="kas-jumlah" name="jumlah" min="1" step="1" placeholder="0" required>
        </div>
        <div class="form-group">
          <label for="kas-keterangan">Keterangan</label>
          <input type="text" id="kas-keterangan" name="keterangan" placeholder="Opsional" maxlength="255">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><i data-lucide="save"></i> Simpan</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php
layout_footer();
