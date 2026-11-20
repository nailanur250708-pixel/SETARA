<?php
/**
 * upload_menu_foto.php — Validasi + resize foto menu (GD/WebP) dalam folder uploads/menu/.
 *
 * Fitur:
 * - cek error upload, ukuran ≤ 5 MB, ekstensi DAN MIME asli (finfo + getimagesize)
 * - orientasi EXIF ditolak (rotasi/flip via GD)
 * - resize sisi terpanjang ≤1200px, thumbnail ≤400px
 * - format WebP (atau JPEG kalau WebP tidak tersedia), kualitas ~82
 * - nama acak: uploads/menu/menu_{id}_{timestamp}_{random}.webp
 * - Return: path relatif ('uploads/menu/...') atau null kalau gagal.
 */

function ini_bytes(string $v): float
{
    $v = trim($v);
    $n = (float) $v;
    $u = strtoupper(preg_replace('/[0-9\s.]/u', '', $v));
    switch ($u) {
        case 'K':
        case 'KB':
            return $n * 1024;
        case 'M':
        case 'MB':
            return $n * 1024 * 1024;
        case 'G':
        case 'GB':
            return $n * 1024 * 1024 * 1024;
        default:
            return $n;
    }
}

function menu_thumb_path(string $path): string
{
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $sfx = '.' . $ext;
        if (str_ends_with($path, $sfx)) {
            return substr($path, 0, -strlen($sfx)) . '_thumb' . $sfx;
        }
    }
    return $path;
}

function menu_delete_photo_files(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('SELECT foto_path, gambar FROM menu WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    foreach (['foto_path', 'gambar'] as $k) {
        $p = $row[$k] ?? '';
        if ($p === '') {
            continue;
        }
        if (!str_starts_with($p, 'uploads/') && !str_starts_with($p, 'assets/')) {
            continue;
        }
        foreach ([$p, menu_thumb_path($p)] as $fp) {
            $f = dirname(__DIR__) . '/' . $fp;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
}

function process_menu_upload(int $menuId, array $file): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return null;
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        return null;
    }
    $umf = ini_get('upload_max_filesize');
    if ($umf !== false && ini_bytes($umf) > 0 && (int) $file['size'] > ini_bytes($umf)) {
        return null;
    }
    // MIME asli + dimensi gambar (finfo + getimagesize).
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
    } catch (Exception $e) {
        return null;
    }
    $info = getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    $image = imagecreatefromstring(file_get_contents($file['tmp_name']));
    if ($image === false) {
        return null;
    }
    // Orientasi EXIF (foto dari HP) → putar/flip agar tegak.
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, 90, 0); break;
            case 6: $image = imagerotate($image, 270, 0); break;
            case 7: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, 270, 0); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }
    }
    // Resize: sisi terpanjang ≤1200 (main) + thumbnail ≤400, WebP (atau JPEG), kualitas ~82.
    $save = function ($src, int $maxSide, string $absPath): bool {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = max($w, $h) > $maxSide ? $maxSide / max($w, $h) : 1.0;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = function_exists('imagewebp') ? @imagewebp($out, $absPath, 82) : @imagejpeg($out, $absPath, 82);
        imagedestroy($out);
        return (bool) ($ok && is_file($absPath) && filesize($absPath) > 0);
    };
    $dir = dirname(__DIR__) . '/uploads/menu';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        imagedestroy($image);
        return null;
    }
    $base = 'menu_' . $menuId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6));
    $ext  = function_exists('imagewebp') ? 'webp' : 'jpg';
    $main = $dir . '/' . $base . '.' . $ext;
    if (!$save($image, 1200, $main)) {
        @unlink($main);
        imagedestroy($image);
        return null;
    }
    $save($image, 400, $dir . '/' . $base . '_thumb.' . $ext);
    imagedestroy($image);
    return 'uploads/menu/' . basename($main);
}
