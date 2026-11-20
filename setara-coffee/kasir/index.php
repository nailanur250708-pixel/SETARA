<?php
require_once __DIR__ . '/../includes/layout_kasir.php';
require_role(['owner','kasir']);
$pdo = db();

// Hitung stok bahan untuk tiap menu (apakah bahan cukup).
$st = $pdo->query("SELECT m.id, b.nama, r.takaran, b.stok FROM menu m JOIN resep r ON r.menu_id=m.id JOIN bahan b ON b.id=r.bahan_id");
$kebutuhanPerMenu = [];
foreach ($st->fetchAll() as $r) {
    if ((float)$r['stok'] < (float)$r['takaran']) $kebutuhanPerMenu[(int)$r['id']] = $r['nama'];
}
$st = $pdo->query("SELECT m.*, (SELECT COALESCE(SUM(r.takaran*b.harga_beli_per_satuan),0) FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=m.id) hpp FROM menu m WHERE m.tersedia=1 AND m.diarsipkan=0 ORDER BY m.kategori, m.nama");
$menus = $st->fetchAll();
$promoByMenu = [];
foreach ($pdo->query("SELECT * FROM promo WHERE aktif=1 AND (berlaku_mulai IS NULL OR berlaku_mulai<=CURDATE()) AND (berlaku_sampai IS NULL OR berlaku_sampai>=CURDATE())")->fetchAll() as $p) {
    $promoByMenu[(int)$p['menu_id']][] = $p;
}
$adaItem = !empty($_SESSION['keranjang']);
kasir_header('kasir', $adaItem);
?>
<div class="pos-judul">
  <div><h1>Kasir / POS</h1><p>Pilih racikan, periksa pesanan, lalu lanjutkan pembayaran.</p></div>
  <span class="badge badge-krem"><?= e($_SESSION['user']['nama']) ?> · Ruang kasir</span>
</div>
<div class="pos-grid">
  <div>
    <div class="filter-bar">
      <div class="input-ikon"><input id="cariMenu" type="text" placeholder="Cari nama menu..."><span class="ikon-kanan" data-lucide="search"></span></div>
      <button class="chip aktif" data-filter="semua">Semua menu</button>
      <button class="chip" data-filter="Kopi">Kopi</button>
      <button class="chip" data-filter="Non-kopi">Non-kopi</button>
      <button class="chip" data-filter="promo">Promo</button>
    </div>
    <div class="menu-grid" id="menuGrid">
      <?php foreach ($menus as $m):
        $mid = (int)$m['id'];
        $habis = isset($kebutuhanPerMenu[$mid]);
        $promos = $promoByMenu[$mid] ?? [];
      ?>
      <div class="menu-kartu <?= $habis?'habis':'' ?>" data-id="<?= $mid ?>" data-nama="<?= e(strtolower($m['nama'])) ?>" data-kategori="<?= e($m['kategori']) ?>" data-promo="<?= $promos?'1':'0' ?>" data-habis="<?= $habis?'1':'0' ?>">
        <div class="menu-kartu-atas">
          <span class="menu-ikon" data-lucide="<?= $m['kategori']==='Kopi'?'coffee':'leaf' ?>"></span>
          <?php if ($promos): ?><span class="badge badge-hijau">Promo <?= e(number_format((float)$promos[0]['potongan_persen'],0)) ?>%</span>
          <?php else: ?><span class="small muted"><?= e($m['kategori']) ?></span><?php endif; ?>
        </div>
        <h3><?= e($m['nama']) ?></h3><span class="menu-harga"><?= e(rupiah($m['harga'])) ?></span>
        <div class="bawah">
          <?php if ($habis): ?><span class="small muted">Stok bahan habis</span><button class="btn btn-secondary btn-sm btn-block" disabled>Tidak tersedia</button>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm btn-block btn-tambah" data-id="<?= $mid ?>">Tambah pesanan +</button>
            <div class="stepper" style="display:none"><button class="kurang" data-id="<?= $mid ?>">−</button><span class="qty">0</span><button class="tambah" data-id="<?= $mid ?>">+</button></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$menus): ?><div class="kartu muted">Belum ada menu tersedia.</div><?php endif; ?>
    </div>
  </div>
  <div class="kartu pesanan-kartu">
    <div class="pesanan-head"><h2>Pesanan saat ini</h2><button class="btn-titik" id="btnOpsi" title="Opsi">⋯</button></div>
    <p class="pesanan-meta">TRX baru · <?= e(tanggal_indonesia(date('Y-m-d'), false)) ?>, <?= e(date('H:i')) ?></p>
    <div class="form-group"><label for="jenisPesanan">Jenis pesanan</label>
      <select id="jenisPesanan"><option>Makan di tempat</option><option>Bawa pulang</option></select>
    </div>
    <div id="daftarItem">
      <div class="kosong-keranjang" id="kosong"><i data-lucide="shopping-bag"></i><p>Belum ada pesanan.<br>Pilih menu di sebelah kiri.</p></div>
    </div>
    <button class="btn btn-secondary btn-sm btn-block" id="btnCatatan" style="margin-top:10px">Tambah catatan pesanan</button>
    <textarea id="catatanGlobal" placeholder="Catatan untuk pesanan..." style="display:none;margin-top:8px"></textarea>
    <div class="ringkasan" id="ringkasan" style="display:none">
      <div class="baris"><span class="muted">Subtotal</span><span id="rSubtotal">Rp0</span></div>
      <div id="rDiskon"></div>
      <div class="baris total"><span><strong>Total</strong></span><span id="rTotal">Rp0</span></div>
    </div>
    <div class="info-hijau" id="infoPromo" style="display:none"></div>
    <p class="small muted" id="jumlahMenu" style="margin-top:10px"></p>
    <button class="btn btn-primary btn-block" id="btnLanjut" disabled style="margin-top:12px">→ Lanjut pembayaran</button>
  </div>
