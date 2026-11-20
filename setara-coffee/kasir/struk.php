<?php
require_once __DIR__ . '/../includes/layout_kasir.php';
require_role(['owner','kasir']);
$pdo = db();
$user = $_SESSION['user'];
$alamat = 'Jl. Towerkanjovank, Tamalanrea Indah, Kec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $st = $pdo->prepare('SELECT t.*, u.nama_lengkap, u.nama AS nama_lama FROM transaksi t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?');
    $st->execute([$id]); $t = $st->fetch();
    if (!$t) { flash('error','Transaksi tidak ditemukan.'); redirect('kasir/struk.php'); }
    if ($user['role']==='kasir' && (int)$t['user_id']!==(int)$user['id']) { render_403(); }
    $st = $pdo->prepare('SELECT d.*, m.nama menu_nama, p.kode promo_kode FROM detail_transaksi d JOIN menu m ON m.id=d.menu_id LEFT JOIN promo p ON p.id=d.promo_id WHERE d.transaksi_id=? ORDER BY d.id');
    $st->execute([$id]); $items = $st->fetchAll();
    kasir_header('struk', false);
    ?>
    <div class="baris-atas" style="max-width:520px;margin:0 auto 16px">
      <a class="kembali" href="<?= e(base_url('kasir/struk.php')) ?>">← Daftar struk</a>
      <div class="baris-atas">
        <?php if ($user['role']==='owner' && $t['status']==='lunas'): ?>
        <form method="post" action="<?= e(base_url('kasir/batalkan.php')) ?>" onsubmit="return confirm('Batalkan transaksi <?= e($t['kode']) ?>? Stok bahan akan dikembalikan.')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><i data-lucide="ban"></i> Batalkan</button>
        </form>
        <?php endif; ?>
        <button class="btn btn-secondary btn-sm" onclick="window.print()"><i data-lucide="printer"></i> Cetak struk</button>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('kasir/index.php')) ?>">Pesanan baru</a>
      </div>
    </div>
    <div class="struk-kartu" id="struk">
      <div style="text-align:center;margin-bottom:8px"><i data-lucide="check-circle-2" style="width:32px;height:32px;color:var(--sage-teks)"></i><p class="small" style="color:var(--sage-teks)">Pembayaran berhasil</p></div>
      <div class="struk-header">
        <div class="struk-logo">Café Setara</div><p class="eyebrow">RUANG KELOLA KEDAI</p>
        <p class="struk-alamat"><?= e($alamat) ?></p>
      </div>
      <div class="struk-baris"><span class="muted">Kode</span><span><strong><?= e($t['kode']) ?></strong></span></div>
      <div class="struk-baris"><span class="muted">Waktu</span><span><?= e(tanggal_indonesia($t['tanggal'])) ?>, <?= e(date('H:i',strtotime($t['tanggal']))) ?></span></div>
      <div class="struk-baris"><span class="muted">Kasir</span><span><?= e($t['nama_lengkap'] ?: $t['nama_lama']) ?> · Kasir</span></div>
      <div class="struk-baris"><span class="muted">Jenis</span><span><?= e($t['jenis_pesanan']) ?></span></div>
      <hr class="hr">
      <?php foreach($items as $it): ?>
        <div class="struk-baris"><span><?= (int)$it['qty'] ?> × <?= e($it['menu_nama']) ?><br><span class="small muted"><?= e(rupiah($it['harga_satuan'])) ?> / menu</span></span><span><?= e(rupiah($it['subtotal'] - $it['diskon_nilai'])) ?></span></div>
        <?php if((float)$it['diskon_nilai']>0): ?><div class="struk-hijau">&nbsp;&nbsp;↳ Voucher <?= e($it['promo_kode']) ?> · −<?= e(rupiah($it['diskon_nilai'])) ?></div><?php endif; ?>
      <?php endforeach; ?>
      <hr class="hr">
      <div class="struk-baris"><span>Subtotal</span><span><?= e(rupiah($t['subtotal'])) ?></span></div>
      <div class="struk-baris struk-hijau"><span>Diskon</span><span>−<?= e(rupiah($t['total_diskon'])) ?></span></div>
      <div class="struk-baris total"><span>Total</span><span><?= e(rupiah($t['total'])) ?></span></div>
      <div class="struk-baris"><span><?= e(ucfirst($t['metode_bayar'])) ?></span><span><?= e(rupiah($t['uang_diterima'])) ?></span></div>
      <div class="struk-baris"><span>Kembalian</span><span><?= e(rupiah($t['kembalian'])) ?></span></div>
      <p class="struk-pusat">Terima kasih. Sampai jumpa di cangkir berikutnya.</p>
      <?php if((float)$t['total_diskon']>0): ?><p class="struk-pusat">Satu promo per item. Diskon total <?= e(rupiah($t['total_diskon'])) ?>.</p><?php endif; ?>
      <span class="badge <?= $t['status']==='lunas'?'badge-hijau':'badge-merah' ?> lunas-badge"><?= e(strtoupper($t['status'])) ?></span>
    </div>
    <?php
} else {
    $where = "DATE(tanggal)=CURDATE()";
    $params = [];
    if ($user['role']==='kasir') { $where .= ' AND t.user_id=?'; $params[] = $user['id']; }
    $st = $pdo->prepare("SELECT t.*, u.nama_lengkap FROM transaksi t LEFT JOIN users u ON u.id=t.user_id WHERE $where ORDER BY t.tanggal DESC, t.id DESC");
    $st->execute($params); $transaksi = $st->fetchAll();
    kasir_header('struk', false);
    ?>
    <div class="pos-judul"><div><h1>Struk</h1><p>Riwayat transaksi hari ini. Pilih struk untuk dilihat atau dicetak.</p></div><span class="badge badge-krem">Tab Struk</span></div>
    <div class="kartu">
      <div class="tabel-wrap"><table><thead><tr><th>Kode</th><th>Waktu</th><th>Metode</th><th class="kanan">Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php if(!$transaksi): ?><tr><td colspan="6" class="tengah muted">Belum ada transaksi hari ini.</td></tr><?php endif; ?>
        <?php foreach($transaksi as $t): ?>
          <tr><td><strong style="color:var(--teks)"><?= e($t['kode']) ?></strong></td><td><?= e(date('H.i',strtotime($t['tanggal']))) ?></td><td><?= e(ucfirst($t['metode_bayar'])) ?></td><td class="kanan"><?= e(rupiah($t['total'])) ?></td><td><span class="badge <?= $t['status']==='lunas'?'badge-hijau':'badge-merah' ?>"><?= e($t['status']) ?></span></td>
          <td><a class="btn btn-secondary btn-sm" href="<?= e(base_url('kasir/struk.php?id='.(int)$t['id'])) ?>"><i data-lucide="receipt"></i> Lihat / Cetak</a></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php
}
kasir_footer();
