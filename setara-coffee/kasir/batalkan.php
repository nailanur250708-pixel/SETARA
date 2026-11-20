<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) { http_response_code(403); echo 'Permintaan tidak valid.'; exit; }
$pdo = db();
$id = (int)($_POST['id'] ?? 0);
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM transaksi WHERE id=? FOR UPDATE');
    $st->execute([$id]); $t = $st->fetch();
    if (!$t) throw new Exception('Transaksi tidak ditemukan.');
    if ($t['status'] === 'batal') throw new Exception('Transaksi sudah dibatalkan.');
    $items = $pdo->prepare('SELECT d.menu_id, d.qty FROM detail_transaksi d WHERE d.transaksi_id=?');
    $items->execute([$id]);
    foreach ($items->fetchAll() as $d) {
        $rs = $pdo->prepare('SELECT bahan_id, takaran FROM resep WHERE menu_id=?');
        $rs->execute([$d['menu_id']]);
        foreach ($rs->fetchAll() as $r) {
            $qty = (float)$r['takaran'] * (int)$d['qty'];
            $pdo->prepare('UPDATE bahan SET stok=stok+? WHERE id=?')->execute([$qty, $r['bahan_id']]);
            $pdo->prepare("INSERT INTO pergerakan_stok (bahan_id,tipe,qty,keterangan,user_id,tanggal) VALUES (?,'koreksi',?,'Pembatalan ".$t['kode']."',?,NOW())")
                ->execute([$r['bahan_id'], $qty, $_SESSION['user']['id']]);
        }
    }
    $pdo->prepare("UPDATE transaksi SET status='batal' WHERE id=?")->execute([$id]);
    $pdo->commit();
    flash('success', 'Transaksi '.$t['kode'].' dibatalkan. Stok bahan telah dikembalikan.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', 'Gagal membatalkan: '.$e->getMessage());
}
redirect('kasir/struk.php?id='.$id);
