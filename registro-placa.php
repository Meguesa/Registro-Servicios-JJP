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

function rs_plate_font_path(): ?string
{
    static $resolved = false;
    static $font = null;

    if ($resolved) {
        return $font;
    }
    $resolved = true;

    $candidates = [
        '/usr/share/fonts/truetype/msttcorefonts/pala.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/Palatino_Linotype.ttf',
        '/usr/share/fonts/opentype/urw-base35/P052-Roman.otf',
        '/usr/share/fonts/OTF/P052-Roman.otf',
        '/usr/share/fonts/truetype/urw-base35/P052-Roman.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSerif-Regular.ttf',
        '/usr/share/fonts/liberation/LiberationSerif-Regular.ttf',
    ];

    foreach ($candidates as $candidate) {
        if (function_exists('rs_image_valid_font_file') && rs_image_valid_font_file($candidate)) {
            $font = $candidate;
            return $font;
        }
    }

    if (function_exists('shell_exec')) {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!in_array('shell_exec', $disabled, true)) {
            foreach (['Palatino Linotype', 'Palatino', 'P052', 'URW Palladio L', 'TeX Gyre Pagella', 'Liberation Serif'] as $query) {
                $command = 'fc-match -f ' . escapeshellarg('%{file}\\n') . ' ' . escapeshellarg($query) . ' 2>/dev/null';
                $output = @shell_exec($command);
                if (!is_string($output) || trim($output) === '') {
                    continue;
                }
                foreach (preg_split('/\\R/', trim($output)) ?: [] as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate !== '' && function_exists('rs_image_valid_font_file') && rs_image_valid_font_file($candidate)) {
                        $font = $candidate;
                        return $font;
                    }
                }
            }
        }
    }

    $font = rs_image_font_path();
    return $font;
}

function rs_plate_png_chunk(string $type, string $data): string
{
    $crc = crc32($type . $data);
    if ($crc < 0) {
        $crc += 4294967296;
    }
    return pack('N', strlen($data)) . $type . $data . pack('N', $crc);
}

function rs_plate_background_png(): string
{
    $cacheDir = __DIR__ . '/.registro-servicios-data/templates';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0750, true);
    }
    $cache = $cacheDir . '/fondo_placa_urna.png';
    if (is_file($cache) && is_readable($cache) && (time() - (int) @filemtime($cache) < 86400)) {
        $cached = @file_get_contents($cache);
        if (is_string($cached) && str_starts_with($cached, "\x89PNG\r\n\x1a\n")) {
            return $cached;
        }
    }

    $pdf = rs_plate_template_pdf();
    $marker = '18 0 obj';
    $objPos = strpos($pdf, $marker);
    if ($objPos === false) {
        throw new RuntimeException('No se encontro el fondo fijo dentro de plantilla_placa_urna.pdf.');
    }

    $streamPos = strpos($pdf, 'stream', $objPos);
    if ($streamPos === false) {
        throw new RuntimeException('No se encontro el stream del fondo fijo de la placa.');
    }

    $dict = substr($pdf, $objPos, $streamPos - $objPos);
    if (!preg_match('/\/Width\s+(\d+)/', $dict, $wm)
        || !preg_match('/\/Height\s+(\d+)/', $dict, $hm)
        || !preg_match('/\/Length\s+(\d+)/', $dict, $lm)
        || !str_contains($dict, '/Filter/FlateDecode')
        || !str_contains($dict, '/ColorSpace/DeviceRGB')) {
        throw new RuntimeException('El fondo fijo de la placa no tiene el formato RGB esperado.');
    }

    $width = (int) $wm[1];
    $height = (int) $hm[1];
    $length = (int) $lm[1];
    if ($width <= 0 || $height <= 0 || $length <= 0) {
        throw new RuntimeException('Las dimensiones del fondo fijo de la placa no son validas.');
    }

    $dataPos = $streamPos + strlen('stream');
    if (substr($pdf, $dataPos, 2) === "\r\n") {
        $dataPos += 2;
    } elseif (substr($pdf, $dataPos, 1) === "\n" || substr($pdf, $dataPos, 1) === "\r") {
        $dataPos += 1;
    }

    $compressed = substr($pdf, $dataPos, $length);
    $raw = @gzuncompress($compressed);
    if (!is_string($raw)) {
        $raw = @gzinflate($compressed);
    }
    if (!is_string($raw)) {
        throw new RuntimeException('No fue posible descomprimir el fondo fijo de la placa.');
    }

    $expected = $width * $height * 3;
    if (strlen($raw) !== $expected) {
        throw new RuntimeException('El fondo fijo de la placa no tiene la longitud RGB esperada.');
    }

    $scanlines = '';
    $rowBytes = $width * 3;
    for ($y = 0; $y < $height; $y++) {
        $scanlines .= "\x00" . substr($raw, $y * $rowBytes, $rowBytes);
    }

    $idat = gzcompress($scanlines, 9);
    if (!is_string($idat)) {
        throw new RuntimeException('No fue posible codificar el fondo fijo como PNG.');
    }

    $png = "\x89PNG\r\n\x1a\n"
        . rs_plate_png_chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        . rs_plate_png_chunk('IDAT', $idat)
        . rs_plate_png_chunk('IEND', '');

    @file_put_contents($cache, $png, LOCK_EX);
    return $png;
}

