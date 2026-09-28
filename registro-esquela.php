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
    $dir = __DIR__ . '/assets/esquelas';

    // 1) Archivos binarios directos, si existen.
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $path = $dir . '/' . $baseName . '.' . $ext;
        if (!is_file($path) || !is_readable($path)) {
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

    // 2) Assets base64 divididos en partes. Se usa este formato para que los
    // fondos y elementos graficos puedan viajar por el deploy sin binarios.
    $parts = glob($dir . '/' . $baseName . '.b64.part*') ?: [];
    if ($parts !== []) {
        natsort($parts);
        $encoded = '';
        foreach ($parts as $part) {
            $piece = @file_get_contents($part);
            if (!is_string($piece)) {
                $encoded = '';
                break;
            }
            $encoded .= preg_replace('/\s+/', '', $piece) ?? '';
        }
        if ($encoded !== '') {
            $decoded = base64_decode($encoded, true);
            if (is_string($decoded) && $decoded !== '') {
                $image = rs_esquela_image_from_bytes($decoded);
                if ($image instanceof GdImage) {
                    return $image;
                }
            }
        }
    }

    // 3) Compatibilidad con el asset base64 de una sola pieza.
    $b64 = $dir . '/' . $baseName . '.b64';
    if (is_file($b64) && is_readable($b64)) {
        $raw = @file_get_contents($b64);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $raw) ?? '', true);
            if (is_string($decoded) && $decoded !== '') {
                $image = rs_esquela_image_from_bytes($decoded);
                if ($image instanceof GdImage) {
                    return $image;
                }
            }
        }
    }

    return null;
}

