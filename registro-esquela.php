<?php

declare(strict_types=1);

/**
 * Generador local de esquela para Registro de Servicios.
 *
 * Responsabilidades:
 * - Seleccionar fondo por edad/sexo.
 * - Componer fotografia circular, texto, logo y QR.
 * - No envia correos, no modifica SharePoint y no reemplaza TellMeBye.
 */

function rs_esquela_norm(string $value): string
{
    $value = trim($value);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }
    $value = mb_strtolower($value, 'UTF-8');
    return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
}

function rs_esquela_asset_candidates(string $baseName): array
{
    return [
        __DIR__ . '/assets/esquelas/' . $baseName . '.jpg',
        __DIR__ . '/assets/esquelas/' . $baseName . '.jpeg',
        __DIR__ . '/assets/esquelas/' . $baseName . '.png',
        __DIR__ . '/assets/esquelas/' . $baseName . '.b64',
    ];
}

function rs_esquela_image_from_bytes(string $bytes): ?GdImage
{
    if ($bytes === '' || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $image = @imagecreatefromstring($bytes);
    return $image instanceof GdImage ? $image : null;
}

function rs_esquela_load_asset(string $baseName): ?GdImage
{
    foreach (rs_esquela_asset_candidates($baseName) as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        if (str_ends_with(strtolower($path), '.b64')) {
            $raw = @file_get_contents($path);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $decoded = base64_decode(preg_replace('/\s+/', '', $raw) ?? '', true);
            if (!is_string($decoded) || $decoded === '') {
                continue;
            }
            $image = rs_esquela_image_from_bytes($decoded);
            if ($image instanceof GdImage) {
                return $image;
            }
            continue;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $image = rs_esquela_image_from_bytes($raw);
        if ($image instanceof GdImage) {
            return $image;
        }
    }

    return null;
}

function rs_esquela_background_key(array $payload): string
{
    $age = max(0, (int)($payload['edad'] ?? 0));
    if ($age < 20) {
        return 'fondo_menor';
    }

    $sex = rs_esquela_norm((string)($payload['sexo'] ?? ''));
    if (str_contains($sex, 'femen') || $sex === 'f' || str_contains($sex, 'mujer')) {
        return 'fondo_femenino';
    }

    return 'fondo_masculino';
}

function rs_esquela_prefix(array $payload): string
{
    $age = max(0, (int)($payload['edad'] ?? 0));
    $sex = rs_esquela_norm((string)($payload['sexo'] ?? ''));
    $female = str_contains($sex, 'femen') || $sex === 'f' || str_contains($sex, 'mujer');

    if ($age <= 5) {
        return $female ? 'Niña' : 'Niño';
    }
    if ($age < 20) {
        return $female ? 'La Joven' : 'El Joven';
    }
    return $female ? 'Sra.' : 'Sr.';
}

function rs_esquela_location_name(string $ubicacion): string
{
    $norm = rs_esquela_norm($ubicacion);
    if (str_contains($norm, 'apodaca') || str_contains($norm, 'agua fria') || str_contains($norm, 'aguafria')) {
        return 'Capillas Agua Fría';
    }
    if (str_contains($norm, 'churubusco')) {
        return 'Capillas Churubusco';
    }
    return trim($ubicacion) !== '' ? trim($ubicacion) : 'Capillas Jardines de Juan Pablo';
}

function rs_esquela_location_address(string $ubicacion): string
{
    $norm = rs_esquela_norm($ubicacion);
    if (str_contains($norm, 'apodaca') || str_contains($norm, 'agua fria') || str_contains($norm, 'aguafria')) {
        return 'Antigua carretera a Zuazua, Antigua Carretera a Aguafria S/N, 66620 Cdad. Apodaca, N.L.';
    }
    if (str_contains($norm, 'churubusco')) {
        return 'Av. Churubusco 217 Nte, Churubusco, 64590 Monterrey, N.L.';
    }
    return '';
}

function rs_esquela_parse_datetime(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $tz = new DateTimeZone('America/Monterrey');
    foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    try {
        return new DateTimeImmutable($value, $tz);
    } catch (Throwable) {
        return null;
    }
}

function rs_esquela_spanish_datetime(string $value): string
{
    $dt = rs_esquela_parse_datetime($value);
    if (!$dt instanceof DateTimeImmutable) {
        return trim($value);
    }

    $days = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
    $months = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    return sprintf(
        '%s %d de %s a las %s h.',
        $days[(int)$dt->format('N')] ?? '',
        (int)$dt->format('j'),
        $months[(int)$dt->format('n')] ?? '',
        $dt->format('H:i')
    );
}

function rs_esquela_qr_url(string $fullName): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $fullName) ?? $fullName);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if (is_string($ascii) && $ascii !== '') {
        $name = $ascii;
    }
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z0-9 ]+/', '', $name) ?? $name;
    $slug = preg_replace('/\s+/', '-', trim($name)) ?? trim($name);
    return 'https://tellmebye.com/' . rawurlencode($slug) . '&accessbycode=1';
}

