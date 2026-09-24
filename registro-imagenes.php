<?php

declare(strict_types=1);

/**
 * FASE 2 - Generacion de imagenes informativas para Registro de Servicios.
 *
 * Modulo aislado:
 * - No envia correos.
 * - No modifica SharePoint.
 * - No crea eventos.
 * - No ejecuta Tellmebye.
 */

function rs_image_font_path(): ?string
{
    static $resolved = false;
    static $font = null;

    if ($resolved) {
        return $font;
    }
    $resolved = true;

    $candidates = [
        __DIR__ . '/assets/fonts/DejaVuSans.ttf',
        __DIR__ . '/assets/fonts/NotoSans-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        '/usr/share/fonts/noto/NotoSans-Regular.ttf',
        '/usr/share/fonts/google-noto/NotoSans-Regular.ttf',
        '/usr/local/share/fonts/DejaVuSans.ttf',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $font = $candidate;
            return $font;
        }
    }

    foreach ([
        '/usr/share/fonts',
        '/usr/local/share/fonts',
        '/usr/local/cpanel/3rdparty/share/fonts',
    ] as $dir) {
        if (!is_dir($dir) || !is_readable($dir)) {
            continue;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            $checked = 0;
            foreach ($iterator as $file) {
                if (++$checked > 1500) {
                    break;
                }
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $name = strtolower($file->getFilename());
                if (!str_ends_with($name, '.ttf')) {
                    continue;
                }
                if (
                    str_contains($name, 'sans') ||
                    str_contains($name, 'arial') ||
                    str_contains($name, 'dejavu') ||
                    str_contains($name, 'noto')
                ) {
                    $font = $file->getPathname();
                    return $font;
                }
            }
        } catch (Throwable) {
            // fallback a fuente bitmap de GD
        }
    }

    return null;
}

function rs_image_require_gd(): void
{
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
        throw new RuntimeException('El servidor no tiene GD con soporte PNG disponible.');
    }
}

function rs_image_clean_text(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? 'Si' : 'No';
    }
    if ($value === null) {
        return '';
    }
    $text = trim((string) $value);
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function rs_image_builtin_text(string $value): string
{
    $value = rs_image_clean_text($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }
    return $value;
}

function rs_image_date(string $value, bool $withTime = true): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $tz = new DateTimeZone('America/Monterrey');
    foreach ([
        'Y-m-d\\TH:i:s',
        'Y-m-d\\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'Y-m-d',
        'd/m/Y',
    ] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            $hasTime = str_contains($format, 'H:i');
            if ($withTime && $hasTime) {
                return $dt->format('d/m/Y H:i');
            }
            return $dt->format('d/m/Y');
        }
    }

    return $value;
}

function rs_image_time(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $tz = new DateTimeZone('America/Monterrey');
    foreach ([
        'Y-m-d\\TH:i:s',
        'Y-m-d\\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'H:i:s',
        'H:i',
    ] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('H:i');
        }
    }

    return $value;
}

function rs_image_lower(string $value): string
{
    $value = rs_image_clean_text($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }
    return strtolower($value);
}

function rs_image_money(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_numeric($value)) {
        return rs_image_clean_text($value);
    }
    return '$' . number_format((float) $value, 2, '.', ',');
}

function rs_image_wrap_lines(string $text, int $maxWidth, ?string $fontPath, float $fontSize, int $bitmapFont = 5): array
{
    $text = rs_image_clean_text($text);
    if ($text === '') {
        return [''];
    }

    $measure = static function (string $candidate) use ($fontPath, $fontSize, $bitmapFont): int {
        if ($fontPath !== null && function_exists('imagettfbbox')) {
            $box = @imagettfbbox($fontSize, 0, $fontPath, $candidate);
            if (is_array($box)) {
                return abs((int) $box[2] - (int) $box[0]);
            }
        }
        return strlen(rs_image_builtin_text($candidate)) * imagefontwidth($bitmapFont);
    };

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && $measure($candidate) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines !== [] ? $lines : [''];
}

