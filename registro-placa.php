<?php
declare(strict_types=1);

require_once __DIR__ . '/registro-imagenes.php';
require_once __DIR__ . '/registro-placa-template.php';

function rs_plate_escape_pdf_text(string $value): string
{
    return str_replace(
        ['\\', '(', ')', "\r", "\n"],
        ['\\\\', '\\(', '\\)', ' ', ' '],
        $value
    );
}

function rs_plate_wrap_words(string $text, string $font, float $size, int $maxWidth): array
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    if ($text === '') {
        return [''];
    }

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        $width = is_array($box) ? abs((int)$box[2] - (int)$box[0]) : 0;

        if ($current !== '' && $width > $maxWidth) {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

function rs_plate_draw_centered_text(
    GdImage $image,
    string $font,
    float $size,
    int $y,
    string $text,
    int $color,
    int $canvasWidth
): void {
    $box = imagettfbbox($size, 0, $font, $text);
    $width = is_array($box) ? abs((int)$box[2] - (int)$box[0]) : 0;
    $x = max(20, (int)(($canvasWidth - $width) / 2));
    imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
}

function rs_plate_draw_star(GdImage $image, int $cx, int $cy, int $outer, int $inner, int $color): void
{
    $points = [];
    for ($i = 0; $i < 10; $i++) {
        $angle = deg2rad(-90 + ($i * 36));
        $radius = ($i % 2 === 0) ? $outer : $inner;
        $points[] = (int)round($cx + cos($angle) * $radius);
        $points[] = (int)round($cy + sin($angle) * $radius);
    }
    imagefilledpolygon($image, $points, 10, $color);
}

function rs_plate_draw_cross(GdImage $image, int $cx, int $cy, int $size, int $thickness, int $color): void
{
    $half = (int)floor($size / 2);
    $t = (int)floor($thickness / 2);
    imagefilledrectangle($image, $cx - $t, $cy - $half, $cx + $t, $cy + $half, $color);
    imagefilledrectangle($image, $cx - $half, $cy - $t, $cx + $half, $cy + $t, $color);
}

function rs_plate_logo_path(): ?string
{
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $candidates = [
        $root . '/mapa/assets/logo.jpg',
        $root . '/mapa/assets/logo.png',
        __DIR__ . '/assets/logo.jpg',
        __DIR__ . '/assets/logo.png',
    ];

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function rs_plate_draw_logo(GdImage $canvas, int $x, int $y, int $maxW, int $maxH): void
{
    $path = rs_plate_logo_path();
    if ($path === null) {
        return;
    }

    $info = @getimagesize($path);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        return;
    }

    $src = null;
    $mime = strtolower((string)($info['mime'] ?? ''));
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($path);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($path);
    }
    if (!$src instanceof GdImage) {
        return;
    }

    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min($maxW / max(1, $sw), $maxH / max(1, $sh));
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));

    $tmp = imagecreatetruecolor($dw, $dh);
    $white = imagecolorallocate($tmp, 255, 255, 255);
    imagefill($tmp, 0, 0, $white);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

    if (function_exists('imagefilter')) {
        @imagefilter($tmp, IMG_FILTER_GRAYSCALE);
        @imagefilter($tmp, IMG_FILTER_CONTRAST, -100);
    }

    imagecopy($canvas, $tmp, $x, $y, 0, 0, $dw, $dh);
    imagedestroy($tmp);
    imagedestroy($src);
}

function rs_plate_png_to_pdf(string $pngBytes, int $pixelWidth, int $pixelHeight): string
{
    $src = @imagecreatefromstring($pngBytes);
    if (!$src instanceof GdImage) {
        throw new RuntimeException('No fue posible convertir la placa PNG a PDF.');
    }

    ob_start();
    imagejpeg($src, null, 95);
    $jpeg = (string)ob_get_clean();
    imagedestroy($src);

    if ($jpeg === '') {
        throw new RuntimeException('No fue posible generar el JPEG temporal de la placa.');
    }

    $pageW = 544.8;
    $pageH = 271.2;
    $objects = [];

    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] /Resources << /XObject << /Im0 5 0 R >> >> /Contents 4 0 R >>";

    $content = "q\n{$pageW} 0 0 {$pageH} 0 0 cm\n/Im0 Do\nQ\n";
    $objects[4] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

    $objects[5] = "<< /Type /XObject /Subtype /Image /Width {$pixelWidth} /Height {$pixelHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0 => 0];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xref}\n%%EOF";

    return $pdf;
}