function rs_esquela_font(bool $bold = false): ?string
{
    if ($bold && function_exists('rs_image_bold_font_path')) {
        $font = rs_image_bold_font_path();
        if (is_string($font) && $font !== '') {
            return $font;
        }
    }
    if (function_exists('rs_image_font_path')) {
        $font = rs_image_font_path();
        if (is_string($font) && $font !== '') {
            return $font;
        }
    }

    foreach ($bold
        ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf']
        : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf']
        as $candidate
    ) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function rs_esquela_measure(string $text, ?string $font, float $size): int
{
    if ($font !== null && function_exists('imagettfbbox')) {
        $box = @imagettfbbox($size, 0, $font, $text);
        if (is_array($box)) {
            return abs((int)$box[2] - (int)$box[0]);
        }
    }
    return strlen($text) * imagefontwidth(5);
}

function rs_esquela_wrap(string $text, int $maxWidth, ?string $font, float $size): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return [];
    }

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && rs_esquela_measure($candidate, $font, $size) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function rs_esquela_center_text(
    GdImage $image,
    string $text,
    int $y,
    int $maxWidth,
    int $color,
    ?string $font,
    float $size,
    int $lineHeight,
    bool $bold = false
): int {
    $useFont = $bold ? (rs_esquela_font(true) ?? $font) : $font;
    $lines = rs_esquela_wrap($text, $maxWidth, $useFont, $size);

    foreach ($lines as $line) {
        $width = rs_esquela_measure($line, $useFont, $size);
        $x = max(10, (int)round((imagesx($image) - $width) / 2));

        if ($useFont !== null && function_exists('imagettftext')) {
            @imagettftext($image, $size, 0, $x, $y, $color, $useFont, $line);
        } else {
            imagestring($image, 5, $x, max(0, $y - imagefontheight(5)), $line, $color);
        }
        $y += $lineHeight;
    }

    return $y;
}

function rs_esquela_copy_cover(GdImage $dest, GdImage $src): void
{
    $dw = imagesx($dest);
    $dh = imagesy($dest);
    $sw = imagesx($src);
    $sh = imagesy($src);

    $scale = max($dw / max(1, $sw), $dh / max(1, $sh));
    $cropW = (int)round($dw / $scale);
    $cropH = (int)round($dh / $scale);
    $sx = max(0, (int)round(($sw - $cropW) / 2));
    $sy = max(0, (int)round(($sh - $cropH) / 2));

    imagecopyresampled($dest, $src, 0, 0, $sx, $sy, $dw, $dh, $cropW, $cropH);
}

function rs_esquela_fallback_background(string $key, int $width, int $height): GdImage
{
    $img = imagecreatetruecolor($width, $height);
    if (!$img instanceof GdImage) {
        throw new RuntimeException('No fue posible crear el fondo de esquela.');
    }

    if ($key === 'fondo_femenino') {
        $top = [255, 246, 232];
        $bottom = [239, 219, 196];
    } elseif ($key === 'fondo_menor') {
        $top = [224, 240, 247];
        $bottom = [255, 243, 214];
    } else {
        $top = [231, 226, 195];
        $bottom = [189, 196, 159];
    }

    for ($y = 0; $y < $height; $y++) {
        $t = $height <= 1 ? 0 : $y / ($height - 1);
        $r = (int)round($top[0] + ($bottom[0] - $top[0]) * $t);
        $g = (int)round($top[1] + ($bottom[1] - $top[1]) * $t);
        $b = (int)round($top[2] + ($bottom[2] - $top[2]) * $t);
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width, $y, $c);
    }

    return $img;
}

