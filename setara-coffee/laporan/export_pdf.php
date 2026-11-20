<?php
/**
 * laporan/export_pdf.php — Ekspor laporan operasional ke PDF (dompdf).
 * Parameter: tab (pendapatan|stok|cashflow), mulai, sampai (YYYY-MM-DD), pengelompokan.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['owner']);

/** Halaman error jelas (bukan blank/500). */
function laporan_pdf_error(string $judul, string $pesan, string $instruksi = ''): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($judul) . ' · Café Setara</title>'
        . '<link rel="stylesheet" href="' . e(base_url('assets/css/app.css')) . '"></head>'
        . '<body class="halaman-error"><div class="kotak-error"><div class="ikon-error">⚠️</div>'
        . '<p class="eyebrow">CAFÉ SETARA · EKSPOR PDF</p><h1>' . e($judul) . '</h1>'
        . '<p>' . e($pesan) . '</p>';
    if ($instruksi !== '') {
        echo '<p style="text-align:left;background:#F4EBDD;padding:12px;border-radius:8px">'
            . '<code>' . e($instruksi) . '</code></p>';
    }
    echo '<a class="btn btn-primary" href="' . e(base_url('laporan/index.php')) . '">Kembali ke laporan</a>'
        . '</div></body></html>';
    exit;
}

/* ------------------------------------------------------ Validasi parameter */
$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'pendapatan';
if (!in_array($tab, ['pendapatan', 'stok', 'cashflow'], true)) {
    laporan_pdf_error('Tab tidak valid', 'Parameter tab harus salah satu dari: pendapatan, stok, atau cashflow.');
}

function pdf_tanggal_valid($s): bool
{
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

$mulai = isset($_GET['mulai']) && pdf_tanggal_valid($_GET['mulai']) ? (string)$_GET['mulai'] : date('Y-m-01');
$sampai = isset($_GET['sampai']) && pdf_tanggal_valid($_GET['sampai']) ? (string)$_GET['sampai'] : date('Y-m-d');

if (isset($_GET['mulai']) && !pdf_tanggal_valid($_GET['mulai'])) {
    laporan_pdf_error('Tanggal mulai tidak valid', 'Format tanggal mulai harus YYYY-MM-DD, misalnya 2026-11-01.');
}
if (isset($_GET['sampai']) && !pdf_tanggal_valid($_GET['sampai'])) {
    laporan_pdf_error('Tanggal akhir tidak valid', 'Format tanggal akhir harus YYYY-MM-DD, misalnya 2026-11-18.');
}
if (strtotime($sampai) < strtotime($mulai)) {
    laporan_pdf_error(
        'Rentang tanggal tidak valid',
        'Tanggal akhir (' . $sampai . ') tidak boleh lebih awal dari tanggal mulai (' . $mulai . '). Perbaiki filter lalu coba lagi.'
    );
}

$pengelompokan = isset($_GET['pengelompokan']) ? (string)$_GET['pengelompokan'] : 'harian';
if (!in_array($pengelompokan, ['harian', 'mingguan', 'bulanan'], true)) {
    $pengelompokan = 'harian';
}

/* ---------------------------------------------------------- Ketersediaan */
if (!class_exists('Dompdf\Dompdf')) {
    laporan_pdf_error(
        'Library dompdf belum terpasang',
        'Fitur ekspor PDF membutuhkan library dompdf yang dipasang melalui Composer.',
        'composer require dompdf/dompdf:^3.1'
    );
}
if (!extension_loaded('gd')) {
    laporan_pdf_error(
        'Ekstensi GD belum aktif',
        'Dompdf membutuhkan ekstensi PHP gd untuk memproses gambar. Aktifkan ekstensi gd di php.ini (Laragon), lalu muat ulang halaman.',
        'extension=gd'
    );
}

/* ------------------------------------------------------ Helper lokal PDF */
function pdf_nama_bulan(int $bulan, bool $pendek = false): string
{
    $lengkap = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $pendekArr = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $daftar = $pendek ? $pendekArr : $lengkap;
    return $daftar[$bulan] ?? '';
}

$pdo = db();
$rentangTeks = tanggal_indonesia($mulai, false) . ' – ' . tanggal_indonesia($sampai, false);
$mulaiBesok = date('Y-m-d', strtotime($sampai . ' +1 day'));

/* ------------------------------------------------------ Data: pendapatan */
$kpi = ['pendapatan' => 0.0, 'jumlah' => 0, 'hpp' => 0.0, 'laba' => 0.0];
$data = [];

if ($tab === 'pendapatan') {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total),0) AS pendapatan,
                COUNT(*) AS jumlah,
                COALESCE(SUM(total_hpp),0) AS hpp
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $baris = $st->fetch();
    $kpi['pendapatan'] = (float)($baris['pendapatan'] ?? 0);
    $kpi['jumlah'] = (int)($baris['jumlah'] ?? 0);
    $kpi['hpp'] = (float)($baris['hpp'] ?? 0);
    $kpi['laba'] = $kpi['pendapatan'] - $kpi['hpp'];

    if ($pengelompokan === 'mingguan') {
        $sql = "SELECT YEARWEEK(tanggal,1) AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY YEARWEEK(tanggal,1)
                 ORDER BY kunci";
    } elseif ($pengelompokan === 'bulanan') {
        $sql = "SELECT DATE_FORMAT(tanggal,'%Y-%m') AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY DATE_FORMAT(tanggal,'%Y-%m')
                 ORDER BY kunci";
    } else {
        $sql = "SELECT DATE(tanggal) AS kunci, DATE(MIN(tanggal)) AS contoh,
                       COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS pendapatan,
                       COALESCE(SUM(total_hpp),0) AS hpp
                  FROM transaksi
                 WHERE status = ? AND tanggal >= ? AND tanggal < ?
                 GROUP BY DATE(tanggal)
                 ORDER BY kunci";
    }
    $st = $pdo->prepare($sql);
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $rincian = [];
    foreach ($st->fetchAll() as $r) {
        $contoh = $r['contoh'] ?: $mulai;
        $ts = strtotime((string)$contoh);
        if ($pengelompokan === 'mingguan') {
            $label = 'Minggu ke-' . date('W', $ts) . ' (' . date('j', $ts) . ' ' . pdf_nama_bulan((int)date('n', $ts), true) . ')';
        } elseif ($pengelompokan === 'bulanan') {
            $label = pdf_nama_bulan((int)date('n', $ts)) . ' ' . date('Y', $ts);
        } else {
            $label = tanggal_indonesia((string)$r['kunci'], false);
        }
        $rincian[] = [
            'label' => $label,
            'jumlah' => (int)$r['jumlah'],
            'pendapatan' => (float)$r['pendapatan'],
            'hpp' => (float)$r['hpp'],
            'laba' => (float)$r['pendapatan'] - (float)$r['hpp'],
        ];
    }
    $data = ['rincian' => $rincian];
    $judul = 'Laporan Pendapatan';
}

