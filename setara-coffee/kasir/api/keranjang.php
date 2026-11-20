<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['owner','kasir']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'pesan'=>'Permintaan tidak valid (CSRF).']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    // fallback form-encoded
    $body = $_POST;
}
$aksi = $body['aksi'] ?? '';
$pdo = db();

if (!isset($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) $_SESSION['keranjang'] = [];

/** Hitung kebutuhan bahan seluruh keranjang (termasuk perubahan yang diusulkan). */
function kebutuhan_bahan(array $keranjang): array
{
    if (!$keranjang) return [];
    $ids = array_keys($keranjang);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT r.menu_id, r.bahan_id, r.takaran, b.nama, b.stok FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id IN ($ph)");
    $st->execute($ids);
    $kebutuhan = [];
    foreach ($st->fetchAll() as $r) {
        $qty = (int)$keranjang[$r['menu_id']]['qty'];
        $k = ($kebutuhan[$r['bahan_id']]['qty'] ?? 0) + (float)$r['takaran'] * $qty;
        $kebutuhan[$r['bahan_id']] = ['qty'=>$k,'nama'=>$r['nama'],'stok'=>(float)$r['stok']];
    }
    return $kebutuhan;
}

/** Validasi stok untuk keranjang; return pesan error atau null. */
function validasi_stok(array $keranjang): ?string
{
    foreach (kebutuhan_bahan($keranjang) as $b) {
        if ($b['stok'] < $b['qty']) {
            return 'Stok ' . $b['nama'] . ' tidak cukup untuk menambah pesanan ini';
        }
    }
    return null;
}

/** Bangun response keranjang lengkap dengan harga dari DB. */
function response_keranjang(): array
{
    $keranjang = $_SESSION['keranjang'];
    $items = []; $subtotal = 0; $totalDiskon = 0; $totalHpp = 0;
    if ($keranjang) {
        $ids = array_keys($keranjang);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT m.*, (SELECT COALESCE(SUM(r.takaran*b.harga_beli_per_satuan),0) FROM resep r JOIN bahan b ON b.id=r.bahan_id WHERE r.menu_id=m.id) hpp FROM menu m WHERE m.id IN ($ph)");
        $st->execute($ids);
        $menu = [];
        foreach ($st->fetchAll() as $m) $menu[$m['id']] = $m;
        foreach ($keranjang as $menuId => $data) {
            if (!isset($menu[$menuId])) continue;
            $m = $menu[$menuId];
            $qty = (int)$data['qty'];
            $harga = (float)$m['harga'];
            $sub = $harga * $qty;
            $diskonPersen = 0; $diskonNilai = 0; $promoKode = null; $promoId = null;
            if (!empty($data['promo_id'])) {
                $ps = db()->prepare('SELECT * FROM promo WHERE id=?');
                $ps->execute([$data['promo_id']]);
                $promo = $ps->fetch();
                $today = date('Y-m-d');
                if ($promo && $promo['aktif'] && $promo['menu_id']==$menuId
                    && (!$promo['berlaku_mulai'] || $promo['berlaku_mulai']<=$today)
                    && (!$promo['berlaku_sampai'] || $promo['berlaku_sampai']>=$today)) {
                    $diskonPersen = (float)$promo['potongan_persen'];
                    $diskonNilai = round($sub * $diskonPersen / 100);
                    $promoKode = $promo['kode']; $promoId = (int)$promo['id'];
                } else {
                    $_SESSION['keranjang'][$menuId]['promo_id'] = null;
                }
            }
            $hpp = (float)$m['hpp'];
            $items[] = [
                'menu_id'=>(int)$menuId,'nama'=>$m['nama'],'kategori'=>$m['kategori'],'qty'=>$qty,
                'harga'=>$harga,'subtotal'=>$sub,'hpp'=>$hpp,'hpp_total'=>$hpp*$qty,
                'promo_id'=>$promoId,'promo_kode'=>$promoKode,'diskon_persen'=>$diskonPersen,'diskon_nilai'=>$diskonNilai,
                'total'=>$sub-$diskonNilai,'catatan'=>$data['catatan'] ?? '',
            ];
            $subtotal += $sub; $totalDiskon += $diskonNilai; $totalHpp += $hpp*$qty;
        }
    }
    return [
        'ok'=>true,'items'=>$items,'subtotal'=>$subtotal,'total_diskon'=>$totalDiskon,
        'total'=>$subtotal-$totalDiskon,'total_hpp'=>$totalHpp,
        'jumlah_menu'=>count($items),
        'info'=>info_promo($items),
    ];
}

/** Info dinamis kotak hijau di POS. */
function info_promo(array $items): string
{
    $terpasang = array_values(array_filter($items, fn($i)=>$i['promo_kode']));
    if (!$terpasang) return 'Belum ada promo dipasang. Satu promo per item — pasang voucher pada menu yang tersedia.';
    $bagian = [];
    foreach ($terpasang as $i) $bagian[] = $i['promo_kode'] . ' dipilih · ' . $i['nama'] . ' hemat ' . rupiah($i['diskon_nilai']);
    $tanpa = array_values(array_filter($items, fn($i)=>!$i['promo_kode']));
    $out = implode('. ', $bagian) . '. Satu promo per item';
    if ($tanpa) $out .= '. ' . implode(', ', array_column($tanpa,'nama')) . ' tidak mendapat diskon';
    return $out . '.';
}

switch ($aksi) {
    case 'muat': {
        echo json_encode(response_keranjang());
        break;
    }
    case 'tambah': {
        $menuId = (int)($body['menu_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM menu WHERE id=?');
        $st->execute([$menuId]); $m = $st->fetch();
        if (!$m || !$m['tersedia'] || $m['diarsipkan']) { echo json_encode(['ok'=>false,'pesan'=>'Menu tidak tersedia.']); exit; }
        $baru = $_SESSION['keranjang'];
        $qty = (int)(($baru[$menuId]['qty'] ?? 0) + 1);
        $baru[$menuId] = ['qty'=>$qty,'promo_id'=>$baru[$menuId]['promo_id'] ?? null,'catatan'=>$baru[$menuId]['catatan'] ?? ''];
        $err = validasi_stok($baru);
        if ($err) { echo json_encode(['ok'=>false,'pesan'=>$err]); exit; }
        $_SESSION['keranjang'] = $baru;
        echo json_encode(response_keranjang() + ['pesan'=>'+ '.$m['nama'].' ditambahkan']);
        break;
    }
    case 'ubah': {
        $menuId = (int)($body['menu_id'] ?? 0);
        $qty = (int)($body['qty'] ?? 0);
        $baru = $_SESSION['keranjang'];
        if ($qty <= 0) unset($baru[$menuId]);
        elseif (isset($baru[$menuId])) $baru[$menuId]['qty'] = $qty;
        else { echo json_encode(['ok'=>false,'pesan'=>'Item tidak ditemukan.']); exit; }
        $err = validasi_stok($baru);
        if ($err) { echo json_encode(['ok'=>false,'pesan'=>$err]); exit; }
        $_SESSION['keranjang'] = $baru;
        echo json_encode(response_keranjang());
        break;
    }
    case 'hapus': {
        unset($_SESSION['keranjang'][(int)($body['menu_id'] ?? 0)]);
        echo json_encode(response_keranjang());
        break;
    }
    case 'kosongkan': {
        $_SESSION['keranjang'] = [];
        echo json_encode(response_keranjang() + ['pesan'=>'Pesanan dikosongkan']);
        break;
    }
    case 'catatan': {
        $menuId = (int)($body['menu_id'] ?? 0);
        if (isset($_SESSION['keranjang'][$menuId])) $_SESSION['keranjang'][$menuId]['catatan'] = mb_substr(trim((string)($body['catatan'] ?? '')), 0, 200);
        echo json_encode(response_keranjang());
        break;
    }
    case 'voucher': {
        $menuId = (int)($body['menu_id'] ?? 0);
        $kode = strtoupper(trim((string)($body['kode'] ?? '')));
        if (!isset($_SESSION['keranjang'][$menuId])) { echo json_encode(['ok'=>false,'pesan'=>'Menu tidak ada di pesanan.']); exit; }
        $st = $pdo->prepare('SELECT * FROM promo WHERE UPPER(kode)=?');
        $st->execute([$kode]); $promo = $st->fetch();
        if (!$promo) { echo json_encode(['ok'=>false,'pesan'=>'Kode tidak ditemukan']); exit; }
        if (!$promo['aktif']) { echo json_encode(['ok'=>false,'pesan'=>'Promo sudah tidak aktif']); exit; }
        $today = date('Y-m-d');
        if (($promo['berlaku_mulai'] && $promo['berlaku_mulai']>$today) || ($promo['berlaku_sampai'] && $promo['berlaku_sampai']<$today)) {
            echo json_encode(['ok'=>false,'pesan'=>'Promo sudah tidak aktif']); exit;
        }
        if ((int)$promo['menu_id'] !== $menuId) { echo json_encode(['ok'=>false,'pesan'=>'Kode tidak berlaku untuk menu ini']); exit; }
        $_SESSION['keranjang'][$menuId]['promo_id'] = (int)$promo['id'];
        echo json_encode(response_keranjang() + ['pesan'=>'Voucher '.$promo['kode'].' terpasang']);
        break;
    }
    case 'lepas_voucher': {
        $menuId = (int)($body['menu_id'] ?? 0);
        if (isset($_SESSION['keranjang'][$menuId])) $_SESSION['keranjang'][$menuId]['promo_id'] = null;
        echo json_encode(response_keranjang() + ['pesan'=>'Voucher dilepas']);
        break;
    }
    default:
        echo json_encode(['ok'=>false,'pesan'=>'Aksi tidak dikenal.']);
}