function rs_esquela_draw_circular_photo(GdImage $canvas, ?GdImage $photo, int $cx, int $cy, int $diameter): void
{
    if (!$photo instanceof GdImage) {
        return;
    }

    $sw = imagesx($photo);
    $sh = imagesy($photo);
    $side = min($sw, $sh);
    $sx = max(0, (int)round(($sw - $side) / 2));
    $sy = max(0, (int)round(($sh - $side) / 2));

    $tmp = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    imagefill($tmp, 0, 0, $transparent);
    imagecopyresampled($tmp, $photo, 0, 0, $sx, $sy, $diameter, $diameter, $side, $side);

    $radius = $diameter / 2;
    $x0 = $cx - (int)$radius;
    $y0 = $cy - (int)$radius;

    for ($y = 0; $y < $diameter; $y++) {
        for ($x = 0; $x < $diameter; $x++) {
            $dx = $x - $radius + 0.5;
            $dy = $y - $radius + 0.5;
            if (($dx * $dx + $dy * $dy) <= ($radius * $radius)) {
                $color = imagecolorat($tmp, $x, $y);
                imagesetpixel($canvas, $x0 + $x, $y0 + $y, $color);
            }
        }
    }

    $border = imagecolorallocate($canvas, 190, 143, 54);
    imageellipse($canvas, $cx, $cy, $diameter + 4, $diameter + 4, $border);
    imagedestroy($tmp);
}

function rs_esquela_draw_ribbon(GdImage $image, int $cx, int $cy): void
{
    $gray = imagecolorallocate($image, 70, 70, 70);
    imagesetthickness($image, 8);
    imageline($image, $cx - 15, $cy - 20, $cx + 15, $cy + 22, $gray);
    imageline($image, $cx + 15, $cy - 20, $cx - 15, $cy + 22, $gray);
    imagearc($image, $cx, $cy - 11, 30, 34, 180, 360, $gray);
    imagesetthickness($image, 1);
}

function rs_esquela_logo_path(): ?string
{
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    foreach ([
        $root . '/mapa/assets/logo.jpg',
        $root . '/mapa/assets/logo.png',
        __DIR__ . '/assets/logo.jpg',
        __DIR__ . '/assets/logo.png',
    ] as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function rs_esquela_draw_logo(GdImage $canvas, int $x, int $y, int $maxW, int $maxH): void
{
    $path = rs_esquela_logo_path();
    if ($path === null) {
        return;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return;
    }
    $logo = rs_esquela_image_from_bytes($raw);
    if (!$logo instanceof GdImage) {
        return;
    }

    $sw = imagesx($logo);
    $sh = imagesy($logo);
    $scale = min($maxW / max(1, $sw), $maxH / max(1, $sh));
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));
    imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($logo);
}

function rs_esquela_fetch_qr(string $url): ?GdImage
{
    $endpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($url);

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "User-Agent: JDJP-Registro-Servicios\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $bytes = @file_get_contents($endpoint, false, $context);
    if (!is_string($bytes) || $bytes === '') {
        return null;
    }
    return rs_esquela_image_from_bytes($bytes);
}

function rs_esquela_draw_qr(GdImage $canvas, string $url, int $x, int $y, int $size): bool
{
    $qr = rs_esquela_fetch_qr($url);
    if ($qr instanceof GdImage) {
        imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
        imagedestroy($qr);
        return true;
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 35, 35, 35);
    imagefilledrectangle($canvas, $x, $y, $x + $size, $y + $size, $white);
    imagerectangle($canvas, $x, $y, $x + $size, $y + $size, $black);
    imagestring($canvas, 5, $x + (int)($size / 2) - 12, $y + (int)($size / 2) - 8, 'QR', $black);
    return false;
}

