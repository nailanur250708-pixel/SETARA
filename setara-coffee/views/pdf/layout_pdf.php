<?php
/**
 * views/pdf/layout_pdf.php — Kop & kerangka laporan PDF Café Setara.
 * Variables: $judul, $periodeTeks, $namaPencetak, $waktuCetak, $konten (HTML string).
 * CSS inline, table-based, TANPA flexbox/grid (kompatibel dompdf).
 */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= e($judul) ?> · Café Setara</title>
<style>
  html, body { margin: 0; padding: 0; }
  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9pt;
    line-height: 1.5;
    color: #3B1F0E;
    background: #FFFFFF;
  }
  .kop { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .kop td { padding: 0; vertical-align: top; }
  .logo-teks { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 19pt; font-weight: bold; color: #5C3317; }
  .logo-sub { font-size: 7pt; letter-spacing: 1.4px; color: #8A7A6B; font-weight: bold; margin-top: 2px; }
  .kop-kanan { text-align: right; font-size: 8pt; color: #8A7A6B; }
  .kop-kanan strong { color: #3B1F0E; font-size: 9pt; }
  .garis { border-top: 2px solid #5C3317; margin: 0 0 14px 0; height: 0; }
  h1.judul { font-size: 16pt; color: #3B1F0E; margin: 0 0 4px 0; font-weight: bold; }
  p.periode { font-size: 9pt; color: #5A4A3F; margin: 0 0 4px 0; }
  p.cetak { font-size: 8pt; color: #8A7A6B; margin: 0 0 16px 0; }
  table.tabel { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  table.tabel th {
    background: #F4EBDD; text-align: left; font-size: 7.5pt; text-transform: uppercase;
    letter-spacing: .5px; color: #3B1F0E; font-weight: bold; padding: 6px 7px;
    border: 1px solid #E6DCCA;
  }
  table.tabel td { padding: 6px 7px; border: 1px solid #E6DCCA; vertical-align: middle; }
  table.tabel tr.total td { background: #F4EBDD; font-weight: bold; color: #3B1F0E; }
  .kanan { text-align: right; }
  .tengah { text-align: center; }
  .muted { color: #8A7A6B; }
  table.kpi { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  table.kpi td {
    width: 25%; border: 1px solid #E6DCCA; background: #FBF6EE;
    padding: 9px 10px; vertical-align: top;
  }
  .kpi-label { font-size: 7pt; text-transform: uppercase; letter-spacing: .6px; color: #8A7A6B; font-weight: bold; }
  .kpi-angka { font-size: 14pt; color: #3B1F0E; font-weight: bold; margin-top: 3px; }
  .kpi-sub { font-size: 7pt; color: #8A7A6B; margin-top: 2px; }
  .kotak { border: 1px solid #E6DCCA; background: #FBF6EE; padding: 10px 12px; margin-bottom: 12px; }
  .kotak-hijau { border: 1px solid #BBD7AE; background: #E8F0E0; padding: 12px; margin-bottom: 12px; }
  .kotak-kuning { border: 1px solid #E8D49B; background: #FFF4D6; padding: 10px 12px; margin-bottom: 12px; }
  .kotak strong { display: block; margin-bottom: 3px; }
  .saldo-label { font-size: 8pt; color: #2F6B3A; text-align: center; }
  .saldo-angka { font-size: 20pt; font-weight: bold; color: #2F6B3A; text-align: center; margin-top: 3px; }
  .badge { font-size: 7pt; font-weight: bold; padding: 2px 6px; border: 1px solid #E6DCCA; }
  .badge-hijau { color: #2F6B3A; background: #E8F0E0; border-color: #BBD7AE; }
  .badge-merah { color: #B42318; background: #FBE4E1; border-color: #EDC2BC; }
  .badge-kuning { color: #8A5A00; background: #FFF4D6; border-color: #E8D49B; }
  .catatan { font-size: 7.5pt; color: #8A7A6B; margin-top: 10px; }
  .tanda-tangan { width: 100%; border-collapse: collapse; margin-top: 34px; }
  .tanda-tangan td { width: 50%; text-align: center; padding: 0 14px; font-size: 8pt; color: #8A7A6B; }
  .garis-ttd { border-top: 1px solid #8A7A6B; margin-bottom: 5px; height: 26px; }
</style>
</head>
<body>

<table class="kop">
  <tr>
    <td>
      <div class="logo-teks">Café Setara</div>
      <div class="logo-sub">RUANG KELOLA KEDAI</div>
    </td>
    <td class="kop-kanan">
      <strong>Laporan Operasional</strong><br>
      Jl. Towerkanjovank, Tamalanrea Indah,<br>
      Kec. Tamalanrea, Kota Makassar,<br>
      Sulawesi Selatan 90245
    </td>
  </tr>
</table>
<div class="garis"></div>

<h1 class="judul"><?= e($judul) ?></h1>
<p class="periode">Periode: <?= e($periodeTeks) ?></p>
<p class="cetak">Dicetak oleh <?= e($namaPencetak) ?> · <?= e($waktuCetak) ?></p>

<?= $konten ?>

<table class="tanda-tangan">
  <tr>
    <td><div class="garis-ttd"></div>Penanggung jawab</td>
    <td><div class="garis-ttd"></div><?= e($namaPencetak) ?></td>
  </tr>
</table>

</body>
</html>
