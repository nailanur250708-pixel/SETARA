<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['owner','kasir']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'pesan'=>'Permintaan tidak valid (CSRF). Silakan muat ulang halaman.']);
    exit;
}
$pdo = db();
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;
$metode = strtolower((string)($body['metode'] ?? 'tunai'));
$jenisPesanan = ($body['jenis_pesanan'] ?? 'Makan di tempat') === 'Bawa pulang' ? 'Bawa pulang' : 'Makan di tempat';
$uangDiterima = isset($body['uang_diterima']) ? (float)$body['uang_diterima'] : 0;

if (empty($_SESSION['keranjang'])) { echo json_encode(['ok'=>false,'pesan'=>'Keranjang masih kosong.']); exit; }
if (!in_array($metode, ['tunai','qris','debit'], true)) { echo json_encode(['ok'=>false,'pesan'=>'Metode pembayaran tidak valid.']); exit; }

$user = $_SESSION['user'];
$berhasil = false;
$redirect = '';
$kode = '';

try {
    $pdo->beginTransaction();

    // Ambil & lock menu + bahan
    $ids = array_keys($_SESSION['keranjang']);
    if (!$ids) throw new Exception('Keranjang kosong.');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT m.*, (SELECT COALESCE(SUM(r.takaran*b.harga_beli_per_satuan),0) FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=m.id) hpp FROM menu m WHERE m.id IN ($ph) FOR UPDATE");
    $st->execute($ids);
    $menu = [];
    foreach ($st->fetchAll() as $m) $menu[(int)$m['id']] = $m;

    $items = []; $subtotal = 0; $totalDiskon = 0; $totalHpp = 0; $kebutuhan = [];
    $today = date('Y-m-d');
    foreach ($_SESSION['keranjang'] as $menuId => $data) {
        $menuId = (int)$menuId;
        if (!isset($menu[$menuId])) throw new Exception('Menu tidak ditemukan.');
        $m = $menu[$menuId];
        if (!$m['tersedia'] || $m['diarsipkan']) throw new Exception('Menu '.$m['nama'].' tidak tersedia.');
        $qty = (int)$data['qty'];
        if ($qty <= 0) throw new Exception('Jumlah pesanan tidak valid.');
        $harga = (float)$m['harga'];
        $sub = $harga * $qty;
        $diskonPersen = 0; $diskonNilai = 0; $promoId = null;
        if (!empty($data['promo_id'])) {
            $ps = $pdo->prepare('SELECT * FROM promo WHERE id=? FOR UPDATE');
            $ps->execute([$data['promo_id']]); $promo = $ps->fetch();
            if (!$promo || !$promo['aktif'] || (int)$promo['menu_id']!==$menuId
                || ($promo['berlaku_mulai'] && $promo['berlaku_mulai']>$today)
                || ($promo['berlaku_sampai'] && $promo['berlaku_sampai']<$today)) {
                throw new Exception('Voucher pada '.$m['nama'].' sudah tidak berlaku.');
            }
            $promoId = (int)$promo['id'];
            $diskonPersen = (float)$promo['potongan_persen'];
            $diskonNilai = round($sub * $diskonPersen / 100);
        }
        $hppSatuan = (float)$m['hpp'];
        $items[] = [
            'menu_id'=>$menuId,'qty'=>$qty,'harga'=>$harga,'subtotal'=>$sub,
            'hpp_satuan'=>$hppSatuan,'promo_id'=>$promoId,'diskon_persen'=>$diskonPersen,
            'diskon_nilai'=>$diskonNilai,'catatan'=>mb_substr((string)($data['catatan']??''),0,200),
            'nama'=>$m['nama'],
        ];
        $subtotal += $sub; $totalDiskon += $diskonNilai; $totalHpp += $hppSatuan*$qty;

        // Kebutuhan bahan
        $rs = $pdo->prepare('SELECT r.bahan_id, r.takaran FROM resep r WHERE r.menu_id=?');
        $rs->execute([$menuId]);
        foreach ($rs->fetchAll() as $r) {
            $bid = (int)$r['bahan_id'];
            $kebutuhan[$bid] = ($kebutuhan[$bid] ?? 0) + (float)$r['takaran']*$qty;
        }
    }
    $total = $subtotal - $totalDiskon;

    // Lock & cek stok bahan
    if ($kebutuhan) {
        $bids = array_keys($kebutuhan);
        $bph = implode(',', array_fill(0, count($bids), '?'));
        $bs = $pdo->prepare("SELECT id,nama,stok FROM bahan WHERE id IN ($bph) FOR UPDATE");
        $bs->execute($bids);
        $bahan = [];
        foreach ($bs->fetchAll() as $b) $bahan[(int)$b['id']] = $b;
        foreach ($kebutuhan as $bid => $qty) {
            if (!isset($bahan[$bid])) throw new Exception('Bahan resep tidak ditemukan.');
            if ((float)$bahan[$bid]['stok'] < $qty) throw new Exception('Stok '.$bahan[$bid]['nama'].' tidak cukup');
        }
        foreach ($kebutuhan as $bid => $qty) {
            $pdo->prepare('UPDATE bahan SET stok=stok-? WHERE id=?')->execute([$qty, $bid]);
            $pdo->prepare("INSERT INTO pergerakan_stok (bahan_id,tipe,qty,keterangan,user_id,tanggal) VALUES (?,'penjualan',?,?,?,NOW())")
                ->execute([$bid, -$qty, 'Penjualan kasir', $user['id']]);
        }
    }

    // Kode transaksi TRX-YYYYMMDD-NNN dengan retry 3x
    $tglKode = date('Ymd');
    $prefix = 'TRX-'.$tglKode.'-';
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $st = $pdo->query("SELECT kode FROM transaksi WHERE kode LIKE '$prefix%' ORDER BY kode DESC LIMIT 1 FOR UPDATE");
        $last = $st->fetchColumn();
        $no = $last ? ((int)substr($last, -3) + 1) : 1;
        $kode = $prefix . str_pad((string)$no, 3, '0', STR_PAD_LEFT);
        $cek = $pdo->prepare('SELECT COUNT(*) FROM transaksi WHERE kode=?'); $cek->execute([$kode]);
        if (!$cek->fetchColumn()) break;
        $kode = '';
    }
    if (!$kode) throw new Exception('Gagal membuat kode transaksi. Silakan coba lagi.');

    if ($metode === 'tunai') {
        if ($uangDiterima < $total) throw new Exception('Uang kurang '.rupiah($total-$uangDiterima));
        $kembalian = $uangDiterima - $total;
    } else {
        $uangDiterima = $total; $kembalian = 0;
    }

    $ins = $pdo->prepare("INSERT INTO transaksi (kode,users_id,user_id,tanggal,total,metode_bayar,jenis_pesanan,subtotal,total_diskon,total_hpp,uang_diterima,kembalian,status,catatan) VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,'lunas',?)");
    $ins->execute([$kode,$user['id'],$user['id'],$total,$metode,$jenisPesanan,$subtotal,$totalDiskon,$totalHpp,$uangDiterima,$kembalian,null]);
    $transaksiId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("INSERT INTO detail_transaksi (transaksi_id,menu_id,qty,harga_satuan,hpp_satuan,promo_id,diskon_persen,diskon_nilai,catatan) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($items as $it) {
        $insItem->execute([$transaksiId,$it['menu_id'],$it['qty'],$it['harga'],$it['hpp_satuan'],$it['promo_id'],$it['diskon_persen'],$it['diskon_nilai'],$it['catatan'] ?: null]);
    }

    $pdo->commit();
    $berhasil = true;
    $redirect = base_url('kasir/struk.php?id='.$transaksiId);
    $_SESSION['keranjang'] = [];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[BAYAR] '.$e->getMessage());
    echo json_encode(['ok'=>false,'pesan'=>'Pembayaran gagal: '.$e->getMessage()]);
    exit;
}

echo json_encode(['ok'=>true,'id'=>(int)($transaksiId??0),'kode'=>$kode,'redirect'=>$redirect]);