function rs_image_draw_text(GdImage $image, int $x, int $y, string $text, int $color, ?string $fontPath, float $fontSize, int $bitmapFont = 5): void
{
    if ($fontPath !== null && function_exists('imagettftext')) {
        @imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
        return;
    }

    imagestring($image, $bitmapFont, $x, max(0, $y - imagefontheight($bitmapFont)), rs_image_builtin_text($text), $color);
}

function rs_image_line_height(?string $fontPath, float $fontSize, int $bitmapFont = 5): int
{
    if ($fontPath !== null && function_exists('imagettfbbox')) {
        $box = @imagettfbbox($fontSize, 0, $fontPath, 'Ag');
        if (is_array($box)) {
            return max(18, abs((int) $box[7] - (int) $box[1]) + 8);
        }
    }
    return imagefontheight($bitmapFont) + 8;
}

function rs_image_render_start(int $width, int $height): array
{
    rs_image_require_gd();

    $fontPath = rs_image_font_path();
    $image = imagecreatetruecolor($width, $height);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('No fue posible crear el lienzo PNG.');
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $c = [
        'white' => imagecolorallocate($image, 255, 255, 255),
        'ink' => imagecolorallocate($image, 55, 38, 33),
        'muted' => imagecolorallocate($image, 100, 90, 86),
        'navy' => imagecolorallocate($image, 15, 83, 124),
        'gold' => imagecolorallocate($image, 246, 179, 33),
        'labelBg' => imagecolorallocate($image, 244, 248, 251),
        'line' => imagecolorallocate($image, 220, 224, 227),
    ];

    imagefilledrectangle($image, 0, 0, $width, $height, $c['white']);

    return [$image, $fontPath, $c];
}

function rs_image_output(GdImage $image): string
{
    ob_start();
    imagepng($image, null, 6);
    $png = ob_get_clean();
    imagedestroy($image);
    if (!is_string($png) || $png === '') {
        throw new RuntimeException('No fue posible generar la imagen PNG.');
    }
    return $png;
}

function rs_image_draw_header(GdImage $image, array $c, ?string $fontPath, string $title, string $subtitle, int $width, int $margin, int $headerHeight): void
{
    imagefilledrectangle($image, 0, 0, $width, 12, $c['gold']);
    imagefilledrectangle($image, 0, 12, 14, $headerHeight - 1, $c['navy']);

    rs_image_draw_text($image, $margin, 60, $title, $c['ink'], $fontPath, 32.0, 5);
    if ($subtitle !== '') {
        rs_image_draw_text($image, $margin, 98, $subtitle, $c['muted'], $fontPath, 18.0, 5);
    }
}

/**
 * @param array<int,array{label:string,value:string}> $rows
 */
