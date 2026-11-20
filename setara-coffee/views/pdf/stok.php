<?php
/**
 * views/pdf/stok.php — Isi laporan stok untuk PDF.
 * Variables: $data (berisi 'bahan' dan 'kritis').
 */
?>
<div class="kotak">
  <strong>Ringkasan laporan stok</strong>
  Snapshot stok saat ini
</div>

<table class="tabel">
  <thead>
    <tr>
      <th style="width:40%">Bahan</th>
      <th class="kanan" style="width:38%">Stok / minimum</th>
      <th class="tengah" style="width:22%">Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$data['bahan']): ?>
      <tr><td colspan="3" class="tengah muted">Belum ada bahan tercatat.</td></tr>
    <?php else: ?>
      <?php foreach ($data['bahan'] as $b): ?>
        <tr>
          <td><?= e($b['nama']) ?></td>
          <td class="kanan"><?= e(rupiah($b['stok'])) ?> <?= e($b['satuan']) ?> / <?= e(rupiah($b['minimum'])) ?> <?= e($b['satuan']) ?></td>
          <td class="tengah"><span class="badge <?= e($b['kelas']) ?>"><?= e($b['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($data['kritis']): ?>
  <div class="kotak-kuning">
    <strong>Perlu segera dibeli</strong>
    <?= e(implode(', ', $data['kritis'])) ?><?= count($data['kritis']) > 1 ? ' dan bahan kritis lainnya' : '' ?> perlu segera dibeli.
  </div>
<?php else: ?>
  <div class="kotak-hijau">
    <strong>Stok aman</strong>
    Semua bahan dalam kondisi aman.
  </div>
<?php endif; ?>

<p class="catatan">
  Status kritis ditetapkan ketika stok sama dengan atau di bawah stok minimum. Status waspada ketika stok tidak lebih dari 150% stok minimum.
</p>
