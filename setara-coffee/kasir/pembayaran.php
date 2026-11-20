<?php
require_once __DIR__ . '/../includes/layout_kasir.php';
require_role(['owner','kasir']);
$pdo = db();
if (empty($_SESSION['keranjang'])) { flash('warning','Tambahkan item terlebih dahulu sebelum pembayaran.'); redirect('kasir/index.php'); }

// Hitung ulang ringkasan untuk ditampilkan (sumber kebenaran tetap bayar.php)
$items = []; $subtotal=0; $totalDiskon=0; $total=0;
$ids = array_keys($_SESSION['keranjang']);
$ph = implode(',', array_fill(0,count($ids),'?'));
$st = $pdo->prepare("SELECT m.*, (SELECT COALESCE(SUM(r.takaran*b.harga_beli_per_satuan),0) FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=m.id) hpp FROM menu m WHERE m.id IN ($ph)");
$st->execute($ids); $menu=[];
foreach($st->fetchAll() as $m) $menu[(int)$m['id']]=$m;
$today=date('Y-m-d');
foreach($_SESSION['keranjang'] as $mid=>$d){
  $m=$menu[(int)$mid]??null; if(!$m)continue;
  $qty=(int)$d['qty']; $sub=(float)$m['harga']*$qty; $dp=0;$dn=0;$pk=null;
  if(!empty($d['promo_id'])){
    $ps=$pdo->prepare('SELECT * FROM promo WHERE id=?'); $ps->execute([$d['promo_id']]); $p=$ps->fetch();
    if($p&&$p['aktif']&&(int)$p['menu_id']===(int)$mid&&(!$p['berlaku_mulai']||$p['berlaku_mulai']<=$today)&&(!$p['berlaku_sampai']||$p['berlaku_sampai']>=$today)){
      $dp=(float)$p['potongan_persen']; $dn=round($sub*$dp/100); $pk=$p['kode'];
    }
  }
  $items[]=['nama'=>$m['nama'],'qty'=>$qty,'harga'=>(float)$m['harga'],'subtotal'=>$sub,'promo_kode'=>$pk,'diskon_persen'=>$dp,'diskon_nilai'=>$dn,'total'=>$sub-$dn];
  $subtotal+=$sub;$totalDiskon+=$dn;
}
$total=$subtotal-$totalDiskon;
$jenis = $_SESSION['jenis_pesanan_sementara'] ?? 'Makan di tempat';
$kasirNama = $_SESSION['user']['nama'];
$alamat = 'Jl. Towerkanjovank, Tamalanrea Indah, Kec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245';
// Voucher aktif untuk menu di keranjang
$vouchers=[];
foreach($ids as $mid){
  $vs=$pdo->prepare("SELECT p.*,m.nama menu_nama FROM promo p JOIN menu m ON m.id=p.menu_id WHERE p.menu_id=? AND p.aktif=1 AND (p.berlaku_mulai IS NULL OR p.berlaku_mulai<=?) AND (p.berlaku_sampai IS NULL OR p.berlaku_sampai>=?)");
  $vs->execute([$mid,$today,$today]);
  foreach($vs->fetchAll() as $v) $vouchers[]=$v;
}
kasir_header('pembayaran', true);
?>
<div style="margin-bottom:14px"><a class="kembali" href="<?= e(base_url('kasir/index.php')) ?>">← Kembali ke kasir</a></div>
<div class="pos-judul">
  <div><h1>Pembayaran pesanan</h1><p><?= e($kasirNama) ?> / Kasir · <?= e(tanggal_indonesia(date('Y-m-d'))) ?>, <?= e(date('H:i')) ?></p></div>
  <span class="badge badge-hijau">Pesanan siap dibayar</span>
