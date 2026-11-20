<?php
require_once __DIR__ . '/includes/layout_owner.php';
require_role(['owner']);
$pdo = db();
$hariIni = date('Y-m-d');

// KPI
$st = $pdo->prepare("SELECT COALESCE(SUM(total),0) pendapatan, COUNT(*) trx FROM transaksi WHERE status='lunas' AND DATE(tanggal)=?");
$st->execute([$hariIni]); $kpi = $st->fetch();
$pendapatan = (float)$kpi['pendapatan']; $trx = (int)$kpi['trx'];
$rata = $trx ? $pendapatan / $trx : 0;
$kritis = bahan_kritis();

$jumlahTransaksiSemua = (int)$pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn();

// Transaksi terbaru + ringkasan pesanan
$st = $pdo->prepare("SELECT t.*, u.nama_lengkap, u.nama AS nama_lama FROM transaksi t LEFT JOIN users u ON u.id=t.user_id WHERE t.status='lunas' AND DATE(t.tanggal)=? ORDER BY t.tanggal DESC, t.id DESC LIMIT 5");
$st->execute([$hariIni]); $terbaru = $st->fetchAll();

// Menu terlaris hari ini
$st = $pdo->prepare("SELECT m.nama, SUM(d.qty) porsi FROM detail_transaksi d JOIN transaksi t ON t.id=d.transaksi_id JOIN menu m ON m.id=d.menu_id WHERE t.status='lunas' AND DATE(t.tanggal)=? GROUP BY m.id ORDER BY porsi DESC LIMIT 1");
$st->execute([$hariIni]); $terlaris = $st->fetch();

layout_header('Dashboard', 'Ringkasan kedai hari ini, sebelum mulai meracik.', 'dashboard');
?>
<div class="baris-atas" style="margin-bottom:22px">
  <div><h1><?= e(sapaan()) ?>, <?= e(nama_depan()) ?>.</h1><p class="muted" style="margin-top:5px">Ringkasan kedai hari ini, sebelum mulai meracik.</p></div>
  <div class="baris-atas">
    <?php if ($jumlahTransaksiSemua === 0): ?><span class="badge badge-krem">Data contoh</span><?php endif; ?>
    <span class="small muted">Periode: Hari ini</span>
    <a class="btn btn-secondary btn-sm" href="<?= e(base_url('laporan/index.php')) ?>">↗ Lihat laporan</a>
  </div>
</div>

<div class="grid grid-4">
  <div class="kartu kpi"><span class="kpi-ikon" data-lucide="wallet"></span><span class="label">Pendapatan hari ini</span><span class="angka"><?= e(rupiah($pendapatan)) ?></span><span class="sub">Total penjualan <?= $trx ?> transaksi</span></div>
  <div class="kartu kpi"><span class="kpi-ikon" data-lucide="receipt"></span><span class="label">Transaksi</span><span class="angka"><?= $trx ?></span><span class="sub">Pesanan selesai hari ini</span></div>
  <div class="kartu kpi"><span class="kpi-ikon" data-lucide="calculator"></span><span class="label">Rata-rata transaksi</span><span class="angka"><?= e(rupiah($rata)) ?></span><span class="sub">Pendapatan ÷ jumlah transaksi</span></div>
  <div class="kartu kpi"><span class="kpi-ikon" data-lucide="alert-triangle"></span><span class="label">Bahan di bawah minimum</span><span class="angka"><?= count($kritis) ?></span><span class="sub">Perlu dibeli untuk persediaan</span></div>
</div>

<div class="grid grid-32 mt20">
  <div class="kartu">
    <div class="kartu-head"><div><h2>Penjualan lima hari</h2><p id="rentangGrafik">Pendapatan 5 hari terakhir</p></div><a class="btn btn-secondary btn-sm" href="<?= e(base_url('laporan/index.php?tab=pendapatan')) ?>">Detail →</a></div>
    <div class="grafik-wrap"><canvas id="grafik5hari"></canvas></div>
    <div class="baris-atas" style="border-top:1px solid var(--border);padding-top:12px;margin-top:14px">
      <span class="small muted">Total 5 hari</span><span><strong id="total5hari" class="font-judul">Rp0</strong> <span class="small muted" id="total5trx">· 0 transaksi</span></span>
    </div>
  </div>
  <div class="kartu">
    <div class="kartu-head"><div><h2>Perlu perhatian</h2><p><?= count($kritis) ?> bahan di bawah stok minimum</p></div></div>
    <?php if ($kritis): ?><div style="margin-bottom:12px">
      <?php foreach (array_slice($kritis, 0, 5) as $b): ?>
        <div class="baris-atas" style="padding:7px 0;border-bottom:1px solid var(--border)">
          <span><strong style="color:var(--teks)"><?= e($b['nama']) ?></strong><br><span class="small muted"><?= e(rtrim(rtrim(number_format((float)$b['stok'],0,',','.'),'0'),'.')) ?> / <?= e(number_format((float)$b['stok_minimum'],0,',','.')) ?> <?= e($b['satuan']) ?></span></span>
          <span class="badge badge-merah">Kritis</span>
        </div>
      <?php endforeach; ?>
    </div><?php else: ?><p class="small muted" style="margin-bottom:12px">Semua bahan dalam kondisi aman.</p><?php endif; ?>
    <a class="btn btn-secondary btn-sm btn-block" href="<?= e(base_url('bahan/index.php')) ?>">Lihat stok →</a>
    <div class="catatan-hijau" style="margin-top:16px"><span class="eyebrow">MENU TERLARIS HARI INI</span>
      <?php if ($terlaris): ?><strong><?= e($terlaris['nama']) ?> · <?= (int)$terlaris['porsi'] ?> porsi</strong>
      <?php else: ?><strong>Belum ada penjualan hari ini</strong><?php endif; ?>
    </div>
  </div>
