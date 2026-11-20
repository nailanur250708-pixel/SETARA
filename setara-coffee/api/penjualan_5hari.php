<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);
header('Content-Type: application/json; charset=utf-8');

$today = new DateTime('today', new DateTimeZone('Asia/Makassar'));
$data = [];
for ($i = 4; $i >= 0; $i--) {
    $d = (clone $today)->modify("-$i days");
    $key = $d->format('Y-m-d');
    $st = db()->prepare("SELECT COALESCE(SUM(total),0) AS pendapatan, COUNT(*) AS trx FROM transaksi WHERE status='lunas' AND DATE(tanggal)=?");
    $st->execute([$key]);
    $r = $st->fetch();
    $data[] = [
        'tanggal'    => $key,
        'label'      => $d->format('j M'),
        'pendapatan' => (float)$r['pendapatan'],
        'transaksi'  => (int)$r['trx'],
    ];
}
$totalPendapatan = array_sum(array_column($data, 'pendapatan'));
$totalTransaksi = array_sum(array_column($data, 'transaksi'));
echo json_encode([
    'data' => $data,
    'rentang' => tanggal_indonesia($data[0]['tanggal'], false) . '–' . tanggal_indonesia($data[4]['tanggal'], false) . ' ' . $today->format('Y'),
    'total_pendapatan' => $totalPendapatan,
    'total_transaksi' => $totalTransaksi,
], JSON_UNESCAPED_UNICODE);