function rs_plate_draw_centered_box_text(
    GdImage $image,
    string $font,
    float $size,
    float $boxX,
    float $boxWidth,
    float $baselineY,
    string $text,
    int $color
): void {
    $box = imagettfbbox($size, 0, $font, $text);
    $width = is_array($box) ? abs((int) $box[2] - (int) $box[0]) : 0;
    $x = (int) round($boxX + max(0, ($boxWidth - $width) / 2));
    imagettftext($image, $size, 0, $x, (int) round($baselineY), $color, $font, $text);
}

function rs_plate_pdf_baseline_to_png(float $placementY, float $localBaselineY, float $pageHeightPt, float $scale): float
{
    return ($pageHeightPt - ($placementY + $localBaselineY)) * $scale;
}

/**
 * Rasteriza el PDF final de la placa a PNG.
 *
 * IMPORTANTE: el PNG NO vuelve a dibujar el nombre ni las fechas con GD.
 * Se convierte el mismo PDF final generado con la plantilla original, por lo
 * que tipografia, posiciones, interlineado y proporciones son exactamente los
 * mismos que en el PDF de referencia.
 */
function rs_plate_pdf_to_png(string $pdf): string
{
    if ($pdf === '' || !str_starts_with($pdf, '%PDF-')) {
        throw new RuntimeException('El contenido recibido para rasterizar no es un PDF valido.');
    }

    $baseDir = __DIR__ . '/.registro-servicios-data/tmp';
    if (!is_dir($baseDir) && !@mkdir($baseDir, 0750, true) && !is_dir($baseDir)) {
        throw new RuntimeException('No fue posible preparar el directorio temporal de la placa.');
    }

    $suffix = bin2hex(random_bytes(8));
    $pdfPath = $baseDir . '/placa-' . $suffix . '.pdf';
    $outBase = $baseDir . '/placa-' . $suffix;
    $pngPath = $outBase . '.png';

    if (@file_put_contents($pdfPath, $pdf, LOCK_EX) === false) {
        throw new RuntimeException('No fue posible escribir el PDF temporal de la placa.');
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $canShell = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);

    try {
        if (!$canShell) {
            throw new RuntimeException(
                'El servidor no permite shell_exec; no es posible convertir el PDF final a PNG conservando exactamente su tipografia.'
            );
        }

        $commands = [
            // Poppler: primera opcion por fidelidad y disponibilidad comun en Linux/cPanel.
            'pdftoppm -f 1 -singlefile -png -r 300 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outBase) . ' 2>&1',
            // Alternativa Poppler.
            'pdftocairo -f 1 -singlefile -png -r 300 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outBase) . ' 2>&1',
            // MuPDF.
            'mutool draw -q -r 300 -o ' . escapeshellarg($pngPath) . ' ' . escapeshellarg($pdfPath) . ' 1 2>&1',
            // Ghostscript.
            'gs -q -dSAFER -dBATCH -dNOPAUSE -dFirstPage=1 -dLastPage=1 -sDEVICE=png16m -r300 '
                . '-sOutputFile=' . escapeshellarg($pngPath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1',
        ];

        $errors = [];
        foreach ($commands as $command) {
            @unlink($pngPath);
            $output = @shell_exec($command);

            if (is_file($pngPath) && filesize($pngPath) > 100) {
                $png = @file_get_contents($pngPath);
                if (is_string($png) && str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                    return $png;
                }
            }

            $message = trim((string) $output);
            if ($message !== '') {
                $errors[] = $message;
            }
        }

        $detail = $errors !== [] ? ' Detalle: ' . implode(' | ', array_slice($errors, 0, 2)) : '';
        throw new RuntimeException(
            'No se encontro un rasterizador PDF disponible (pdftoppm, pdftocairo, mutool o Ghostscript).' . $detail
        );
    } finally {
        @unlink($pdfPath);
        @unlink($pngPath);
        @unlink($outBase . '-1.png');
    }
}

/**
 * Genera la placa final EXCLUSIVAMENTE como PNG.
 *
 * El PDF original es la fuente maestra. Primero se rellena y aplana
 * plantilla_placa_urna.pdf con su propia Palatino Linotype y con las posiciones
 * exactas de la plantilla. Despues se rasteriza ESE MISMO PDF a PNG.
 *
 * Resultado: el PNG mantiene exactamente la misma tipografia y composicion
 * visual que el PDF de referencia; no existe una segunda version dibujada con GD.
 *
 * @return array{pngName:string,png:string}
 */
function rs_generate_urna_plate(array $payload): array
{
    $name = trim((string) ($payload['fallecido'] ?? ''));
    $birthRaw = trim((string) ($payload['fechaNacimiento'] ?? ''));
    $deathRaw = trim((string) ($payload['fechaDefuncion'] ?? ''));

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

    $displayName = mb_strtoupper($name, 'UTF-8');

    // FUENTE MAESTRA:
    // rellena la plantilla PDF original con sus fuentes/metricas originales.
    $finalPdf = rs_plate_fill_original_template(
        $displayName,
        $birth->format('d/m/Y'),
        $death->format('d/m/Y')
    );

    // RESULTADO FINAL:
    // rasteriza el PDF ya terminado; el PNG no redibuja ningun texto.
    $png = rs_plate_pdf_to_png($finalPdf);

    if ($png === '') {
        throw new RuntimeException('No fue posible generar la placa PNG desde el PDF final.');
    }

    $itemId = preg_replace('/[^0-9A-Za-z_-]+/', '', trim((string) ($payload['itemId'] ?? '')));
    if ($itemId === '') {
        $itemId = 'PRUEBA';
    }

    return [
        'pngName' => 'Placa-' . $itemId . '.png',
        'png' => $png,
    ];
}