</div>
<div class="pos-grid">
  <div>
    <div class="kartu">
      <div class="kartu-head"><div><h2>Diskon &amp; voucher</h2><p>Pasang voucher pada menu yang tersedia.</p></div></div>
      <div class="baris-atas">
        <div class="input-ikon" style="flex:1"><input id="kodeVoucher" placeholder="Kode voucher"><span class="ikon-kanan" data-lucide="tag"></span></div>
        <select id="voucherMenu" style="width:auto;min-width:180px"><?php foreach($items as $it): ?><option value="<?= e($it['nama']) ?>"><?= e($it['nama']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary btn-sm" id="btnVoucher">Terapkan</button>
      </div>
      <div id="pesanVoucher" class="small" style="margin-top:8px"></div>
      <?php if($vouchers): ?><div class="voucher-grid" style="margin-top:16px">
        <?php foreach($vouchers as $v):
          $terpasang = !empty($_SESSION['keranjang'][(int)$v['menu_id']]['promo_id']) && (int)$_SESSION['keranjang'][(int)$v['menu_id']]['promo_id']===(int)$v['id'];
          $namaMenu = $v['menu_nama'];
          // cari diskon pada item ini
          $hemat=0; foreach($items as $it){ if($it['nama']===$namaMenu) $hemat=$it['diskon_nilai']; }
        ?>
          <div class="voucher-kartu <?= $terpasang?'terpasang':'' ?>" data-id="<?= (int)$v['id'] ?>" data-menu="<?= (int)$v['menu_id'] ?>" data-kode="<?= e($v['kode']) ?>" data-persen="<?= e($v['potongan_persen']) ?>">
            <?php if($terpasang): ?><span class="centang" data-lucide="check-circle-2"></span><?php endif; ?>
            <div class="persen"><?= e(number_format((float)$v['potongan_persen'],0)) ?>%</div>
            <div class="small" style="color:var(--teks)"><?= e($namaMenu) ?></div>
            <div class="kode"><?= e($v['kode']) ?></div>
            <div class="small" style="margin-top:6px"><?= $terpasang?'Terpasang · hemat '.rupiah($hemat):'Belum ada di pesanan' ?></div>
          </div>
        <?php endforeach; ?>
      </div><?php else: ?><p class="small muted" style="margin-top:14px">Tidak ada voucher aktif untuk menu dalam pesanan.</p><?php endif; ?>
      <p class="small muted" style="margin-top:14px">Satu promo per item. Voucher hanya berlaku pada menu terkait. Menu yang tidak mendapat diskon tetap dibayar penuh.</p>
    </div>

    <div class="kartu">
      <div class="kartu-head"><div><h2>Metode pembayaran</h2><p>Metode terpilih: <strong id="metodeLabel">Tunai</strong></p></div></div>
      <div class="segmented">
        <button type="button" class="aktif" data-metode="tunai" data-label="Tunai"><i data-lucide="banknote"></i> Tunai</button>
        <button type="button" data-metode="qris" data-label="QRIS"><i data-lucide="qr-code"></i> QRIS</button>
        <button type="button" data-metode="debit" data-label="Debit"><i data-lucide="credit-card"></i> Debit</button>
      </div>
      <div id="bagianTunai" style="margin-top:18px">
        <label for="uang">Uang diterima</label>
        <div class="input-prefix"><span>Rp</span><input id="uang" type="text" inputmode="numeric" placeholder="0"></div>
        <div class="btn-cepat"><button type="button" data-cepat="pas">Pas</button><button type="button" data-cepat="50000">Rp50.000</button><button type="button" data-cepat="100000">Rp100.000</button></div>
        <p class="small muted" style="margin-top:8px">Total yang harus dibayar <strong><?= e(rupiah($total)) ?></strong></p>
        <div class="kembalian-box hijau" id="kotakKembalian"><span class="small">Kembalian</span><div class="angka" id="nilaiKembalian">Rp0</div></div>
      </div>
      <button class="btn btn-primary" id="btnBayar" style="margin-top:20px;width:260px">✓ Selesaikan pembayaran</button>
    </div>
  </div>

  <div class="kartu struk-kartu" id="struk">
    <div style="text-align:center;margin-bottom:10px"><i data-lucide="receipt" style="width:30px;height:30px;color:var(--teks-muted)"></i><p class="small muted" id="strukStatus">Pratinjau struk</p></div>
    <div class="struk-header">
      <div class="struk-logo">Café Setara</div><p class="eyebrow">RUANG KELOLA KEDAI</p>
      <p class="struk-alamat"><?= e($alamat) ?></p>
    </div>
    <div class="struk-baris"><span class="muted">Kode</span><span id="sKode">TRX baru</span></div>
    <div class="struk-baris"><span class="muted">Waktu</span><span><?= e(tanggal_indonesia(date('Y-m-d'))) ?>, <?= e(date('H:i')) ?></span></div>
    <div class="struk-baris"><span class="muted">Kasir</span><span><?= e($kasirNama) ?> · Kasir</span></div>
    <div class="struk-baris"><span class="muted">Jenis</span><span><?= e($jenis) ?></span></div>
    <hr class="hr">
    <?php foreach($items as $it): ?>
      <div class="struk-baris"><span><?= $it['qty'] ?> × <?= e($it['nama']) ?><br><span class="small muted"><?= e(rupiah($it['harga'])) ?> / menu</span></span><span><?= e(rupiah($it['total'])) ?></span></div>
      <?php if($it['diskon_nilai']>0): ?><div class="struk-hijau">&nbsp;&nbsp;↳ Voucher <?= e($it['promo_kode']) ?> · −<?= e(rupiah($it['diskon_nilai'])) ?></div><?php endif; ?>
    <?php endforeach; ?>
    <hr class="hr">
    <div class="struk-baris"><span>Subtotal</span><span><?= e(rupiah($subtotal)) ?></span></div>
    <div class="struk-baris struk-hijau"><span>Diskon</span><span>−<?= e(rupiah($totalDiskon)) ?></span></div>
    <div class="struk-baris total"><span>Total</span><span><?= e(rupiah($total)) ?></span></div>
    <div class="struk-baris"><span id="sMetodeLabel">Tunai</span><span id="sUang">Rp0</span></div>
    <div class="struk-baris"><span>Kembalian</span><span id="sKembalian">Rp0</span></div>
    <p class="struk-pusat">Terima kasih. Sampai jumpa di cangkir berikutnya.</p>
    <?php if($totalDiskon>0): ?><p class="struk-pusat">Satu promo per item. Diskon total <?= e(rupiah($totalDiskon)) ?>.</p><?php endif; ?>
    <span class="badge badge-hijau lunas-badge">LUNAS</span>
  </div>
</div>

<script>
const CSRF='<?= e(csrf_token()) ?>', API='<?= e(base_url('kasir/api/keranjang.php')) ?>', BAYAR='<?= e(base_url('kasir/api/bayar.php')) ?>';
const TOTAL=<?= (float)$total ?>, JENIS=<?= json_encode($jenis, JSON_UNESCAPED_UNICODE) ?>;
let metode='tunai';
function angka(n){return 'Rp'+Math.round(n).toLocaleString('id-ID');}
function bersih(v){ return parseInt(String(v).replace(/\D/g,''))||0; }
const uang=document.getElementById('uang');
function formatUang(){ uang.value=bersih(uang.value).toLocaleString('id-ID'); hitung(); }
function hitung(){
  const bayar=bersih(uang.value), kembalian=Math.max(0,bayar-TOTAL), kurang=bayar<TOTAL;
  const box=document.getElementById('kotakKembalian');
  box.className='kembalian-box '+(kurang?'merah':'hijau');
  box.querySelector('.small').textContent=kurang?('Uang kurang '+angka(TOTAL-bayar)):'Kembalian';
  document.getElementById('nilaiKembalian').textContent=angka(kembalian);
  document.getElementById('sUang').textContent=metode==='tunai'?angka(bayar):angka(TOTAL);
  document.getElementById('sKembalian').textContent=metode==='tunai'?angka(kembalian):'Rp0';
  document.getElementById('btnBayar').disabled = metode==='tunai' && kurang;
}
uang.addEventListener('input',formatUang);
document.querySelectorAll('[data-cepat]').forEach(b=>b.addEventListener('click',function(){
  uang.value=this.dataset.cepat==='pas'?TOTAL:this.dataset.cepat; formatUang();
}));
document.querySelectorAll('.segmented button').forEach(b=>b.addEventListener('click',function(){
  document.querySelectorAll('.segmented button').forEach(x=>x.classList.remove('aktif')); this.classList.add('aktif');
  metode=this.dataset.metode;
  document.getElementById('metodeLabel').textContent=this.dataset.label;
  document.getElementById('sMetodeLabel').textContent=this.dataset.label;
  document.getElementById('bagianTunai').style.display=metode==='tunai'?'block':'none';
  hitung();
}));
function kirim(payload){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF},payload))}).then(r=>r.json());}
document.getElementById('btnVoucher').addEventListener('click',function(){
  const kode=document.getElementById('kodeVoucher').value.trim().toUpperCase();
  const nama=document.getElementById('voucherMenu').value;
  if(!kode)return;
  // cari menu_id dari nama lewat kartu voucher atau data包装: pakai fetch tambahan sederhana
  const kartu=[...document.querySelectorAll('.voucher-kartu')].find(k=>k.dataset.kode===kode);
  const menuId=kartu?kartu.dataset.menu:null;
  if(!menuId){ document.getElementById('pesanVoucher').innerHTML='<span style="color:var(--merah-teks)">Kode tidak ditemukan</span>'; return; }
  kirim({aksi:'voucher',menu_id:menuId,kode:kode}).then(j=>{
    document.getElementById('pesanVoucher').innerHTML='<span style="color:'+(j.ok?'var(--sage-teks)':'var(--merah-teks)')+'">'+(j.pesan||'')+'</span>';
    if(j.ok)location.reload();
  });
});
document.querySelectorAll('.voucher-kartu').forEach(k=>k.addEventListener('click',function(){
  const dipasang=this.classList.contains('terpasang');
  kirim({aksi:dipasang?'lepas_voucher':'voucher',menu_id:this.dataset.menu,kode:this.dataset.kode}).then(j=>{ if(j.ok)location.reload(); else alert(j.pesan); });
}));
document.getElementById('btnBayar').addEventListener('click',function(){
  const btn=this; btn.disabled=true; btn.textContent='Memproses...';
  fetch(BAYAR,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
    csrf_token:CSRF, metode:metode, uang_diterima:bersih(uang.value), jenis_pesanan:JENIS
  })}).then(r=>r.json()).then(j=>{
    if(j.ok){ location.href=j.redirect; }
    else { alert(j.pesan||'Pembayaran gagal.'); btn.disabled=false; btn.textContent='✓ Selesaikan pembayaran'; hitung(); }
  }).catch(e=>{ alert('Terjadi kesalahan jaringan.'); btn.disabled=false; btn.textContent='✓ Selesaikan pembayaran'; });
});
hitung();
document.addEventListener('DOMContentLoaded',function(){if(window.lucide)lucide.createIcons();});
</script>
<?php kasir_footer(); ?>