</div>

<div class="kartu mt20">
  <div class="kartu-head"><div><h2>Transaksi terbaru</h2><p>Riwayat transaksi kasir</p></div><?php if (in_array($_SESSION['user']['role'], ['owner'], true)): ?><a class="btn btn-secondary btn-sm" href="<?= e(base_url('kasir/index.php')) ?>">Buka kasir →</a><?php endif; ?></div>
  <div class="tabel-wrap"><table><thead><tr><th>Kode transaksi</th><th>Waktu</th><th>Pesanan</th><th class="kanan">Total</th><th>Metode / status</th></tr></thead><tbody>
    <?php if (!$terbaru): ?><tr><td colspan="5" class="tengah muted">Belum ada transaksi hari ini.</td></tr><?php endif; ?>
    <?php foreach ($terbaru as $t):
      $st2 = $pdo->prepare("SELECT m.nama, d.qty FROM detail_transaksi d JOIN menu m ON m.id=d.menu_id WHERE d.transaksi_id=?"); $st2->execute([$t['id']]); $items=$st2->fetchAll();
      $ringkasan=[]; foreach($items as $it){ $ringkasan[]=$it['qty'].' '.$it['nama']; }
    ?>
      <tr><td><strong style="color:var(--teks)"><?= e($t['kode']) ?></strong></td><td><?= e(date('H.i', strtotime($t['tanggal']))) ?></td><td><?= e(implode(' + ', $ringkasan) ?: '—') ?></td><td class="kanan"><?= e(rupiah($t['total'])) ?></td><td><span class="badge badge-hijau"><?= e(ucfirst($t['metode_bayar'])) ?> · Berhasil</span></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="mt20"><p class="eyebrow" style="margin-bottom:12px">AKSES CEPAT</p>
  <div class="akses-grid">
    <a class="akses-item" href="<?= e(base_url('menu/index.php?aksi=tambah')) ?>"><span data-lucide="plus-circle"></span> + Tambah menu</a>
    <a class="akses-item" href="<?= e(base_url('bahan/index.php?aksi=stok_masuk')) ?>"><span data-lucide="package-plus"></span> Catat stok masuk</a>
    <a class="akses-item" href="<?= e(base_url('resep/index.php')) ?>"><span data-lucide="flask-conical"></span> Edit resep &amp; HPP</a>
  </div>
</div>

<script>
fetch('<?= e(base_url('api/penjualan_5hari.php')) ?>').then(r=>r.json()).then(j=>{
  document.getElementById('rentangGrafik').textContent='Pendapatan '+j.rentang;
  document.getElementById('total5hari').textContent='Rp'+j.total_pendapatan.toLocaleString('id-ID');
  document.getElementById('total5trx').textContent='· '+j.total_transaksi+' transaksi';
  const singkat=n=>{ if(n>=1e6) return (n/1e6).toFixed(1).replace('.',',')+' jt'; if(n>=1e3) return (n/1e3).toFixed(0)+' rb'; return String(n); };
  new Chart(document.getElementById('grafik5hari'),{
    type:'bar',
    data:{labels:j.data.map(x=>x.label),datasets:[{data:j.data.map(x=>x.pendapatan),
      backgroundColor:j.data.map((_,i)=>i===j.data.length-1?'#5C3317':'#D9B98E'),borderRadius:6,barPercentage:.55}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},
      tooltip:{callbacks:{label:c=>'Rp'+Number(c.raw).toLocaleString('id-ID')+' · '+j.data[c.dataIndex].transaksi+' transaksi'}},
      datalabels:false},
      scales:{y:{beginAtZero:true,ticks:{callback:v=>'Rp'+singkat(v)}},x:{grid:{display:false}}}}
  });
  // Label nilai di atas bar (tanpa plugin tambahan)
  setTimeout(()=>{
    const c=document.getElementById('grafik5hari'), ctx=c.getContext('2d');
    const chart=Chart.getChart(c); if(!chart)return;
    chart.data.datasets[0].data.forEach((v,i)=>{
      const meta=chart.getDatasetMeta(0), bar=meta.data[i]; if(!bar)return;
      ctx.fillStyle='#3B1F0E';ctx.font='600 11px Inter,Arial';ctx.textAlign='center';
      ctx.fillText('Rp'+Math.round(v).toLocaleString('id-ID'),bar.x,bar.y-6);
    });
  },250);
}).catch(e=>console.error(e));
</script>
<?php layout_footer(); ?>