/* ----------------------------------------------------------- Data: stok */
if ($tab === 'stok') {
    $bahan = [];
    foreach ($pdo->query('SELECT nama, satuan, stok, stok_minimum FROM bahan ORDER BY nama')->fetchAll() as $b) {
        $stok = (float)$b['stok'];
        $min = (float)$b['stok_minimum'];
        if ($stok <= $min) {
            $status = 'Kritis'; $kelas = 'badge-merah';
        } elseif ($stok <= $min * 1.5) {
            $status = 'Waspada'; $kelas = 'badge-kuning';
        } else {
            $status = 'Aman'; $kelas = 'badge-hijau';
        }
        $bahan[] = [
            'nama' => (string)$b['nama'],
            'satuan' => (string)$b['satuan'],
            'stok' => $stok,
            'minimum' => $min,
            'status' => $status,
            'kelas' => $kelas,
        ];
    }
    $kritis = [];
    foreach (bahan_kritis() as $b) {
        $kritis[] = (string)$b['nama'];
    }
    $data = ['bahan' => $bahan, 'kritis' => $kritis];
    $judul = 'Laporan Stok';
}

/* ------------------------------------------------------- Data: cashflow */
if ($tab === 'cashflow') {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total),0) FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $pendapatan = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah),0) FROM kas
          WHERE tipe = 'keluar' AND kategori = 'Pembelian bahan'
            AND tanggal >= ? AND tanggal < ?"
    );
    $st->execute([$mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $pembelian = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(jumlah),0) FROM kas
          WHERE tipe = 'keluar' AND (kategori <> 'Pembelian bahan' OR kategori IS NULL)
            AND tanggal >= ? AND tanggal < ?"
    );
    $st->execute([$mulai . ' 00:00:00', $mulaiBesok . ' 00:00:00']);
    $operasional = (float)$st->fetchColumn();

    $data = [
        'pendapatan' => $pendapatan,
        'pembelian' => $pembelian,
        'operasional' => $operasional,
        'saldo' => $pendapatan - $pembelian - $operasional,
    ];
    $judul = 'Laporan Cash Flow';
}

/* ------------------------------------------------------------- Render HTML */
$namaPencetak = (string)($_SESSION['user']['nama'] ?? 'Pengguna');
$waktuCetak = tanggal_indonesia(date('Y-m-d')) . ' pukul ' . date('H:i');
$periodeTeks = tanggal_indonesia($mulai, false) . ' – ' . tanggal_indonesia($sampai, false) . ' ' . date('Y', strtotime($sampai));

ob_start();
require __DIR__ . '/../views/pdf/' . $tab . '.php';
$konten = ob_get_clean();

ob_start();
require __DIR__ . '/../views/pdf/layout_pdf.php';
$html = ob_get_clean();

/* ----------------------------------------------------------------- Dompdf */
$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', $tab === 'pendapatan' ? 'landscape' : 'portrait');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
    $text = 'Café Setara · Halaman ' . $pageNumber . ' dari ' . $pageCount;
    $width = $canvas->get_width();
    $tinggi = $canvas->get_height();
    $y = $tinggi - 18;
    $font = 'DejaVu Sans'; $size = 8;
    $lebarTeks = $canvas->get_text_width($text, $font, $size);
    $canvas->text(($width - $lebarTeks) / 2, $y, $text, $font, $size, [0, 0, 0]);
});

$pdf = $dompdf->output();
if (ob_get_level() > 0) {
    ob_end_clean();
}
$namaFile = 'laporan-' . $tab . '-' . $mulai . '-' . $sampai . '.pdf';
header('Content-Type: application/pdf');
// Default: tampilkan di tab browser agar tidak bergantung pada pembuka file lokal.
// Tambahkan ?download=1 pada URL bila pengguna ingin memaksa unduhan.
$mode = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Disposition: ' . $mode . '; filename="' . $namaFile . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $pdf;
exit;
