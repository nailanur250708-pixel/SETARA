<?php
/**
 * laporan/pdf.php — Generator PDF operasional Café Setara (mPDF).
 *
 * PDF dibuat sepenuhnya di sisi server. Teks dapat dipilih/disalin dan tidak
 * bergantung pada window.print(), html2canvas, maupun gambar.
 *
 * Parameter GET:
 *   jenis          stok | pendapatan | cashflow   (alias: tab)
 *   tanggal_mulai  YYYY-MM-DD                     (alias: mulai)
 *   tanggal_akhir  YYYY-MM-DD                     (alias: sampai)
 *   pengelompokan  harian | mingguan | bulanan
 *   mode           inline | download               (default: inline)
 *
 * Contoh:
 *   laporan/pdf.php?jenis=pendapatan&tanggal_mulai=2026-11-01
 *                    &tanggal_akhir=2026-11-24&pengelompokan=harian&mode=download
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Jangan pernah menampilkan warning/notice ke dalam stream PDF.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ob_start();

require_login();
require_role(['owner']);

/* ------------------------------------------------------ Validasi parameter */
function pdf_tanggal_valid($nilai): bool
{
    if (!is_string($nilai) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/** Tampilkan halaman error yang jelas, bukan PDF rusak. */
function pdf_error(int $status, string $judul, string $pesan): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($judul) . ' · Café Setara</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#FBF6EE;color:#3B1F0E;padding:40px}'
        . '.box{background:#fff;border:1px solid #E6DCCA;border-radius:14px;padding:28px;max-width:680px}'
        . 'h1{font-size:24px;margin:0 0 10px;color:#5C3317}p{line-height:1.6}'
        . 'a{color:#5C3317}</style></head><body><div class="box">'
        . '<h1>' . e($judul) . '</h1><p>' . e($pesan) . '</p>'
        . '<p><a href="' . e(base_url('laporan/index.php')) . '">Kembali ke laporan</a></p>'
        . '</div></body></html>';
    exit;
}

$jenis = $_GET['jenis'] ?? ($_GET['tab'] ?? 'pendapatan');
if (!is_string($jenis) || !in_array($jenis, ['stok', 'pendapatan', 'cashflow'], true)) {
    pdf_error(400, 'Jenis laporan tidak valid', 'Pilih salah satu: stok, pendapatan, atau cashflow.');
}

$mulai = $_GET['tanggal_mulai'] ?? ($_GET['mulai'] ?? date('Y-m-01'));
$sampai = $_GET['tanggal_akhir'] ?? ($_GET['sampai'] ?? date('Y-m-d'));
if (!is_string($mulai) || !pdf_tanggal_valid($mulai)) {
    pdf_error(400, 'Tanggal mulai tidak valid', 'Format tanggal mulai harus YYYY-MM-DD, misalnya 2026-11-01.');
}
if (!is_string($sampai) || !pdf_tanggal_valid($sampai)) {
    pdf_error(400, 'Tanggal akhir tidak valid', 'Format tanggal akhir harus YYYY-MM-DD, misalnya 2026-11-24.');
}
if (strtotime($sampai) < strtotime($mulai)) {
    pdf_error(400, 'Rentang tanggal tidak valid', 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
}

$pengelompokan = $_GET['pengelompokan'] ?? 'harian';
if (!is_string($pengelompokan) || !in_array($pengelompokan, ['harian', 'mingguan', 'bulanan'], true)) {
    pdf_error(400, 'Pengelompokan tidak valid', 'Pilih salah satu: harian, mingguan, atau bulanan.');
}

$mode = $_GET['mode'] ?? (isset($_GET['download']) ? 'download' : 'inline');
if (!is_string($mode) || !in_array($mode, ['inline', 'download'], true)) {
    pdf_error(400, 'Mode PDF tidak valid', 'Pilih salah satu: inline atau download.');
}

if (!class_exists('\\Mpdf\\Mpdf')) {
    pdf_error(
        500,
        'Library mPDF belum terpasang',
        'Jalankan "composer require mpdf/mpdf" di folder aplikasi, lalu muat ulang halaman ini.'
    );
}

/* ---------------------------------------------------------- Helper laporan */
$ALAMAT_KOP = 'Jl. Towerkanjovank, Tamalanrea Indah, Kec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245';

function pdf_nama_bulan(int $bulan, bool $pendek = false): string
{
    $lengkap = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $pendekArr = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $daftar = $pendek ? $pendekArr : $lengkap;
    return $daftar[$bulan] ?? '';
}

/** Angka kuantitas: 5.000 atau 1,5 (bukan format rupiah). */
function pdf_qty($angka): string
{
    $angka = (float)$angka;
    if (abs($angka - round($angka)) < 0.00001) {
        return number_format($angka, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($angka, 2, ',', '.'), '0'), ',');
}

function pdf_tanggal_singkat(string $tanggal): string
{
    $ts = strtotime($tanggal);
    if ($ts === false) {
        return $tanggal;
    }
    return date('j', $ts) . ' ' . pdf_nama_bulan((int)date('n', $ts), true) . ' ' . date('Y', $ts) . ' ' . date('H:i', $ts);
}

function pdf_label_metode(string $metode): string
{
    $map = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit'];
    return $map[strtolower($metode)] ?? ucfirst($metode);
}

function pdf_label_status(string $status): string
{
    $map = ['lunas' => 'Lunas', 'batal' => 'Batal'];
    return $map[strtolower($status)] ?? ucfirst($status);
}

function pdf_label_pengelompokan(string $pengelompokan): string
{
    $map = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];
    return $map[$pengelompokan] ?? 'Harian';
}

function pdf_section(string $judul): string
{
    return '<h2 style="font-size:12pt;color:#5C3317;margin:16px 0 8px;padding-bottom:4px;border-bottom:1px solid #D9B98E">'
        . e($judul) . '</h2>';
}

/**
 * Bangun tabel HTML dengan header berulang di setiap halaman.
 *
 * @param array $headers Daftar header: [label, 'left'|'right'|'center', lebar%]
 * @param array $rows    Baris berisi nilai mentah (akan di-escape).
 * @param array|null $total Baris total (opsional).
 */
function pdf_table(array $headers, array $rows, string $pesanKosong, ?array $total = null): string
{
    $html = '<table class="data" style="width:100%;border-collapse:collapse;margin:4px 0 8px">'
        . '<thead><tr>';
    foreach ($headers as $h) {
        $label = (string)($h[0] ?? '');
        $align = (string)($h[1] ?? 'left');
        $width = isset($h[2]) ? 'width:' . (float)$h[2] . '%;' : '';
        $html .= '<th style="' . $width . 'text-align:' . e($align) . '">' . e($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!$rows) {
        $html .= '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#5A4A3F;font-style:italic;padding:10px 6px">'
            . e($pesanKosong) . '</td></tr>';
    } else {
        foreach ($rows as $i => $row) {
            $kelas = $i % 2 ? ' class="alt"' : '';
            $html .= '<tr' . $kelas . '>';
            foreach ($row as $j => $sel) {
                $align = (string)($headers[$j][1] ?? 'left');
                $html .= '<td style="text-align:' . e($align) . '">' . e((string)$sel) . '</td>';
            }
            $html .= '</tr>';
        }
    }

    if ($total !== null) {
        $html .= '<tr class="total">';
        foreach ($total as $j => $sel) {
            $align = (string)($headers[$j][1] ?? 'left');
            $html .= '<td style="text-align:' . e($align) . '">' . e((string)$sel) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

/** Kartu ringkasan (3 kolom untuk 3/6 kartu, 2 kolom untuk 4 kartu). */
function pdf_kpi(array $items): string
{
    $jumlah = count($items);
    $kolom = $jumlah === 4 ? 2 : 3;
    $lebar = round(100 / $kolom, 2);
    $html = '<table style="width:100%;border-collapse:separate;border-spacing:4px;margin:2px 0 8px;table-layout:fixed;page-break-inside:avoid">';
    for ($i = 0; $i < $jumlah; $i += $kolom) {
        $html .= '<tr>';
        for ($j = 0; $j < $kolom; $j++) {
            $item = $items[$i + $j] ?? null;
            if ($item === null) {
                $html .= '<td style="width:' . $lebar . '%"></td>';
                continue;
            }
            $html .= '<td style="width:' . $lebar . '%;border:1px solid #E6DCCA;background:#FBF6EE;padding:8px;vertical-align:top">'
                . '<div style="font-size:9pt;color:#8A7A6B;font-weight:bold;text-transform:uppercase;letter-spacing:.4px">' . e((string)$item[0]) . '</div>'
                . '<div style="font-size:13pt;color:#3B1F0E;font-weight:bold;margin-top:3px">' . e((string)$item[1]) . '</div>'
                . '<div style="font-size:9pt;color:#8A7A6B;margin-top:2px">' . e((string)($item[2] ?? '')) . '</div>'
                . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

function pdf_kop(string $judul, string $periodeTeks, string $namaPencetak, string $waktuCetak, string $alamat): string
{
    return '<table style="width:100%;border-collapse:collapse;margin-bottom:8px"><tr>'
        . '<td style="width:68%;vertical-align:top">'
        . '<div style="font-size:21pt;color:#5C3317;font-weight:bold;line-height:1.1">Café Setara</div>'
        . '<div style="font-size:9pt;color:#8A7A6B;font-weight:bold;letter-spacing:2px;margin-top:3px">RUANG KELOLA KEDAI</div>'
        . '</td>'
        . '<td style="width:32%;text-align:right;vertical-align:top;font-size:9pt;color:#5A4A3F;line-height:1.5">'
        . e($alamat)
        . '</td></tr></table>'
        . '<div style="border-top:2px solid #5C3317;margin:4px 0 12px;height:0"></div>'
        . '<div style="text-align:center;margin-bottom:12px">'
        . '<div style="font-size:16pt;font-weight:bold;color:#3B1F0E;margin-top:2px">' . e($judul) . '</div>'
        . '<div style="font-size:9pt;color:#5A4A3F;margin-top:4px">Periode: ' . e($periodeTeks) . '</div>'
        . '<div style="font-size:9pt;color:#8A7A6B;margin-top:2px">Dicetak oleh ' . e($namaPencetak) . ' &middot; ' . e($waktuCetak) . ' (WITA)</div>'
        . '</div>';
}

function pdf_ttd(string $nama): string
{
    return '<table style="width:100%;border-collapse:collapse;margin-top:26px;page-break-inside:avoid"><tr>'
        . '<td style="width:55%"></td>'
        . '<td style="width:45%;text-align:left;font-size:9pt;color:#3B1F0E">'
        . '<div style="color:#5A4A3F">Penanggung jawab</div>'
        . '<table style="width:190px;border-collapse:collapse;margin-top:32px"><tr>'
        . '<td style="border-top:1px solid #8A7A6B;padding-top:4px;font-weight:bold">' . e($nama) . '</td>'
        . '</tr></table>'
        . '</td></tr></table>';
}

/* -------------------------------------------------------------- Data awal */
$pdo = db();
$mulaiBesok = date('Y-m-d', strtotime($sampai . ' +1 day'));
$mulaiSql = $mulai . ' 00:00:00';
$sampaiSql = $mulaiBesok . ' 00:00:00';
$periodeTeks = tanggal_indonesia($mulai, false) . ' – ' . tanggal_indonesia($sampai, false);
$waktuCetak = tanggal_indonesia(date('Y-m-d'), true) . ' pukul ' . date('H:i');
$namaPencetak = (string)($_SESSION['user']['nama'] ?? 'Pengguna');
$judul = $jenis === 'pendapatan' ? 'Laporan Pendapatan' : ($jenis === 'stok' ? 'Laporan Stok' : 'Laporan Cash Flow');

/* --------------------------------------------------------- Data pendapatan */
$kpi = [
    'pendapatan' => 0.0,
    'jumlah' => 0,
    'hpp' => 0.0,
    'laba' => 0.0,
    'diskon' => 0.0,
];
$rincian = [];
$transaksi = [];
$menuTerjual = [];
$metode = [];
$kasir = [];
$hppKosong = [];

if ($jenis === 'pendapatan') {
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS jumlah,
                COALESCE(SUM(total),0) AS pendapatan,
                COALESCE(SUM(total_hpp),0) AS hpp,
                COALESCE(SUM(total_diskon),0) AS diskon
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $baris = $st->fetch();
    $kpi['pendapatan'] = (float)($baris['pendapatan'] ?? 0);
    $kpi['jumlah'] = (int)($baris['jumlah'] ?? 0);
    $kpi['hpp'] = (float)($baris['hpp'] ?? 0);
    $kpi['laba'] = $kpi['pendapatan'] - $kpi['hpp'];
    $kpi['diskon'] = (float)($baris['diskon'] ?? 0);

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
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
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

    $st = $pdo->prepare(
        'SELECT t.kode, t.tanggal, COALESCE(NULLIF(u.nama, \'\'), u.username, \'-\') AS kasir,
                t.metode_bayar, t.total, t.status, COALESCE(dt.qty,0) AS jumlah_item
           FROM transaksi t
           LEFT JOIN users u ON u.id = t.users_id
           LEFT JOIN (SELECT transaksi_id, SUM(qty) AS qty FROM detail_transaksi GROUP BY transaksi_id) dt
                  ON dt.transaksi_id = t.id
          WHERE t.status = ? AND t.tanggal >= ? AND t.tanggal < ?
          ORDER BY t.tanggal, t.id'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $transaksi = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT m.nama, COALESCE(NULLIF(m.kategori, \'\'), c.nama, \'-\') AS kategori,
                SUM(d.qty) AS terjual,
                COALESCE(SUM(d.subtotal),0) AS pendapatan,
                COALESCE(SUM(COALESCE(d.hpp_satuan,0) * d.qty),0) AS hpp
           FROM detail_transaksi d
           JOIN menu m ON m.id = d.menu_id
           LEFT JOIN kategori c ON c.id = m.kategori_id
           JOIN transaksi t ON t.id = d.transaksi_id
          WHERE t.status = ? AND t.tanggal >= ? AND t.tanggal < ?
          GROUP BY m.id, m.nama, m.kategori, c.nama
          ORDER BY terjual DESC, pendapatan DESC, m.nama'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $menuTerjual = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT metode_bayar, COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS total
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?
          GROUP BY metode_bayar
          ORDER BY total DESC, metode_bayar'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $metode = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT COALESCE(NULLIF(u.nama, \'\'), u.username, \'-\') AS nama_kasir,
                COUNT(*) AS jumlah, COALESCE(SUM(t.total),0) AS total
           FROM transaksi t
           LEFT JOIN users u ON u.id = t.users_id
          WHERE t.status = ? AND t.tanggal >= ? AND t.tanggal < ?
          GROUP BY u.id, u.nama, u.username
          ORDER BY total DESC, nama_kasir'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $kasir = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT m.nama, SUM(d.qty) AS qty
           FROM detail_transaksi d
           JOIN menu m ON m.id = d.menu_id
           JOIN transaksi t ON t.id = d.transaksi_id
          WHERE t.status = ? AND t.tanggal >= ? AND t.tanggal < ?
          GROUP BY m.id, m.nama
         HAVING MIN(COALESCE(d.hpp_satuan,0)) <= 0
          ORDER BY m.nama'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $hppKosong = $st->fetchAll();
}

/* --------------------------------------------------------------- Data stok */
$bahan = [];
$stokRingkasan = ['jumlah' => 0, 'kritis' => 0, 'habis' => 0, 'nilai' => 0.0];
$mutasi = [];
$mutasiRingkasan = ['Masuk' => 0, 'Keluar' => 0, 'Penyesuaian' => 0];

if ($jenis === 'stok') {
    $bahan = $pdo->query(
        'SELECT nama, satuan, stok, stok_minimum, COALESCE(harga_beli_per_satuan,0) AS harga
           FROM bahan
          ORDER BY nama'
    )->fetchAll();

    foreach ($bahan as $b) {
        $stok = (float)$b['stok'];
        $min = (float)$b['stok_minimum'];
        $stokRingkasan['jumlah']++;
        if ($stok <= $min) {
            $stokRingkasan['kritis']++;
        }
        if ($stok <= 0) {
            $stokRingkasan['habis']++;
        }
        $stokRingkasan['nilai'] += $stok * (float)$b['harga'];
    }

    $st = $pdo->prepare(
        'SELECT p.tanggal, b.nama AS bahan, b.satuan, p.tipe, p.jenis, p.jumlah, p.qty, p.keterangan,
                COALESCE(NULLIF(u.nama, \'\'), u.username, \'-\') AS pengguna
           FROM pergerakan_stok p
           JOIN bahan b ON b.id = p.bahan_id
           LEFT JOIN users u ON u.id = COALESCE(p.users_id, p.user_id)
          WHERE p.tanggal >= ? AND p.tanggal < ?
          ORDER BY p.tanggal, p.id'
    );
    $st->execute([$mulaiSql, $sampaiSql]);
    foreach ($st->fetchAll() as $r) {
        $tipe = strtolower((string)($r['tipe'] ?? ''));
        $jenisGerak = strtolower((string)($r['jenis'] ?? ''));
        $qty = (float)($r['qty'] ?? 0);
        if (abs($qty) < 0.00001) {
            $qty = (float)($r['jumlah'] ?? 0);
        }

        if ($tipe === 'masuk') {
            $kelompok = 'Masuk';
            $tanda = '+';
            $nilai = abs($qty);
        } elseif ($tipe === 'penjualan' || $jenisGerak === 'keluar') {
            $kelompok = 'Keluar';
            $tanda = '−';
            $nilai = abs($qty);
        } elseif ($tipe === 'koreksi') {
            $kelompok = 'Penyesuaian';
            $tanda = $qty < 0 ? '−' : '+';
            $nilai = abs($qty);
        } else {
            $kelompok = $jenisGerak === 'masuk' ? 'Masuk' : 'Keluar';
            $tanda = $kelompok === 'Masuk' ? '+' : '−';
            $nilai = abs($qty);
        }

        $mutasiRingkasan[$kelompok]++;
        $mutasi[] = [
            'tanggal' => $r['tanggal'],
            'bahan' => $r['bahan'],
            'satuan' => $r['satuan'],
            'kelompok' => $kelompok,
            'jumlah' => $tanda . pdf_qty($nilai),
            'keterangan' => $r['keterangan'] ?: '-',
            'pengguna' => $r['pengguna'] ?: '-',
        ];
    }
}

/* ----------------------------------------------------------- Data cash flow */
$cf = ['masuk' => 0.0, 'keluar' => 0.0, 'saldo' => 0.0];
$cfMasukKategori = [];
$cfKeluarKategori = [];
$cfHarian = [];

if ($jenis === 'cashflow') {
    $penjualanKategori = ['kategori' => 'Penjualan', 'jumlah' => 0, 'total' => 0.0];
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS total
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $baris = $st->fetch();
    $penjualanKategori['jumlah'] = (int)($baris['jumlah'] ?? 0);
    $penjualanKategori['total'] = (float)($baris['total'] ?? 0);

    $st = $pdo->prepare(
        'SELECT DATE(tanggal) AS tgl, COUNT(*) AS jumlah, COALESCE(SUM(total),0) AS total
           FROM transaksi
          WHERE status = ? AND tanggal >= ? AND tanggal < ?
          GROUP BY DATE(tanggal)
          ORDER BY tgl'
    );
    $st->execute(['lunas', $mulaiSql, $sampaiSql]);
    $penjualanHarian = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT COALESCE(NULLIF(kategori, ''), 'Lain-lain') AS kategori,
                COUNT(*) AS jumlah, COALESCE(SUM(jumlah),0) AS total
           FROM kas
          WHERE COALESCE(tipe, jenis) = 'masuk'
            AND transaksi_id IS NULL
            AND (kategori IS NULL OR kategori NOT IN ('Penjualan', 'Pendapatan'))
            AND tanggal >= ? AND tanggal < ?
          GROUP BY COALESCE(NULLIF(kategori, ''), 'Lain-lain')
          ORDER BY total DESC, kategori"
    );
    $st->execute([$mulaiSql, $sampaiSql]);
    $kasMasukLain = $st->fetchAll();

    if ($penjualanKategori['jumlah'] > 0 || $penjualanKategori['total'] > 0) {
        $cfMasukKategori[] = $penjualanKategori;
    }
    foreach ($kasMasukLain as $r) {
        $cfMasukKategori[] = [
            'kategori' => (string)$r['kategori'],
            'jumlah' => (int)$r['jumlah'],
            'total' => (float)$r['total'],
        ];
    }

    $st = $pdo->prepare(
        "SELECT COALESCE(NULLIF(kategori, ''), 'Lain-lain') AS kategori,
                COUNT(*) AS jumlah, COALESCE(SUM(jumlah),0) AS total
           FROM kas
          WHERE COALESCE(tipe, jenis) = 'keluar' AND tanggal >= ? AND tanggal < ?
          GROUP BY COALESCE(NULLIF(kategori, ''), 'Lain-lain')
          ORDER BY total DESC, kategori"
    );
    $st->execute([$mulaiSql, $sampaiSql]);
    foreach ($st->fetchAll() as $r) {
        $cfKeluarKategori[] = [
            'kategori' => (string)$r['kategori'],
            'jumlah' => (int)$r['jumlah'],
            'total' => (float)$r['total'],
        ];
    }

    $st = $pdo->prepare(
        "SELECT DATE(tanggal) AS tgl, COALESCE(SUM(jumlah),0) AS total
           FROM kas
          WHERE COALESCE(tipe, jenis) = 'keluar' AND tanggal >= ? AND tanggal < ?
          GROUP BY DATE(tanggal)
          ORDER BY tgl"
    );
    $st->execute([$mulaiSql, $sampaiSql]);
    $kasKeluarHarian = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT DATE(tanggal) AS tgl, COALESCE(SUM(jumlah),0) AS total
           FROM kas
          WHERE COALESCE(tipe, jenis) = 'masuk'
            AND transaksi_id IS NULL
            AND (kategori IS NULL OR kategori NOT IN ('Penjualan', 'Pendapatan'))
            AND tanggal >= ? AND tanggal < ?
          GROUP BY DATE(tanggal)
          ORDER BY tgl"
    );
    $st->execute([$mulaiSql, $sampaiSql]);
    $kasMasukLainHarian = $st->fetchAll();

    $harian = [];
    foreach ($penjualanHarian as $r) {
        $tgl = (string)$r['tgl'];
        $harian[$tgl]['masuk'] = ($harian[$tgl]['masuk'] ?? 0) + (float)$r['total'];
    }
    foreach ($kasMasukLainHarian as $r) {
        $tgl = (string)$r['tgl'];
        $harian[$tgl]['masuk'] = ($harian[$tgl]['masuk'] ?? 0) + (float)$r['total'];
    }
    foreach ($kasKeluarHarian as $r) {
        $tgl = (string)$r['tgl'];
        $harian[$tgl]['keluar'] = ($harian[$tgl]['keluar'] ?? 0) + (float)$r['total'];
    }
    ksort($harian);

    $masuk = 0.0;
    foreach ($cfMasukKategori as $r) {
        $masuk += $r['total'];
    }
    $keluar = 0.0;
    foreach ($cfKeluarKategori as $r) {
        $keluar += $r['total'];
    }
    $cf['masuk'] = $masuk;
    $cf['keluar'] = $keluar;
    $cf['saldo'] = $masuk - $keluar;

    $jalan = 0.0;
    foreach ($harian as $tgl => $nilai) {
        $masukHari = (float)($nilai['masuk'] ?? 0);
        $keluarHari = (float)($nilai['keluar'] ?? 0);
        $jalan += $masukHari - $keluarHari;
        $cfHarian[] = [
            'tanggal' => $tgl,
            'masuk' => $masukHari,
            'keluar' => $keluarHari,
            'saldo' => $jalan,
        ];
    }
}

/* --------------------------------------------------------------- Render HTML */
$html = '<!doctype html><html lang="id"><head><meta charset="UTF-8"><title>' . e($judul) . ' · Café Setara</title>'
    . '<style>'
    . 'body{font-family:dejavusans,sans-serif;font-size:9pt;color:#2E1B0C;line-height:1.45}'
    . 'h1,h2,h3{font-family:dejavusans,sans-serif}'
    . 'h2{page-break-after:avoid}'
    . 'p{margin:4px 0}'
    . 'table.data{width:100%;border-collapse:collapse;margin:4px 0 8px}'
    . 'table.data thead{display:table-header-group}'
    . 'table.data th{background:#5C3317;color:#FBF6EE;font-size:9pt;font-weight:bold;padding:5px 5px;border:1px solid #5C3317;vertical-align:middle}'
    . 'table.data td{font-size:9pt;padding:4px 5px;border-bottom:1px solid #D9B98E;vertical-align:top;color:#2E1B0C}'
    . 'table.data tr.alt td{background:#FBF6EE}'
    . 'table.data tr.total td{background:#F4EBDD;font-weight:bold;border-top:1.5px solid #5C3317;border-bottom:1.5px solid #5C3317}'
    . 'table.data tr{page-break-inside:avoid}'
    . '.muted{color:#5A4A3F}'
    . '.note{font-size:9pt;color:#5A4A3F;background:#FBF6EE;border:1px solid #E6DCCA;padding:8px 10px;margin-top:10px}'
    . '.warning{font-size:9pt;color:#7A3E00;background:#FFF4D6;border:1px solid #E8D49B;padding:8px 10px;margin:8px 0}'
    . '</style></head><body>';

$html .= pdf_kop($judul, $periodeTeks, $namaPencetak, $waktuCetak, $ALAMAT_KOP);

if ($jenis === 'pendapatan') {
    $margin = $kpi['pendapatan'] > 0 ? ($kpi['laba'] / $kpi['pendapatan']) * 100 : 0;
    $marginTeks = rtrim(rtrim(number_format($margin, 1, ',', '.'), '0'), ',') . '%';

    /* 1. Ringkasan */
    $html .= pdf_section('1. Ringkasan');
    $html .= pdf_kpi([
        ['Pendapatan', rupiah($kpi['pendapatan']), 'Transaksi lunas pada periode ini'],
        ['Total transaksi', (string)$kpi['jumlah'], 'Transaksi berstatus lunas'],
        ['Rata-rata / transaksi', rupiah($kpi['jumlah'] > 0 ? $kpi['pendapatan'] / $kpi['jumlah'] : 0), 'Pendapatan dibagi jumlah transaksi'],
        ['HPP penjualan', rupiah($kpi['hpp']), 'Snapshot HPP saat transaksi terjadi'],
        ['Laba kotor', rupiah($kpi['laba']), 'Margin ' . $marginTeks . ' dari pendapatan'],
        ['Total diskon', rupiah($kpi['diskon']), $kpi['diskon'] > 0 ? 'Diskon pada periode ini' : 'Tidak ada diskon'],
    ]);

    /* 2. Rincian per periode */
    $html .= pdf_section('2. Rincian per periode (' . pdf_label_pengelompokan($pengelompokan) . ')');
    $rows = [];
    foreach ($rincian as $r) {
        $rows[] = [$r['label'], (string)$r['jumlah'], rupiah($r['pendapatan']), rupiah($r['hpp']), rupiah($r['laba'])];
    }
    $html .= pdf_table(
        [['Periode', 'left', 30], ['Transaksi', 'right', 13], ['Pendapatan', 'right', 20], ['HPP', 'right', 18], ['Laba', 'right', 19]],
        $rows,
        'Tidak ada transaksi pada periode ini.',
        ['TOTAL', (string)$kpi['jumlah'], rupiah($kpi['pendapatan']), rupiah($kpi['hpp']), rupiah($kpi['laba'])]
    );

    /* 3. Rincian per transaksi */
    $html .= pdf_section('3. Rincian per transaksi');
    $rows = [];
    foreach ($transaksi as $t) {
        $rows[] = [
            $t['kode'],
            pdf_tanggal_singkat((string)$t['tanggal']),
            $t['kasir'] ?: '-',
            pdf_label_metode((string)$t['metode_bayar']),
            (string)(int)$t['jumlah_item'],
            rupiah($t['total']),
            pdf_label_status((string)$t['status']),
        ];
    }
    $html .= pdf_table(
        [['Nomor transaksi', 'left', 20], ['Tanggal & jam', 'left', 18], ['Kasir', 'left', 15],
         ['Metode', 'left', 11], ['Item', 'right', 7], ['Total', 'right', 15], ['Status', 'left', 14]],
        $rows,
        'Tidak ada transaksi pada periode ini.'
    );

    /* 4. Penjualan per menu */
    $html .= pdf_section('4. Penjualan per menu (terlaris pertama)');
    $rows = [];
    $totalTerjual = 0;
    $totalMenuPendapatan = 0.0;
    $totalMenuHpp = 0.0;
    foreach ($menuTerjual as $m) {
        $laba = (float)$m['pendapatan'] - (float)$m['hpp'];
        $totalTerjual += (int)$m['terjual'];
        $totalMenuPendapatan += (float)$m['pendapatan'];
        $totalMenuHpp += (float)$m['hpp'];
        $rows[] = [
            $m['nama'],
            $m['kategori'] ?: '-',
            (string)(int)$m['terjual'],
            rupiah($m['pendapatan']),
            rupiah($m['hpp']),
            rupiah($laba),
        ];
    }
    $html .= pdf_table(
        [['Menu', 'left', 31], ['Kategori', 'left', 15], ['Terjual', 'right', 9], ['Pendapatan', 'right', 15], ['HPP', 'right', 15], ['Laba', 'right', 15]],
        $rows,
        'Tidak ada penjualan menu pada periode ini.',
        ['TOTAL', '', (string)$totalTerjual, rupiah($totalMenuPendapatan), rupiah($totalMenuHpp), rupiah($totalMenuPendapatan - $totalMenuHpp)]
    );

    /* 5. Metode pembayaran */
    $html .= '<div style="page-break-inside:avoid">';
    $html .= pdf_section('5. Pendapatan per metode pembayaran');
    $rows = [];
    $totalMetodeTrx = 0;
    $totalMetode = 0.0;
    foreach ($metode as $m) {
        $totalMetodeTrx += (int)$m['jumlah'];
        $totalMetode += (float)$m['total'];
        $rows[] = [pdf_label_metode((string)$m['metode_bayar']), (string)(int)$m['jumlah'], rupiah($m['total'])];
    }
    $html .= pdf_table(
        [['Metode pembayaran', 'left', 50], ['Transaksi', 'right', 20], ['Total', 'right', 30]],
        $rows,
        'Tidak ada transaksi pada periode ini.',
        ['TOTAL', (string)$totalMetodeTrx, rupiah($totalMetode)]
    );
    $html .= '</div>';

    /* 6. Kasir — hanya bila lebih dari satu. */
    $nomorBagian = 6;
    if (count($kasir) > 1) {
        $html .= '<div style="page-break-inside:avoid">';
        $html .= pdf_section($nomorBagian . '. Pendapatan per kasir');
        $nomorBagian++;
        $rows = [];
        $totalKasirTrx = 0;
        $totalKasir = 0.0;
        foreach ($kasir as $k) {
            $totalKasirTrx += (int)$k['jumlah'];
            $totalKasir += (float)$k['total'];
            $rows[] = [$k['nama_kasir'], (string)(int)$k['jumlah'], rupiah($k['total'])];
        }
        $html .= pdf_table(
            [['Kasir', 'left', 50], ['Transaksi', 'right', 20], ['Total', 'right', 30]],
            $rows,
            'Tidak ada transaksi pada periode ini.',
            ['TOTAL', (string)$totalKasirTrx, rupiah($totalKasir)]
        );
        $html .= '</div>';
    }

    /* Catatan & peringatan HPP. */
    $html .= '<div style="page-break-inside:avoid">';
    $html .= pdf_section($nomorBagian . '. Catatan');
    if ($hppKosong) {
        $html .= '<div class="warning"><strong>HPP beberapa menu belum terisi sehingga laba kotor belum akurat.</strong> '
            . 'Menu berikut memiliki HPP Rp0 pada transaksi di periode ini:</div>';
        $rows = [];
        foreach ($hppKosong as $h) {
            $rows[] = [$h['nama'], (string)(int)$h['qty']];
        }
        $html .= pdf_table(
            [['Menu dengan HPP Rp0', 'left', 75], ['Terjual', 'right', 25]],
            $rows,
            'Tidak ada menu dengan HPP Rp0.'
        );
    } elseif ($menuTerjual) {
        $html .= '<div class="note muted">Semua menu yang terjual pada periode ini memiliki snapshot HPP di atas Rp0.</div>';
    } else {
        $html .= '<div class="note muted">Tidak ada menu terjual pada periode ini, sehingga tidak ada peringatan HPP.</div>';
    }

    $html .= '<div class="note">Angka dihitung dari transaksi berstatus lunas pada rentang tanggal tersebut. '
        . 'HPP mengikuti snapshot saat transaksi terjadi.</div>';
    $html .= '</div>';
    $html .= pdf_ttd($namaPencetak);
} elseif ($jenis === 'stok') {
    /* 1. Ringkasan stok */
    $html .= pdf_section('1. Ringkasan stok');
    $html .= pdf_kpi([
        ['Jumlah bahan', (string)$stokRingkasan['jumlah'], 'Bahan tercatat di persediaan'],
        ['Hampir habis / kritis', (string)$stokRingkasan['kritis'], 'Stok sama dengan atau di bawah minimum'],
        ['Bahan habis', (string)$stokRingkasan['habis'], 'Stok 0 atau kurang'],
        ['Nilai persediaan', rupiah($stokRingkasan['nilai']), 'Stok × harga beli per satuan'],
    ]);

    /* 2. Tabel bahan */
    $html .= pdf_section('2. Tabel bahan');
    $rows = [];
    foreach ($bahan as $b) {
        $stok = (float)$b['stok'];
        $min = (float)$b['stok_minimum'];
        if ($stok <= 0) {
            $status = 'Habis';
        } elseif ($stok <= $min) {
            $status = 'Kritis';
        } elseif ($stok <= $min * 1.5) {
            $status = 'Waspada';
        } else {
            $status = 'Aman';
        }
        $rows[] = [
            $b['nama'],
            $b['satuan'],
            pdf_qty($stok),
            pdf_qty($min),
            $status,
            rupiah($b['harga']),
            rupiah($stok * (float)$b['harga']),
        ];
    }
    $html .= pdf_table(
        [['Nama bahan', 'left', 27], ['Satuan', 'left', 10], ['Stok saat ini', 'right', 12], ['Stok minimum', 'right', 12],
         ['Status', 'left', 10], ['Harga / satuan', 'right', 14], ['Nilai', 'right', 15]],
        $rows,
        'Belum ada bahan tercatat.',
        ['TOTAL', '', '', '', '', '', rupiah($stokRingkasan['nilai'])]
    );

    /* 3. Mutasi stok */
    $html .= pdf_section('3. Mutasi stok pada periode ' . $periodeTeks);
    $html .= '<p class="muted" style="margin:2px 0 6px">Masuk: ' . $mutasiRingkasan['Masuk']
        . ' catatan &middot; Keluar: ' . $mutasiRingkasan['Keluar']
        . ' catatan &middot; Penyesuaian: ' . $mutasiRingkasan['Penyesuaian'] . ' catatan.</p>';
    $rows = [];
    foreach ($mutasi as $m) {
        $rows[] = [
            pdf_tanggal_singkat((string)$m['tanggal']),
            $m['bahan'],
            $m['kelompok'],
            $m['jumlah'] . ' ' . $m['satuan'],
            $m['keterangan'],
            $m['pengguna'],
        ];
    }
    $html .= pdf_table(
        [['Tanggal', 'left', 16], ['Bahan', 'left', 20], ['Jenis mutasi', 'left', 13],
         ['Jumlah', 'right', 14], ['Keterangan', 'left', 24], ['Petugas', 'left', 13]],
        $rows,
        'Tidak ada mutasi stok pada periode ini.'
    );

    $html .= '<div class="note">Angka stok adalah snapshot saat laporan dibuat. Mutasi mengikuti catatan pergerakan stok pada rentang tanggal tersebut.</div>';
} else {
    /* 1. Ringkasan cash flow */
    $html .= pdf_section('1. Ringkasan cash flow');
    $html .= pdf_kpi([
        ['Kas masuk', rupiah($cf['masuk']), 'Penjualan lunas + kas masuk lain'],
        ['Kas keluar', rupiah($cf['keluar']), 'Pembelian bahan dan operasional'],
        ['Saldo bersih', rupiah($cf['saldo']), 'Kas masuk − kas keluar'],
    ]);

    /* 2. Kas masuk per kategori */
    $html .= '<div style="page-break-inside:avoid">';
    $html .= pdf_section('2. Kas masuk per kategori');
    $rows = [];
    $totalMasukKategori = 0;
    $totalMasukNilai = 0.0;
    foreach ($cfMasukKategori as $k) {
        $totalMasukKategori += (int)$k['jumlah'];
        $totalMasukNilai += (float)$k['total'];
        $rows[] = [$k['kategori'], (string)(int)$k['jumlah'], rupiah($k['total'])];
    }
    $html .= pdf_table(
        [['Kategori kas masuk', 'left', 50], ['Transaksi / catatan', 'right', 20], ['Total', 'right', 30]],
        $rows,
        'Tidak ada transaksi pada periode ini.',
        ['TOTAL', (string)$totalMasukKategori, rupiah($totalMasukNilai)]
    );
    $html .= '</div>';

    /* 3. Kas keluar per kategori */
    $html .= '<div style="page-break-inside:avoid">';
    $html .= pdf_section('3. Kas keluar per kategori');
    $rows = [];
    $totalKeluarKategori = 0;
    $totalKeluarNilai = 0.0;
    foreach ($cfKeluarKategori as $k) {
        $totalKeluarKategori += (int)$k['jumlah'];
        $totalKeluarNilai += (float)$k['total'];
        $rows[] = [$k['kategori'], (string)(int)$k['jumlah'], rupiah($k['total'])];
    }
    $html .= pdf_table(
        [['Kategori kas keluar', 'left', 50], ['Catatan', 'right', 20], ['Total', 'right', 30]],
        $rows,
        'Tidak ada transaksi pada periode ini.',
        ['TOTAL', (string)$totalKeluarKategori, rupiah($totalKeluarNilai)]
    );
    $html .= '</div>';

    /* 4. Rincian per tanggal + saldo berjalan */
    $rapatkanHarian = count($cfHarian) <= 10;
    if ($rapatkanHarian) {
        $html .= '<div style="page-break-inside:avoid">';
    }
    $html .= pdf_section('4. Rincian per tanggal dan saldo berjalan');
    $rows = [];
    foreach ($cfHarian as $h) {
        $rows[] = [
            tanggal_indonesia((string)$h['tanggal'], false),
            rupiah($h['masuk']),
            rupiah($h['keluar']),
            rupiah($h['saldo']),
        ];
    }
    $html .= pdf_table(
        [['Tanggal', 'left', 40], ['Kas masuk', 'right', 20], ['Kas keluar', 'right', 20], ['Saldo berjalan', 'right', 20]],
        $rows,
        'Tidak ada transaksi pada periode ini.'
    );

    $html .= '<div class="note">Kas masuk penjualan dihitung dari transaksi berstatus lunas. '
        . 'Saldo berjalan dimulai dari Rp0 pada awal periode dan belum termasuk saldo kas sebelum periode ini. '
        . 'Entri kas kategori Penjualan yang sudah tercatat sebagai transaksi tidak dijumlahkan dua kali.</div>';
    if ($rapatkanHarian) {
        $html .= '</div>';
    }
}

$html .= '</body></html>';

/* --------------------------------------------------------------- Render PDF */
$judulPdf = $judul;
$namaFile = 'laporan-' . $jenis . '-' . $mulai . '-' . $sampai . '.pdf';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font_size' => 9,
        'default_font' => 'dejavusans',
        'displayMode' => 'fullpage',
    ]);

    $mpdf->SetTitle($judulPdf . ' · Café Setara');
    $mpdf->SetAuthor('Café Setara');
    $mpdf->SetCreator('Café Setara');
    $mpdf->SetSubject($judulPdf . ' periode ' . $periodeTeks);
    $mpdf->SetKeywords('Café Setara, laporan, ' . $jenis);
    // Metadata bahasa PDF (/Lang) dibaca mPDF dari properti ini.
    $mpdf->currentLang = 'id';
    $mpdf->default_lang = 'id';

    $mpdf->SetHTMLFooter(
        '<div style="border-top:1px solid #D9B98E;padding-top:3px;text-align:center;'
        . 'font-family:dejavusans;font-size:9pt;color:#5C3317">'
        . 'Café Setara &middot; Halaman {PAGENO} dari {nbpg}</div>'
    );

    $mpdf->WriteHTML($html);
    $pdf = $mpdf->Output('', 'S');
} catch (\Throwable $e) {
    error_log('[PDF] ' . $e->getMessage() . ' di ' . $e->getFile() . ':' . $e->getLine());
    pdf_error(
        500,
        'Gagal membuat PDF',
        'Terjadi kesalahan saat menyusun laporan. Detail sudah dicatat di log aplikasi. Silakan coba lagi.'
    );
}

if (!is_string($pdf) || strncmp($pdf, '%PDF', 4) !== 0) {
    pdf_error(500, 'PDF tidak valid', 'Generator tidak menghasilkan berkas PDF yang valid.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

session_write_close();
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . $namaFile . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');
echo $pdf;
exit;
