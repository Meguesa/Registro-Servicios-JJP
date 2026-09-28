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

function rs_image_font_cache_dir(): string
{
    $candidates = [
        __DIR__ . '/.registro-servicios-data/fonts',
        rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'registro-servicios-fonts',
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
}

function rs_image_valid_font_file(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }

    $size = @filesize($path);
    if (!is_int($size) || $size < 20000) {
        return false;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $magic = (string) fread($handle, 4);
    fclose($handle);

    return $magic === "\x00\x01\x00\x00"
        || $magic === 'OTTO'
        || $magic === 'ttcf';
}

function rs_image_download_font(string $url, string $filename): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $cacheDir = rs_image_font_cache_dir();
    $target = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (rs_image_valid_font_file($target)) {
        return $target;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        return null;
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'JdJP-Registro-Servicios/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $bytes = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($bytes) || $status < 200 || $status >= 300 || strlen($bytes) < 20000) {
        return null;
    }

    $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $bytes, LOCK_EX) === false) {
        @unlink($tmp);
        return null;
    }

    if (!rs_image_valid_font_file($tmp)) {
        @unlink($tmp);
        return null;
    }

    @rename($tmp, $target);
    @chmod($target, 0640);

    return rs_image_valid_font_file($target) ? $target : null;
}

function rs_image_carlito_font_path(bool $bold = false): ?string
{
    $filename = $bold ? 'Carlito-Bold.ttf' : 'Carlito-Regular.ttf';

    $localCandidates = [
        __DIR__ . '/assets/fonts/' . $filename,
        __DIR__ . '/fonts/' . $filename,
    ];

    foreach ($localCandidates as $candidate) {
        if (rs_image_valid_font_file($candidate)) {
            return $candidate;
        }
    }

    $remoteUrls = [
        'https://raw.githubusercontent.com/googlefonts/carlito/main/fonts/ttf/' . $filename,
        'https://raw.githubusercontent.com/google/fonts/main/ofl/carlito/' . $filename,
    ];

    foreach ($remoteUrls as $url) {
        $downloaded = rs_image_download_font($url, $filename);
        if ($downloaded !== null) {
            return $downloaded;
        }
    }

    return null;
}