function rs_image_render_table(string $title, string $subtitle, array $rows): string
{
    $width = 1400;
    $margin = 46;
    $labelWidth = 390;
    $gap = 26;
    $headerHeight = 132;
    $footerHeight = 42;
    $fontSize = 22.0;
    $bitmapFont = 5;

    [$image, $fontPath, $c] = rs_image_render_start($width, 100);
    $lineHeight = rs_image_line_height($fontPath, $fontSize, $bitmapFont);
    $valueWidth = $width - ($margin * 2) - $labelWidth - $gap;

    $prepared = [];
    $bodyHeight = 0;
    foreach ($rows as $row) {
        $labelLines = rs_image_wrap_lines(rs_image_clean_text($row['label'] ?? ''), $labelWidth - 30, $fontPath, $fontSize, $bitmapFont);
        $valueLines = rs_image_wrap_lines(rs_image_clean_text($row['value'] ?? ''), $valueWidth - 30, $fontPath, $fontSize, $bitmapFont);
        $lines = max(count($labelLines), count($valueLines));
        $rowHeight = max(54, ($lines * $lineHeight) + 22);
        $prepared[] = [$labelLines, $valueLines, $rowHeight];
        $bodyHeight += $rowHeight;
    }

    $height = $headerHeight + $bodyHeight + $footerHeight;
    imagedestroy($image);
    [$image, $fontPath, $c] = rs_image_render_start($width, $height);

    rs_image_draw_header($image, $c, $fontPath, $title, $subtitle, $width, $margin, $headerHeight);

    $y = $headerHeight;
    foreach ($prepared as [$labelLines, $valueLines, $rowHeight]) {
        imagefilledrectangle($image, $margin, $y, $margin + $labelWidth, $y + $rowHeight, $c['labelBg']);
        imageline($image, $margin, $y, $width - $margin, $y, $c['line']);

        $textY = $y + 31;
        foreach ($labelLines as $lineText) {
            rs_image_draw_text($image, $margin + 16, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }

        $textY = $y + 31;
        foreach ($valueLines as $lineText) {
            rs_image_draw_text($image, $margin + $labelWidth + $gap, $textY, $lineText, $c['ink'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }
        $y += $rowHeight;
    }

    imageline($image, $margin, $y, $width - $margin, $y, $c['line']);
    rs_image_draw_text($image, $margin, $height - 14, 'Jardines de Juan Pablo | Registro de Servicios', $c['muted'], $fontPath, 14.0, 3);

    return rs_image_output($image);
}

/**
 * @param array<int,array{label:string,value:string}> $leftRows
 * @param array<int,array{label:string,value:string}> $rightRows
 */
function rs_image_render_two_column_table(string $title, string $subtitle, array $leftRows, array $rightRows): string
{
    $width = 1600;
    $margin = 46;
    $headerHeight = 132;
    $footerHeight = 42;
    $columnGap = 26;
    $fontSize = 19.0;
    $bitmapFont = 5;

    [$image, $fontPath, $c] = rs_image_render_start($width, 100);
    $lineHeight = rs_image_line_height($fontPath, $fontSize, $bitmapFont);
    $contentWidth = $width - ($margin * 2);
    $columnWidth = (int) floor(($contentWidth - $columnGap) / 2);
    $labelWidth = 240;
    $innerGap = 16;
    $valueWidth = $columnWidth - $labelWidth - $innerGap;

    $maxRows = max(count($leftRows), count($rightRows));
    $prepared = [];
    $bodyHeight = 0;

    for ($i = 0; $i < $maxRows; $i++) {
        $left = $leftRows[$i] ?? ['label' => '', 'value' => ''];
        $right = $rightRows[$i] ?? ['label' => '', 'value' => ''];

        $leftLabelLines = rs_image_wrap_lines(rs_image_clean_text($left['label'] ?? ''), $labelWidth - 22, $fontPath, $fontSize, $bitmapFont);
        $leftValueLines = rs_image_wrap_lines(rs_image_clean_text($left['value'] ?? ''), $valueWidth - 10, $fontPath, $fontSize, $bitmapFont);
        $rightLabelLines = rs_image_wrap_lines(rs_image_clean_text($right['label'] ?? ''), $labelWidth - 22, $fontPath, $fontSize, $bitmapFont);
        $rightValueLines = rs_image_wrap_lines(rs_image_clean_text($right['value'] ?? ''), $valueWidth - 10, $fontPath, $fontSize, $bitmapFont);

        $lines = max(count($leftLabelLines), count($leftValueLines), count($rightLabelLines), count($rightValueLines));
        $rowHeight = max(54, ($lines * $lineHeight) + 18);
        $prepared[] = [
            $leftLabelLines, $leftValueLines,
            $rightLabelLines, $rightValueLines,
            $rowHeight,
        ];
        $bodyHeight += $rowHeight;
    }

    $height = $headerHeight + $bodyHeight + $footerHeight;
    imagedestroy($image);
    [$image, $fontPath, $c] = rs_image_render_start($width, $height);
    rs_image_draw_header($image, $c, $fontPath, $title, $subtitle, $width, $margin, $headerHeight);

    $leftX = $margin;
    $rightX = $margin + $columnWidth + $columnGap;
    $y = $headerHeight;

    foreach ($prepared as [$leftLabelLines, $leftValueLines, $rightLabelLines, $rightValueLines, $rowHeight]) {
        imageline($image, $margin, $y, $width - $margin, $y, $c['line']);

        imagefilledrectangle($image, $leftX, $y, $leftX + $labelWidth, $y + $rowHeight, $c['labelBg']);
        imagefilledrectangle($image, $rightX, $y, $rightX + $labelWidth, $y + $rowHeight, $c['labelBg']);

        $textY = $y + 28;
        foreach ($leftLabelLines as $lineText) {
            rs_image_draw_text($image, $leftX + 12, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }
        $textY = $y + 28;
        foreach ($leftValueLines as $lineText) {
            rs_image_draw_text($image, $leftX + $labelWidth + $innerGap, $textY, $lineText, $c['ink'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }

        $textY = $y + 28;
        foreach ($rightLabelLines as $lineText) {
            rs_image_draw_text($image, $rightX + 12, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }
        $textY = $y + 28;
        foreach ($rightValueLines as $lineText) {
            rs_image_draw_text($image, $rightX + $labelWidth + $innerGap, $textY, $lineText, $c['ink'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }

        $y += $rowHeight;
    }

    imageline($image, $margin, $y, $width - $margin, $y, $c['line']);
    rs_image_draw_text($image, $margin, $height - 14, 'Jardines de Juan Pablo | Registro de Servicios', $c['muted'], $fontPath, 14.0, 3);

    return rs_image_output($image);
}

function rs_image_reference(array $payload): string
{
    return rs_image_clean_text($payload['referencia'] ?? $payload['numeroReferencia'] ?? '');
}

function rs_image_rescate(array $payload): string
{
    $rescate = array_values(array_filter([
        rs_image_clean_text($payload['rescate1'] ?? ''),
        rs_image_clean_text($payload['rescate2'] ?? ''),
    ], static fn(string $value): bool => $value !== ''));

    return $rescate !== [] ? implode(' y ', $rescate) : '';
}

function rs_image_service_columns(array $payload): array
{
    $left = [
        ['label' => 'Ubicacion servicio capilla', 'value' => rs_image_clean_text($payload['ubicacion'] ?? '')],
        ['label' => 'Sala', 'value' => rs_image_clean_text($payload['sala'] ?? '')],
        ['label' => 'Prevision / Uso inmediato', 'value' => rs_image_clean_text($payload['prevision'] ?? '')],
        ['label' => 'Servicio', 'value' => rs_image_clean_text($payload['servicio'] ?? '')],
        ['label' => 'Ubicacion de rescate', 'value' => rs_image_clean_text($payload['ubicacionRescate'] ?? '')],
        ['label' => 'Motivo de fallecimiento', 'value' => rs_image_clean_text($payload['motivo'] ?? '')],
        ['label' => 'Personal de rescate', 'value' => rs_image_rescate($payload)],
        ['label' => 'Vendedor', 'value' => rs_image_clean_text($payload['personalVenta'] ?? '')],
    ];

    $right = [
        ['label' => 'Titular', 'value' => rs_image_clean_text($payload['titular'] ?? '')],
        ['label' => 'Fallecido(a)', 'value' => rs_image_clean_text($payload['fallecido'] ?? '')],
        ['label' => 'Fecha de nacimiento', 'value' => rs_image_date((string) ($payload['fechaNacimiento'] ?? ''), false)],
        ['label' => 'Fecha de defuncion', 'value' => rs_image_date((string) ($payload['fechaDefuncion'] ?? ''), false)],
        ['label' => 'Edad', 'value' => rs_image_clean_text($payload['edad'] ?? '')],
        ['label' => 'Referencia', 'value' => rs_image_reference($payload)],
        ['label' => 'Numero de servicio', 'value' => rs_image_clean_text($payload['numeroServicio'] ?? $payload['numeroReferencia'] ?? '')],
        ['label' => 'Ataud / Urna', 'value' => rs_image_clean_text($payload['tipoAtaud'] ?? '')],
    ];

    $servicio = rs_image_lower((string)($payload['servicio'] ?? ''));
    if (str_contains($servicio, 'cremac')) {
        $left[] = ['label' => 'Referencia crematorio', 'value' => rs_image_clean_text($payload['referenciaCrematorio'] ?? '')];
        $right[] = ['label' => 'Inicio crematorio', 'value' => rs_image_date((string) ($payload['inicioCrematorio'] ?? ''))];
        $right[] = ['label' => 'Personal crematorio', 'value' => rs_image_clean_text($payload['personalCrematorio'] ?? '')];
    }
    if (str_contains($servicio, 'inhum')) {
        $left[] = ['label' => 'Fecha y hora inhumacion', 'value' => rs_image_date((string) ($payload['fechaHoraInhumacion'] ?? ''))];
    }

    return [$left, $right];
}

function rs_image_obituario_rows(array $payload): array
{
    return [
        ['label' => 'Fallecido(a)', 'value' => rs_image_clean_text($payload['fallecido'] ?? '')],
        ['label' => 'Edad', 'value' => rs_image_clean_text($payload['edad'] ?? '')],
        ['label' => 'Ubicacion servicio capilla', 'value' => rs_image_clean_text($payload['ubicacion'] ?? '')],
        ['label' => 'Sala', 'value' => rs_image_clean_text($payload['sala'] ?? '')],
        ['label' => 'Fecha de inicio', 'value' => rs_image_date((string) ($payload['inicio'] ?? ''), false)],
        ['label' => 'Hora de inicio', 'value' => rs_image_time((string) ($payload['inicio'] ?? ''))],
        ['label' => 'Fecha de termino', 'value' => rs_image_date((string) ($payload['termino'] ?? ''), false)],
        ['label' => 'Hora de termino', 'value' => rs_image_time((string) ($payload['termino'] ?? ''))],
        ['label' => 'Fecha exequia', 'value' => (bool) ($payload['llevaExequia'] ?? false) ? rs_image_date((string) ($payload['horaExequia'] ?? ''), false) : ''],
        ['label' => 'Hora exequia', 'value' => (bool) ($payload['llevaExequia'] ?? false) ? rs_image_time((string) ($payload['horaExequia'] ?? '')) : ''],
        ['label' => 'Ubicacion post capillas', 'value' => rs_image_clean_text($payload['destinoFinal'] ?? '')],
    ];
}

function rs_image_sale_rows(array $payload): array
{
    $extras = is_array($payload['serviciosExtra'] ?? null) ? $payload['serviciosExtra'] : [];
    $extraAmounts = is_array($payload['extraAmounts'] ?? null) ? $payload['extraAmounts'] : [];

    $extraParts = [];
    foreach ($extras as $extra) {
        $label = rs_image_clean_text($extra);
        if ($label === '' || rs_image_lower($label) === 'no aplica') {
            continue;
        }
        $amount = $extraAmounts[$label] ?? null;
        $extraParts[] = $amount === null || $amount === ''
            ? $label
            : $label . ' (' . rs_image_money($amount) . ')';
    }

    return [
        ['label' => 'Vendedor', 'value' => rs_image_clean_text($payload['personalVenta'] ?? '')],
        ['label' => 'Precio de lista', 'value' => rs_image_money($payload['precioVenta'] ?? null)],
        ['label' => 'Servicios extra', 'value' => $extraParts !== [] ? implode(', ', $extraParts) : 'Sin servicios extra'],
        ['label' => 'Precio total', 'value' => rs_image_money($payload['ventaTotal'] ?? null)],
    ];
}

/**
 * @return array{serviceName:string,servicePng:string,obitName:string,obitPng:string,saleName:string,salePng:string,font:?string}
 */
function rs_generate_service_information_images(array $payload): array
{
    $reference = rs_image_reference($payload);
    $subtitle = $reference !== '' ? 'Referencia: ' . $reference : 'Registro de servicio';
    [$serviceLeft, $serviceRight] = rs_image_service_columns($payload);

    return [
        'serviceName' => 'Informacion_Servicio.png',
        'servicePng' => rs_image_render_two_column_table('INFORMACION DEL SERVICIO', $subtitle, $serviceLeft, $serviceRight),
        'obitName' => 'Obituario.png',
        'obitPng' => rs_image_render_table('OBITUARIO', $subtitle, rs_image_obituario_rows($payload)),
        'saleName' => 'Informacion_Venta.png',
        'salePng' => rs_image_render_table('INFORMACION DE VENTA', $subtitle, rs_image_sale_rows($payload)),
        'font' => rs_image_font_path(),
    ];
}
