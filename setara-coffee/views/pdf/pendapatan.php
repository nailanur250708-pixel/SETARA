<?php
/**
 * views/pdf/pendapatan.php — Isi laporan pendapatan untuk PDF.
 * Variables: $kpi, $data, $pengelompokan, $rentangTeks.
 */
$judulGrup = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'][$pengelompokan] ?? 'Harian';
?>
<table class="kpi">
  <tr>
    <td>
      <div class="kpi-label">Pendapatan</div>
      <div class="kpi-angka"><?= e(rupiah($kpi['pendapatan'])) ?></div>
      <div class="kpi-sub">Transaksi lunas dalam periode ini</div>
    </td>
    <td>
      <div class="kpi-label">Total transaksi</div>
      <div class="kpi-angka"><?= $kpi['jumlah'] ?></div>
      <div class="kpi-sub">Rata-rata <?= e(rupiah($kpi['jumlah'] > 0 ? $kpi['pendapatan'] / $kpi['jumlah'] : 0)) ?> / transaksi</div>
    </td>
    <td>
      <div class="kpi-label">HPP penjualan</div>
      <div class="kpi-angka"><?= e(rupiah($kpi['hpp'])) ?></div>
      <div class="kpi-sub">Snapshot HPP saat transaksi</div>
    </td>
    <td>
      <div class="kpi-label">Laba kotor</div>
      <div class="kpi-angka"><?= e(rupiah($kpi['laba'])) ?></div>
      <div class="kpi-sub"><?= $kpi['pendapatan'] > 0 ? round(($kpi['laba'] / $kpi['pendapatan']) * 100) : 0 ?>% dari pendapatan</div>
    </td>
  </tr>
</table>

<div class="kotak">
  <strong>Rincian pendapatan <?= e($judulGrup) ?></strong>
  <?= e($rentangTeks) ?>
</div>

<table class="tabel">
  <thead>
    <tr>
      <th style="width:32%">Periode</th>
      <th class="kanan" style="width:12%">Transaksi</th>
      <th class="kanan" style="width:20%">Pendapatan</th>
      <th class="kanan" style="width:18%">HPP</th>
      <th class="kanan" style="width:18%">Laba</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$data['rincian']): ?>
      <tr><td colspan="5" class="tengah muted">Tidak ada transaksi lunas pada periode ini.</td></tr>
    <?php else: ?>
      <?php foreach ($data['rincian'] as $r): ?>
        <tr>
          <td><?= e($r['label']) ?></td>
          <td class="kanan"><?= $r['jumlah'] ?></td>
          <td class="kanan"><?= e(rupiah($r['pendapatan'])) ?></td>
          <td class="kanan"><?= e(rupiah($r['hpp'])) ?></td>
          <td class="kanan"><?= e(rupiah($r['laba'])) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    <tr class="total">
      <td>TOTAL</td>
      <td class="kanan"><?= $kpi['jumlah'] ?></td>
      <td class="kanan"><?= e(rupiah($kpi['pendapatan'])) ?></td>
      <td class="kanan"><?= e(rupiah($kpi['hpp'])) ?></td>
      <td class="kanan"><?= e(rupiah($kpi['laba'])) ?></td>
    </tr>
  </tbody>
</table>

<p class="catatan">
  Angka dihitung dari transaksi berstatus lunas pada rentang tanggal tersebut. HPP mengikuti snapshot saat transaksi terjadi.
</p>