function rs_generate_local_esquela(array $payload, ?string $photoBytes = null): array
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('El servidor no tiene GD disponible para generar la esquela.');
    }

    $width = 558;
    $height = 788;
    $backgroundKey = rs_esquela_background_key($payload);

    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas instanceof GdImage) {
        throw new RuntimeException('No fue posible crear el lienzo de esquela.');
    }

    $background = rs_esquela_load_asset($backgroundKey);
    if (!$background instanceof GdImage) {
        $background = rs_esquela_fallback_background($backgroundKey, $width, $height);
    }

    rs_esquela_copy_cover($canvas, $background);
    imagedestroy($background);

    $overlay = imagecolorallocatealpha($canvas, 255, 255, 255, 52);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $overlay);

    $photo = rs_esquela_image_from_bytes((string)$photoBytes);
    if (!$photo instanceof GdImage) {
        $photo = rs_esquela_load_asset('foto_fallback');
    }

    rs_esquela_draw_circular_photo($canvas, $photo, (int)($width / 2), 112, 170);
    if ($photo instanceof GdImage) {
        imagedestroy($photo);
    }

    $black = imagecolorallocate($canvas, 45, 45, 45);
    $brown = imagecolorallocate($canvas, 90, 48, 34);
    $font = rs_esquela_font(false);
    $bold = rs_esquela_font(true) ?? $font;

    $name = trim((string)($payload['fallecido'] ?? ''));
    $age = max(0, (int)($payload['edad'] ?? 0));
    $prefix = rs_esquela_prefix($payload);
    $ubicacion = rs_esquela_location_name((string)($payload['ubicacion'] ?? ''));
    $sala = trim((string)($payload['sala'] ?? ''));
    $inicio = rs_esquela_spanish_datetime((string)($payload['inicio'] ?? ''));
    $termino = rs_esquela_spanish_datetime((string)($payload['termino'] ?? ''));
    $exequia = rs_esquela_spanish_datetime((string)($payload['horaExequia'] ?? ''));
    $inhumacion = rs_esquela_spanish_datetime((string)($payload['fechaHoraInhumacion'] ?? ''));
    $destino = trim((string)($payload['destinoFinal'] ?? ''));
    $address = rs_esquela_location_address((string)($payload['ubicacion'] ?? ''));

    $y = 226;
    $y = rs_esquela_center_text($canvas, 'Informamos el sensible fallecimiento', $y, 500, $black, $font, 15, 19);
    $y = rs_esquela_center_text($canvas, 'de ' . $prefix . ' ' . $name, $y, 500, $black, $bold, 16, 20, true);
    $y = rs_esquela_center_text($canvas, 'a la edad de ' . $age . ' años.', $y, 500, $black, $font, 15, 19);

    rs_esquela_draw_ribbon($canvas, (int)($width / 2), $y + 29);
    $y += 73;

    $serviceText = 'El homenaje de vida se llevará a cabo en ' . $ubicacion
        . ($sala !== '' ? ' ' . $sala : '')
        . ($inicio !== '' ? ', el ' . $inicio : '')
        . ($termino !== '' ? ', para concluir el ' . $termino : '') . '.';

    if ((bool)($payload['llevaExequia'] ?? false) && $exequia !== '') {
        $serviceText .= ' Se llevará a cabo una ceremonia de cuerpo presente el ' . $exequia . '.';
    }

    $y = rs_esquela_center_text($canvas, $serviceText, $y, 500, $black, $font, 14, 19);

    $serviceNorm = rs_esquela_norm((string)($payload['servicio'] ?? ''));
    if (str_contains($serviceNorm, 'inhumacion') && $inhumacion !== '') {
        $inhText = 'Se realizará la inhumación el ' . $inhumacion;
        if ($destino !== '') {
            $inhText .= ' en ' . $destino;
        }
        $inhText .= '.';
        $y += 10;
        $y = rs_esquela_center_text($canvas, $inhText, $y, 500, $black, $font, 14, 19);
    }

    $y = max($y + 28, 520);
    rs_esquela_center_text(
        $canvas,
        'Ayúdanos a mantener viva su memoria, dejando recuerdos y condolencias en su homenaje de vida, usando este QR.',
        $y,
        410,
        $black,
        $font,
        14,
        19
    );

    rs_esquela_draw_logo($canvas, 12, 638, 105, 105);

    $brandY = 675;
    rs_esquela_center_text($canvas, 'Jardines de Juan Pablo', $brandY, 300, $brown, $font, 14, 18);
    if ($address !== '') {
        rs_esquela_center_text($canvas, $address, $brandY + 24, 320, $brown, $font, 12, 16);
    }

    $qrUrl = rs_esquela_qr_url($name);
    $qrOk = rs_esquela_draw_qr($canvas, $qrUrl, 456, 650, 88);

    ob_start();
    imagejpeg($canvas, null, 92);
    $jpeg = (string)ob_get_clean();
    imagedestroy($canvas);

    if ($jpeg === '') {
        throw new RuntimeException('No fue posible codificar la esquela local.');
    }

    $id = preg_replace('/[^0-9A-Za-z_-]+/', '', trim((string)($payload['itemId'] ?? $payload['numeroServicio'] ?? 'PRUEBA')));
    if ($id === '') {
        $id = 'PRUEBA';
    }

    return [
        'fileName' => 'Esquela_' . $id . '.jpg',
        'jpeg' => $jpeg,
        'qrUrl' => $qrUrl,
        'qrGenerated' => $qrOk,
        'background' => $backgroundKey,
        'prefix' => $prefix,
    ];
}