function rs_generate_urna_plate(array $payload): array
{
    rs_image_require_gd();

    $font = rs_image_font_path();
    $bold = rs_image_bold_font_path() ?? $font;
    if ($font === null || $bold === null) {
        throw new RuntimeException('No hay una fuente TrueType disponible para generar la placa.');
    }

    $name = trim((string)($payload['fallecido'] ?? ''));
    $birthRaw = trim((string)($payload['fechaNacimiento'] ?? ''));
    $deathRaw = trim((string)($payload['fechaDefuncion'] ?? ''));

    if ($name === '' || $birthRaw === '' || $deathRaw === '') {
        throw new RuntimeException('La placa requiere fallecido, fecha de nacimiento y fecha de defuncion.');
    }

    $tz = new DateTimeZone('America/Monterrey');
    $birth = null;
    foreach (['Y-m-d', 'd/m/Y'] as $fmt) {
        $candidate = DateTimeImmutable::createFromFormat($fmt, substr($birthRaw, 0, 10), $tz);
        if ($candidate instanceof DateTimeImmutable) {
            $birth = $candidate;
            break;
        }
    }

    $death = null;
    $normalizedDeath = str_replace('Z', '', $deathRaw);
    foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y'] as $fmt) {
        $candidate = DateTimeImmutable::createFromFormat($fmt, $normalizedDeath, $tz);
        if ($candidate instanceof DateTimeImmutable) {
            $death = $candidate;
            break;
        }
    }

    if (!$birth instanceof DateTimeImmutable || !$death instanceof DateTimeImmutable) {
        throw new RuntimeException('No fue posible interpretar las fechas para la placa.');
    }

    $w = 1514;
    $h = 754;
    $img = imagecreatetruecolor($w, $h);
    imageantialias($img, true);

    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);

    $displayName = mb_strtoupper($name, 'UTF-8');
    $fontSize = 62.0;
    $maxNameWidth = 930;
    $lines = rs_plate_wrap_words($displayName, $font, $fontSize, $maxNameWidth);
    while (count($lines) > 3 && $fontSize > 44) {
        $fontSize -= 2;
        $lines = rs_plate_wrap_words($displayName, $font, $fontSize, $maxNameWidth);
    }

    $lineHeight = (int)round($fontSize * 1.17);
    $startY = 150;
    if (count($lines) === 1) $startY = 215;
    elseif (count($lines) === 2) $startY = 175;

    foreach ($lines as $idx => $line) {
        rs_plate_draw_centered_text($img, $font, $fontSize, $startY + ($idx * $lineHeight), $line, $black, $w);
    }

    $dateY = 505;
    rs_plate_draw_star($img, 138, 478, 30, 13, $black);
    imagettftext($img, 45, 0, 205, $dateY, $black, $bold, $birth->format('d/m/Y'));

    rs_plate_draw_cross($img, 755, 478, 65, 14, $black);
    imagettftext($img, 45, 0, 815, $dateY, $black, $bold, $death->format('d/m/Y'));

    rs_plate_draw_logo($img, 20, 575, 165, 150);

    $phrase = 'Siempre en nuestro corazón';
    $phraseSize = 34.0;
    $phraseBox = imagettfbbox($phraseSize, 0, $font, $phrase);
    $phraseWidth = is_array($phraseBox) ? abs((int)$phraseBox[2] - (int)$phraseBox[0]) : 0;
    $phraseX = max(235, (int)(($w - $phraseWidth) / 2) + 55);
    imagettftext($img, $phraseSize, 0, $phraseX, 700, $black, $font, $phrase);

    ob_start();
    imagepng($img, null, 6);
    $png = (string)ob_get_clean();
    imagedestroy($img);

    if ($png === '') {
        throw new RuntimeException('No fue posible generar la imagen de la placa.');
    }

    $pdf = rs_plate_fill_original_template($displayName, $birth->format('d/m/Y'), $death->format('d/m/Y'));
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', rs_image_ascii($displayName)) ?: 'FALLECIDO';

    return [
        'pngName' => 'Placa_Urna_' . $safe . '.png',
        'pdfName' => 'Placa_Urna_' . $safe . '.pdf',
        'png' => $png,
        'pdf' => $pdf,
    ];
}