function rs_esquela_asset_image(array $assets, string $key): ?GdImage
{
    // Las plantillas oficiales de esquela se cargan desde SharePoint.
    // No reutilizar fondos locales antiguos si la descarga falla.
    $bytes = $assets[$key] ?? null;
    if (is_string($bytes) && $bytes !== '') {
        $image = rs_esquela_image_from_bytes($bytes);
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

    // Sin punto final. Cada frase agrega su propia puntuacion para evitar "h.."
    // o "h.," cuando una fecha aparece en medio de una oracion.
    return sprintf(
        '%s %d de %s a las %s h',
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


function rs_esquela_draw_ribbon(GdImage $image, int $cx, int $cy, int $size = 78, ?string $assetBytes = null): void
{
    $asset = rs_esquela_image_from_bytes((string)$assetBytes);
    if ($asset instanceof GdImage) {
        $x = (int)round($cx - ($size / 2));
        $y = (int)round($cy - ($size / 2));
        imagealphablending($image, true);
        imagecopyresampled(
            $image,
            $asset,
            $x,
            $y,
            0,
            0,
            $size,
            $size,
            imagesx($asset),
            imagesy($asset)
        );
        imagedestroy($asset);
        return;
    }

    // Respaldo vectorial de mejor calidad que el dibujo anterior.
    $dark = imagecolorallocate($image, 67, 67, 70);
    $light = imagecolorallocate($image, 84, 84, 88);
    $w = max(8, (int)round($size * 0.16));
    imagesetthickness($image, $w);
    imageline($image, $cx - (int)($size * 0.17), $cy - (int)($size * 0.25), $cx + (int)($size * 0.27), $cy + (int)($size * 0.34), $dark);
    imageline($image, $cx + (int)($size * 0.17), $cy - (int)($size * 0.25), $cx - (int)($size * 0.27), $cy + (int)($size * 0.34), $light);
    imagearc($image, $cx, $cy - (int)($size * 0.18), (int)($size * 0.50), (int)($size * 0.48), 180, 360, $dark);
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


function rs_esquela_draw_logo(GdImage $canvas, int $cx, int $cy, int $diameter): void
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

    $tmp = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
    imagefill($tmp, 0, 0, $transparent);

    $white = imagecolorallocate($tmp, 255, 255, 255);
    imagefilledellipse($tmp, (int)($diameter / 2), (int)($diameter / 2), $diameter - 2, $diameter - 2, $white);

    // Ajustar el logo completo dentro del circulo, sin recortarlo.
    $sw = imagesx($logo);
    $sh = imagesy($logo);
    $inner = (int)round($diameter * 0.82);
    $scale = min($inner / max(1, $sw), $inner / max(1, $sh));
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));
    $dx = (int)round(($diameter - $dw) / 2);
    $dy = (int)round(($diameter - $dh) / 2);

    imagealphablending($tmp, true);
    imagecopyresampled($tmp, $logo, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

    $x0 = $cx - (int)round($diameter / 2);
    $y0 = $cy - (int)round($diameter / 2);
    imagealphablending($canvas, true);
    imagecopy($canvas, $tmp, $x0, $y0, 0, 0, $diameter, $diameter);

    $border = imagecolorallocate($canvas, 235, 224, 196);
    imagesetthickness($canvas, max(1, (int)round($diameter * 0.015)));
    imageellipse($canvas, $cx, $cy, $diameter - 2, $diameter - 2, $border);
    imagesetthickness($canvas, 1);

    imagedestroy($logo);
    imagedestroy($tmp);
}


function rs_esquela_fetch_qr(string $url): ?GdImage
{
    // Respaldo unicamente. La ruta principal genera el QR en el navegador y lo
    // envia junto con el registro para evitar depender de salidas HTTP del servidor.
    $endpoint = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=2&data=' . rawurlencode($url);

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
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

function rs_esquela_draw_qr(
    GdImage $canvas,
    string $url,
    int $x,
    int $y,
    int $size,
    ?string $qrBytes = null
): bool {
    $qr = rs_esquela_image_from_bytes((string)$qrBytes);
    if (!$qr instanceof GdImage) {
        $qr = rs_esquela_fetch_qr($url);
    }
    if (!$qr instanceof GdImage) {
        return false;
    }

    $pad = max(8, (int)round($size * 0.07));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, $x - $pad, $y - $pad, $x + $size + $pad, $y + $size + $pad, $white);
    imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
    imagedestroy($qr);
    return true;
}


function rs_generate_local_esquela(array $payload, ?string $photoBytes = null, ?string $qrBytes = null, array $assets = []): array
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('El servidor no tiene GD disponible para generar la esquela.');
    }

    // Formato vertical basado en la cartulina de referencia: 720 x 1024.
    // Se renderiza 1.5x para conservar texto y elementos graficos nitidos.
    $scale = 1.5;
    $baseWidth = 720;
    $baseHeight = 1024;
    $width = (int)round($baseWidth * $scale);
    $height = (int)round($baseHeight * $scale);
    $S = static fn(float|int $v): int => (int)round($v * $scale);
    $FS = static fn(float|int $v): float => (float)$v * $scale;

    $backgroundKey = rs_esquela_background_key($payload);

    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas instanceof GdImage) {
        throw new RuntimeException('No fue posible crear el lienzo de esquela.');
    }

    $background = rs_esquela_asset_image($assets, $backgroundKey);
    if (!$background instanceof GdImage) {
        $background = rs_esquela_fallback_background($backgroundKey, $width, $height);
    }

    rs_esquela_copy_cover($canvas, $background);
    imagedestroy($background);

    // Los fondos ya vienen suavizados para lectura; no aplicar una capa blanca
    // adicional porque elimina el detalle del bosque/flor/cielo.
    imagealphablending($canvas, true);

    $photo = rs_esquela_image_from_bytes((string)$photoBytes);
    if (!$photo instanceof GdImage) {
        $photo = rs_esquela_asset_image($assets, 'foto_fallback');
    }

    rs_esquela_draw_circular_photo($canvas, $photo, $S(360), $S(150), $S(238));
    if ($photo instanceof GdImage) {
        imagedestroy($photo);
    }

    $black = imagecolorallocate($canvas, 52, 52, 55);
    $brown = imagecolorallocate($canvas, 102, 57, 44);
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

    $y = $S(300);
    $y = rs_esquela_center_text($canvas, 'Informamos el sensible fallecimiento', $y, $S(640), $black, $font, $FS(21), $S(27));
    $y = rs_esquela_center_text($canvas, 'de ' . $prefix . ' ' . $name, $y, $S(640), $black, $bold, $FS(22), $S(28), true);
    $y = rs_esquela_center_text($canvas, 'a la edad de ' . $age . ' años.', $y, $S(640), $black, $font, $FS(21), $S(27));

    rs_esquela_draw_ribbon($canvas, $S(360), $y + $S(39), $S(80), is_string($assets['crespon'] ?? null) ? $assets['crespon'] : null);
    $y += $S(105);

    $serviceText = 'El homenaje de vida se llevará a cabo en ' . $ubicacion
        . ($sala !== '' ? ' ' . $sala : '')
        . ($inicio !== '' ? ', el ' . $inicio : '')
        . ($termino !== '' ? ', para concluir el ' . $termino : '') . '.';

    if ((bool)($payload['llevaExequia'] ?? false) && $exequia !== '') {
        $serviceText .= ' Se llevará a cabo una ceremonia de cuerpo presente el ' . $exequia . '.';
    }

    $y = rs_esquela_center_text($canvas, $serviceText, $y, $S(635), $black, $font, $FS(20), $S(27));

    $serviceNorm = rs_esquela_norm((string)($payload['servicio'] ?? ''));
    if (str_contains($serviceNorm, 'inhumacion') && $inhumacion !== '') {
        $inhText = 'Se realizará la inhumación el ' . $inhumacion;
        if ($destino !== '') {
            $inhText .= ' en ' . $destino;
        }
        $inhText .= '.';
        $y += $S(18);
        $y = rs_esquela_center_text($canvas, $inhText, $y, $S(635), $black, $font, $FS(20), $S(27));
    }

    $memoryY = max($y + $S(48), $S(670));
    $memoryY = min($memoryY, $S(760));
    rs_esquela_center_text(
        $canvas,
        'Ayúdanos a mantener viva su memoria, dejando recuerdos y condolencias en su homenaje de vida, usando este QR.',
        $memoryY,
        $S(500),
        $black,
        $font,
        $FS(20),
        $S(27)
    );

    // Pie alineado en una misma franja: logo circular, direccion y QR.
    $footerCenterY = $S(890);
    rs_esquela_draw_logo($canvas, $S(105), $footerCenterY, $S(135));

    $brandY = $S(858);
    rs_esquela_center_text($canvas, 'Jardines de Juan Pablo', $brandY, $S(360), $brown, $font, $FS(20), $S(26));
    imagesetthickness($canvas, max(1, $S(1)));
    imageline($canvas, $S(252), $S(884), $S(468), $S(884), $brown);
    imagesetthickness($canvas, 1);
    if ($address !== '') {
        rs_esquela_center_text($canvas, $address, $S(914), $S(430), $brown, $font, $FS(18), $S(23));
    }

    $qrUrl = rs_esquela_qr_url($name);
    $qrSize = $S(112);
    $qrX = $S(592);
    $qrY = $footerCenterY - (int)round($qrSize / 2);
    $qrOk = rs_esquela_draw_qr($canvas, $qrUrl, $qrX, $qrY, $qrSize, $qrBytes);
    if (!$qrOk) {
        imagedestroy($canvas);
        throw new RuntimeException('No fue posible generar el codigo QR de la esquela.');
    }

    imageinterlace($canvas, true);
    ob_start();
    imagejpeg($canvas, null, 96);
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
        'qrGenerated' => true,
        'background' => $backgroundKey,
        'prefix' => $prefix,
        'width' => $width,
        'height' => $height,
    ];
}