</div>

<script>
const CSRF = '<?= e(csrf_token()) ?>';
const API = '<?= e(base_url('kasir/api/keranjang.php')) ?>';
let keranjang = {};

function rp(n){ return 'Rp'+Math.round(n).toLocaleString('id-ID'); }
function kirim(payload){
  return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF},payload))}).then(r=>r.json());
}
function render(j){
  if(!j.ok){ if(j.pesan) alert(j.pesan); return; }
  const wrap=document.getElementById('daftarItem');
  document.getElementById('kosong')?.remove();
  wrap.querySelectorAll('.item-pesanan').forEach(x=>x.remove());
  j.items.forEach(it=>{
    const d=document.createElement('div'); d.className='item-pesanan';
    d.innerHTML='<div class="baris"><span><strong>'+escapeHtml(it.nama)+'</strong><br><span class="qty">'+it.qty+' × '+rp(it.harga)+'</span></span><span>'+rp(it.total)+'</span></div>'
      +(it.promo_kode?'<span class="voucher">Voucher '+escapeHtml(it.promo_kode)+' · '+it.diskon_persen+'% · −'+rp(it.diskon_nilai)+'</span>':'');
    wrap.appendChild(d);
  });
  if(!j.items.length){ wrap.innerHTML='<div class="kosong-keranjang" id="kosong"><i data-lucide="shopping-bag"></i><p>Belum ada pesanan.<br>Pilih menu di sebelah kiri.</p></div>'; if(window.lucide)lucide.createIcons(); }
  document.getElementById('rSubtotal').textContent=rp(j.subtotal);
  const rd=document.getElementById('rDiskon'); rd.innerHTML='';
  j.items.filter(i=>i.diskon_nilai>0).forEach(i=>{ rd.innerHTML+='<div class="baris diskon"><span>Diskon '+escapeHtml(i.nama)+' ('+i.diskon_persen+'%)</span><span>−'+rp(i.diskon_nilai)+'</span></div>'; });
  document.getElementById('rTotal').textContent=rp(j.total);
  document.getElementById('ringkasan').style.display=j.items.length?'block':'none';
  document.getElementById('jumlahMenu').textContent=j.jumlah_menu+' menu dalam pesanan';
  const info=document.getElementById('infoPromo');
  if(j.items.length){ info.style.display='block'; info.textContent=j.info; } else info.style.display='none';
  document.getElementById('btnLanjut').disabled=!j.items.length;
  // stepper
  document.querySelectorAll('.menu-kartu').forEach(k=>{
    const id=k.dataset.id, qty=(j.items.find(i=>i.menu_id==id)||{}).qty||0;
    k.classList.toggle('dipilih',qty>0);
    const tombol=k.querySelector('.btn-tambah'), step=k.querySelector('.stepper');
    if(tombol&&step){ tombol.style.display=qty?'none':'block'; step.style.display=qty?'flex':'none'; step.querySelector('.qty').textContent=qty; }
  });
  sessionStorage.setItem('setara_cart',JSON.stringify(j));
}
function escapeHtml(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
document.addEventListener('click',function(ev){
  const t=ev.target.closest('button'); if(!t)return;
  if(t.classList.contains('btn-tambah')){ kirim({aksi:'tambah',menu_id:t.dataset.id}).then(render); }
  else if(t.classList.contains('tambah')){ kirim({aksi:'tambah',menu_id:t.dataset.id}).then(render); }
  else if(t.classList.contains('kurang')){ const id=t.dataset.id; const q=+t.parentElement.querySelector('.qty').textContent; kirim({aksi:'ubah',menu_id:id,qty:q-1}).then(render); }
});
document.getElementById('btnOpsi').addEventListener('click',function(){ if(confirm('Kosongkan seluruh pesanan?')) kirim({aksi:'kosongkan'}).then(render); });
document.getElementById('btnCatatan').addEventListener('click',function(){ const c=document.getElementById('catatanGlobal'); c.style.display=c.style.display==='none'?'block':'none'; });
document.getElementById('catatanGlobal').addEventListener('change',function(){ const it=JSON.parse(sessionStorage.getItem('setara_cart')||'{}').items||[]; if(it[0]) kirim({aksi:'catatan',menu_id:it[0].menu_id,catatan:this.value}).then(render); });
document.getElementById('btnLanjut').addEventListener('click',function(){
  sessionStorage.setItem('jenis_pesanan',document.getElementById('jenisPesanan').value);
  sessionStorage.setItem('catatan_pesanan',document.getElementById('catatanGlobal').value);
  location.href='<?= e(base_url('kasir/pembayaran.php')) ?>';
});
// Filter
document.querySelectorAll('.chip').forEach(c=>c.addEventListener('click',function(){
  document.querySelectorAll('.chip').forEach(x=>x.classList.remove('aktif')); c.classList.add('aktif');
  const f=c.dataset.filter, q=document.getElementById('cariMenu').value.toLowerCase();
  document.querySelectorAll('.menu-kartu').forEach(k=>{
    const cocok=(!q||k.dataset.nama.includes(q)) && (f==='semua'||(f==='promo'?k.dataset.promo==='1':k.dataset.kategori===f));
    k.style.display=cocok?'flex':'none';
  });
}));
document.getElementById('cariMenu').addEventListener('input',function(){ document.querySelector('.chip.aktif')?.click(); });
// Muat keranjang existing
kirim({aksi:'muat'}).then(j=>{ if(j.ok)render(j); });
</script>
<?php kasir_footer(); ?>
