<?php
/**
 * views/pdf/cashflow.php — Isi laporan arus kas untuk PDF.
 * Variables: $data (pendapatan, pembelian, operasional, saldo), $rentangTeks.
 */
?>
<div class="kotak">
  <strong>Cash flow</strong>
  Arus kas dalam periode terpilih · <?= e($rentangTeks) ?>
</div>

<table class="tabel">
  <thead>
    <tr>
      <th style="width:46%">Komponen</th>
      <th class="tengah" style="width:20%">Arus</th>
      <th class="kanan" style="width:34%">Jumlah</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Pendapatan</td>
      <td class="tengah"><span class="badge badge-hijau">Masuk</span></td>
      <td class="kanan"><?= e(rupiah($data['pendapatan'])) ?></td>
    </tr>
    <tr>
      <td>Pembelian bahan</td>
      <td class="tengah"><span class="badge badge-merah">Keluar</span></td>
      <td class="kanan"><?= e(rupiah($data['pembelian'])) ?></td>
    </tr>
    <tr>
      <td>Operasional</td>
      <td class="tengah"><span class="badge badge-merah">Keluar</span></td>
      <td class="kanan"><?= e(rupiah($data['operasional'])) ?></td>
    </tr>
    <tr class="total">
      <td colspan="2">Saldo periode</td>
      <td class="kanan"><?= e(rupiah($data['saldo'])) ?></td>
    </tr>
  </tbody>
</table>

<div class="kotak-hijau">
  <div class="saldo-label">Saldo periode</div>
  <div class="saldo-angka"><?= e(rupiah($data['saldo'])) ?></div>
</div>

<p class="catatan">
  Saldo periode tersebut bukan saldo kas riil; belum termasuk modal awal dan HPP non-kas.
</p>