function rs_image_fontconfig_match(bool $bold = false): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (in_array('shell_exec', $disabled, true)) {
        return null;
    }

    $query = $bold ? 'Arial:style=Bold' : 'Arial';
    $command = 'fc-match -f ' . escapeshellarg('%{file}\\n') . ' ' . escapeshellarg($query) . ' 2>/dev/null';
    $output = @shell_exec($command);
    if (!is_string($output) || trim($output) === '') {
        return null;
    }

    foreach (preg_split('/\\R/', trim($output)) ?: [] as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '' && rs_image_valid_font_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function rs_image_find_system_font(bool $bold = false): ?string
{
    $specific = $bold
        ? [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/local/share/fonts/DejaVuSans-Bold.ttf',
            '/usr/local/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
            '/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/noto/NotoSans-Bold.ttf',
            '/usr/local/cpanel/3rdparty/share/fonts/DejaVuSans-Bold.ttf',
            '/opt/cpanel/ea-php82/root/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ]
        : [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/local/share/fonts/DejaVuSans.ttf',
            '/usr/local/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
            '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/noto/NotoSans-Regular.ttf',
            '/usr/local/cpanel/3rdparty/share/fonts/DejaVuSans.ttf',
            '/opt/cpanel/ea-php82/root/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];

    foreach ($specific as $candidate) {
        if (rs_image_valid_font_file($candidate)) {
            return $candidate;
        }
    }

    $fontConfig = rs_image_fontconfig_match($bold);
    if ($fontConfig !== null) {
        return $fontConfig;
    }

    $directories = [
        '/usr/share/fonts',
        '/usr/local/share/fonts',
        '/usr/local/cpanel/3rdparty/share/fonts',
        '/opt/cpanel/ea-php82/root/usr/share/fonts',
    ];

    $fallback = null;
    foreach ($directories as $dir) {
        if (!is_dir($dir) || !is_readable($dir)) {
            continue;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            $checked = 0;
            foreach ($iterator as $file) {
                if (++$checked > 12000) {
                    break;
                }
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $name = strtolower($file->getFilename());
                if (!str_ends_with($name, '.ttf') && !str_ends_with($name, '.otf')) {
                    continue;
                }

                $isSans = str_contains($name, 'sans')
                    || str_contains($name, 'arial')
                    || str_contains($name, 'dejavu')
                    || str_contains($name, 'noto')
                    || str_contains($name, 'liberation')
                    || str_contains($name, 'roboto');

                if (!$isSans) {
                    continue;
                }

                $path = $file->getPathname();
                if (!rs_image_valid_font_file($path)) {
                    continue;
                }

                $isBold = str_contains($name, 'bold')
                    || str_contains($name, 'semibold')
                    || str_contains($name, 'demi');

                if ($bold === $isBold) {
                    return $path;
                }

                if ($fallback === null) {
                    $fallback = $path;
                }
            }
        } catch (Throwable) {
            // Se conserva el siguiente fallback disponible.
        }
    }

    return $fallback;
}

function rs_image_font_path(): ?string
{
    static $resolved = false;
    static $font = null;

    if ($resolved) {
        return $font;
    }
    $resolved = true;

    // Carlito es metricamente compatible con Calibri y mantiene un aspecto
    // corporativo limpio sin depender de una licencia de Microsoft.
    $font = rs_image_carlito_font_path(false);
    if ($font !== null) {
        return $font;
    }

    $font = rs_image_find_system_font(false);
    return $font;
}

function rs_image_bold_font_path(): ?string
{
    static $resolved = false;
    static $font = null;

    if ($resolved) {
        return $font;
    }
    $resolved = true;

    $font = rs_image_carlito_font_path(true);
    if ($font !== null) {
        return $font;
    }

    $font = rs_image_find_system_font(true);
    return $font;
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
        return $value ? 'Sí' : 'No';
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

function rs_image_ascii(string $value): string
{
    $value = rs_image_clean_text($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return preg_replace('/[^\\x20-\\x7E]/', '', $value) ?? $value;
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

function rs_image_draw_text_bold(GdImage $image, int $x, int $y, string $text, int $color, ?string $fontPath, float $fontSize, int $bitmapFont = 5): void
{
    $boldFont = rs_image_bold_font_path();

    if ($boldFont !== null && function_exists('imagettftext')) {
        @imagettftext($image, $fontSize, 0, $x, $y, $color, $boldFont, $text);
        return;
    }

    if ($fontPath !== null && function_exists('imagettftext')) {
        @imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
        @imagettftext($image, $fontSize, 0, $x + 1, $y, $color, $fontPath, $text);
        return;
    }

    $bitmapText = rs_image_builtin_text($text);
    $bitmapY = max(0, $y - imagefontheight($bitmapFont));
    imagestring($image, $bitmapFont, $x, $bitmapY, $bitmapText, $color);
    imagestring($image, $bitmapFont, $x + 1, $bitmapY, $bitmapText, $color);
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


function rs_image_text_width(string $text, ?string $fontPath, float $fontSize, int $bitmapFont = 5, bool $bold = false): int
{
    $text = rs_image_clean_text($text);
    if ($text === '') {
        return 0;
    }

    $measureFont = $bold ? (rs_image_bold_font_path() ?? $fontPath) : $fontPath;
    if ($measureFont !== null && function_exists('imagettfbbox')) {
        $box = @imagettfbbox($fontSize, 0, $measureFont, $text);
        if (is_array($box)) {
            return abs((int)$box[2] - (int)$box[0]);
        }
    }

    return strlen(rs_image_builtin_text($text)) * imagefontwidth($bitmapFont);
}

/**
 * Calcula anchos compactos segun el contenido, evitando columnas vacias
 * excesivamente grandes sin sacrificar legibilidad.
 *
 * @param array<int,array{label:string,value:string}> $rows
 * @return array{label:int,value:int}
 */
function rs_image_compact_widths(array $rows, ?string $fontPath, float $fontSize, int $bitmapFont = 5): array
{
    $maxLabel = 0;
    $maxValue = 0;

    foreach ($rows as $row) {
        $maxLabel = max(
            $maxLabel,
            rs_image_text_width((string)($row['label'] ?? ''), $fontPath, $fontSize, $bitmapFont, true)
        );
        $maxValue = max(
            $maxValue,
            rs_image_text_width((string)($row['value'] ?? ''), $fontPath, $fontSize, $bitmapFont, false)
        );
    }

    return [
        'label' => max(150, min(330, $maxLabel + 34)),
        'value' => max(170, min(650, $maxValue + 34)),
    ];
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
        'ink' => imagecolorallocate($image, 44, 39, 36),
        'muted' => imagecolorallocate($image, 107, 99, 94),
        'navy' => imagecolorallocate($image, 20, 82, 122),
        'gold' => imagecolorallocate($image, 244, 181, 43),
        'labelBg' => imagecolorallocate($image, 246, 248, 250),
        'line' => imagecolorallocate($image, 222, 226, 230),
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

    $titleY = 58;
    rs_image_draw_text_bold($image, $margin, $titleY, $title, $c['ink'], $fontPath, 27.0, 5);

    if ($subtitle !== '') {
        rs_image_draw_text($image, $margin, 94, $subtitle, $c['muted'], $fontPath, 15.0, 4);
    }

    imageline($image, $margin, $headerHeight - 8, $width - $margin, $headerHeight - 8, $c['line']);
}

/**
 * @param array<int,array{label:string,value:string}> $rows

/**
 * @param array<int,array{label:string,value:string}> $rows
 */
function rs_image_render_table(string $title, string $subtitle, array $rows): string
{
    $margin = 46;
    $gap = 18;
    $headerHeight = 132;
    $footerHeight = 42;
    $fontSize = 18.0;
    $bitmapFont = 5;

    [$probe, $fontPath] = rs_image_render_start(100, 100);
    imagedestroy($probe);

    $compact = rs_image_compact_widths($rows, $fontPath, $fontSize, $bitmapFont);
    $labelWidth = $compact['label'];
    $valueWidth = $compact['value'];

    // El lienzo se ajusta al contenido real, con limites razonables para correo.
    $width = max(620, min(1180, ($margin * 2) + $labelWidth + $gap + $valueWidth));
    $valueWidth = $width - ($margin * 2) - $labelWidth - $gap;

    [$image, $fontPath, $c] = rs_image_render_start($width, 100);
    $lineHeight = rs_image_line_height($fontPath, $fontSize, $bitmapFont);

    $prepared = [];
    $bodyHeight = 0;
    foreach ($rows as $row) {
        $labelLines = rs_image_wrap_lines(rs_image_clean_text($row['label'] ?? ''), $labelWidth - 30, $fontPath, $fontSize, $bitmapFont);
        $valueLines = rs_image_wrap_lines(rs_image_clean_text($row['value'] ?? ''), $valueWidth - 30, $fontPath, $fontSize, $bitmapFont);
        $lines = max(count($labelLines), count($valueLines));
        $rowHeight = max(50, ($lines * $lineHeight) + 18);
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
            rs_image_draw_text_bold($image, $margin + 16, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
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
    $margin = 46;
    $headerHeight = 132;
    $footerHeight = 42;
    $columnGap = 24;
    $innerGap = 16;
    $fontSize = 17.0;
    $bitmapFont = 5;

    [$probe, $fontPath] = rs_image_render_start(100, 100);
    imagedestroy($probe);

    $leftCompact = rs_image_compact_widths($leftRows, $fontPath, $fontSize, $bitmapFont);
    $rightCompact = rs_image_compact_widths($rightRows, $fontPath, $fontSize, $bitmapFont);

    $leftLabelWidth = $leftCompact['label'];
    $leftValueWidth = $leftCompact['value'];
    $rightLabelWidth = $rightCompact['label'];
    $rightValueWidth = $rightCompact['value'];

    $leftColumnWidth = $leftLabelWidth + $innerGap + $leftValueWidth;
    $rightColumnWidth = $rightLabelWidth + $innerGap + $rightValueWidth;
    $width = max(
        940,
        min(1500, ($margin * 2) + $leftColumnWidth + $columnGap + $rightColumnWidth)
    );

    // Si el contenido excede el limite, repartir el ajuste principalmente
    // entre las columnas de valores, manteniendo etiquetas legibles.
    $availableColumnsWidth = $width - ($margin * 2) - $columnGap;
    $naturalColumnsWidth = $leftColumnWidth + $rightColumnWidth;
    if ($naturalColumnsWidth > $availableColumnsWidth) {
        $overflow = $naturalColumnsWidth - $availableColumnsWidth;
        $leftReduction = (int)ceil($overflow / 2);
        $rightReduction = $overflow - $leftReduction;
        $leftValueWidth = max(190, $leftValueWidth - $leftReduction);
        $rightValueWidth = max(190, $rightValueWidth - $rightReduction);
        $leftColumnWidth = $leftLabelWidth + $innerGap + $leftValueWidth;
        $rightColumnWidth = $rightLabelWidth + $innerGap + $rightValueWidth;
    }

    [$image, $fontPath, $c] = rs_image_render_start($width, 100);
    $lineHeight = rs_image_line_height($fontPath, $fontSize, $bitmapFont);

    $maxRows = max(count($leftRows), count($rightRows));
    $prepared = [];
    $bodyHeight = 0;

    for ($i = 0; $i < $maxRows; $i++) {
        $left = $leftRows[$i] ?? ['label' => '', 'value' => ''];
        $right = $rightRows[$i] ?? ['label' => '', 'value' => ''];

        $leftLabelLines = rs_image_wrap_lines(rs_image_clean_text($left['label'] ?? ''), $leftLabelWidth - 22, $fontPath, $fontSize, $bitmapFont);
        $leftValueLines = rs_image_wrap_lines(rs_image_clean_text($left['value'] ?? ''), $leftValueWidth - 10, $fontPath, $fontSize, $bitmapFont);
        $rightLabelLines = rs_image_wrap_lines(rs_image_clean_text($right['label'] ?? ''), $rightLabelWidth - 22, $fontPath, $fontSize, $bitmapFont);
        $rightValueLines = rs_image_wrap_lines(rs_image_clean_text($right['value'] ?? ''), $rightValueWidth - 10, $fontPath, $fontSize, $bitmapFont);

        $lines = max(count($leftLabelLines), count($leftValueLines), count($rightLabelLines), count($rightValueLines));
        $rowHeight = max(48, ($lines * $lineHeight) + 16);
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
    $rightX = $margin + $leftColumnWidth + $columnGap;
    $y = $headerHeight;

    foreach ($prepared as [$leftLabelLines, $leftValueLines, $rightLabelLines, $rightValueLines, $rowHeight]) {
        imageline($image, $margin, $y, $width - $margin, $y, $c['line']);

        imagefilledrectangle($image, $leftX, $y, $leftX + $leftLabelWidth, $y + $rowHeight, $c['labelBg']);
        imagefilledrectangle($image, $rightX, $y, $rightX + $rightLabelWidth, $y + $rowHeight, $c['labelBg']);

        $textY = $y + 28;
        foreach ($leftLabelLines as $lineText) {
            rs_image_draw_text_bold($image, $leftX + 12, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }
        $textY = $y + 28;
        foreach ($leftValueLines as $lineText) {
            rs_image_draw_text($image, $leftX + $leftLabelWidth + $innerGap, $textY, $lineText, $c['ink'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }

        $textY = $y + 28;
        foreach ($rightLabelLines as $lineText) {
            rs_image_draw_text_bold($image, $rightX + 12, $textY, $lineText, $c['navy'], $fontPath, $fontSize, $bitmapFont);
            $textY += $lineHeight;
        }
        $textY = $y + 28;
        foreach ($rightValueLines as $lineText) {
            rs_image_draw_text($image, $rightX + $rightLabelWidth + $innerGap, $textY, $lineText, $c['ink'], $fontPath, $fontSize, $bitmapFont);
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
        ['label' => 'Ubicación servicio capilla', 'value' => rs_image_clean_text($payload['ubicacion'] ?? '')],
        ['label' => 'Sala', 'value' => rs_image_clean_text($payload['sala'] ?? '')],
        ['label' => 'Previsión / Uso inmediato', 'value' => rs_image_clean_text($payload['prevision'] ?? '')],
        ['label' => 'Servicio', 'value' => rs_image_clean_text($payload['servicio'] ?? '')],
        ['label' => 'Ubicación de rescate', 'value' => rs_image_clean_text($payload['ubicacionRescate'] ?? '')],
        ['label' => 'Motivo de fallecimiento', 'value' => rs_image_clean_text($payload['motivo'] ?? '')],
        ['label' => 'Personal de rescate', 'value' => rs_image_rescate($payload)],
        ['label' => 'Vendedor', 'value' => rs_image_clean_text($payload['personalVenta'] ?? '')],
    ];

    $right = [
        ['label' => 'Titular', 'value' => rs_image_clean_text($payload['titular'] ?? '')],
        ['label' => 'Fallecido(a)', 'value' => rs_image_clean_text($payload['fallecido'] ?? '')],
        ['label' => 'Fecha de nacimiento', 'value' => rs_image_date((string) ($payload['fechaNacimiento'] ?? ''), false)],
        ['label' => 'Fecha de defunción', 'value' => rs_image_date((string) ($payload['fechaDefuncion'] ?? ''), false)],
        ['label' => 'Edad', 'value' => rs_image_clean_text($payload['edad'] ?? '')],
        ['label' => 'Referencia', 'value' => rs_image_reference($payload)],
        ['label' => 'Número de servicio', 'value' => rs_image_clean_text($payload['numeroServicio'] ?? $payload['numeroReferencia'] ?? '')],
        ['label' => 'Ataúd / Urna', 'value' => rs_image_clean_text($payload['tipoAtaud'] ?? '')],
    ];

    $servicio = rs_image_lower((string)($payload['servicio'] ?? ''));
    if (str_contains($servicio, 'cremac')) {
        $left[] = ['label' => 'Referencia crematorio', 'value' => rs_image_clean_text($payload['referenciaCrematorio'] ?? '')];
        $right[] = ['label' => 'Inicio crematorio', 'value' => rs_image_date((string) ($payload['inicioCrematorio'] ?? ''))];
        $right[] = ['label' => 'Personal crematorio', 'value' => rs_image_clean_text($payload['personalCrematorio'] ?? '')];
    }
    if (str_contains($servicio, 'inhum')) {
        $left[] = ['label' => 'Fecha y hora inhumación', 'value' => rs_image_date((string) ($payload['fechaHoraInhumacion'] ?? ''))];
    }

    return [$left, $right];
}

function rs_image_obituario_rows(array $payload): array
{
    return [
        ['label' => 'Fallecido(a)', 'value' => rs_image_clean_text($payload['fallecido'] ?? '')],
        ['label' => 'Edad', 'value' => rs_image_clean_text($payload['edad'] ?? '')],
        ['label' => 'Ubicación servicio capilla', 'value' => rs_image_clean_text($payload['ubicacion'] ?? '')],
        ['label' => 'Sala', 'value' => rs_image_clean_text($payload['sala'] ?? '')],
        ['label' => 'Fecha de inicio', 'value' => rs_image_date((string) ($payload['inicio'] ?? ''), false)],
        ['label' => 'Hora de inicio', 'value' => rs_image_time((string) ($payload['inicio'] ?? ''))],
        ['label' => 'Fecha de término', 'value' => rs_image_date((string) ($payload['termino'] ?? ''), false)],
        ['label' => 'Hora de término', 'value' => rs_image_time((string) ($payload['termino'] ?? ''))],
        ['label' => 'Fecha exequia', 'value' => (bool) ($payload['llevaExequia'] ?? false) ? rs_image_date((string) ($payload['horaExequia'] ?? ''), false) : ''],
        ['label' => 'Hora exequia', 'value' => (bool) ($payload['llevaExequia'] ?? false) ? rs_image_time((string) ($payload['horaExequia'] ?? '')) : ''],
        ['label' => 'Ubicación post capillas', 'value' => rs_image_clean_text($payload['destinoFinal'] ?? '')],
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
        'servicePng' => rs_image_render_two_column_table('INFORMACIÓN DEL SERVICIO', $subtitle, $serviceLeft, $serviceRight),
        'obitName' => 'Obituario.png',
        'obitPng' => rs_image_render_table('OBITUARIO', $subtitle, rs_image_obituario_rows($payload)),
        'saleName' => 'Informacion_Venta.png',
        'salePng' => rs_image_render_table('INFORMACIÓN DE VENTA', $subtitle, rs_image_sale_rows($payload)),
        'font' => rs_image_font_path(),
    ];
}
