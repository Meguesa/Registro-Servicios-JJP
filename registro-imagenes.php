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
 *
 * Devuelve PNG en memoria para que fases posteriores puedan adjuntarlos al correo.
 */

function rs_image_font_path(): ?string
{
    static $resolved = false;
    static $fontPath = null;

    if ($resolved) {
        return $fontPath;
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
            $fontPath = $candidate;
            return $fontPath;
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
                    $path = $file->getPathname();
                    if (is_readable($path)) {
                        $fontPath = $path;
                        return $fontPath;
                    }
                }
            }
        } catch (Throwable) {
            // Se usa la fuente bitmap incluida en GD.
        }
    }

    return null;
}

function rs_image_require_gd(): void
{
    if (
        !extension_loaded('gd') ||
        !function_exists('imagecreatetruecolor') ||
        !function_exists('imagepng')
    ) {
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

    $text = trim((string)$value);
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
        'd/m/Y H:i',
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

function rs_image_money(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (!is_numeric($value)) {
        return rs_image_clean_text($value);
    }

    return '$' . number_format((float)$value, 2, '.', ',');
}

function rs_image_wrap_lines(
    string $text,
    int $maxWidth,
    ?string $fontPath,
    float $fontSize,
    int $bitmapFont = 5
): array {
    $text = rs_image_clean_text($text);
    if ($text === '') {
        return [''];
    }

    $measure = static function (string $candidate) use ($fontPath, $fontSize, $bitmapFont): int {
        if ($fontPath !== null && function_exists('imagettfbbox')) {
            $box = @imagettfbbox($fontSize, 0, $fontPath, $candidate);
            if (is_array($box)) {
                return abs((int)$box[2] - (int)$box[0]);
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

function rs_image_draw_text(
    GdImage $image,
    int $x,
    int $y,
    string $text,
    int $color,
    ?string $fontPath,
    float $fontSize,
    int $bitmapFont = 5
): void {
    if ($fontPath !== null && function_exists('imagettftext')) {
        @imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
        return;
    }

    imagestring(
        $image,
        $bitmapFont,
        $x,
        max(0, $y - imagefontheight($bitmapFont)),
        rs_image_builtin_text($text),
        $color
    );
}

function rs_image_line_height(?string $fontPath, float $fontSize, int $bitmapFont = 5): int
{
    if ($fontPath !== null && function_exists('imagettfbbox')) {
        $box = @imagettfbbox($fontSize, 0, $fontPath, 'Ag');
        if (is_array($box)) {
            return max(20, abs((int)$box[7] - (int)$box[1]) + 8);
        }
    }

    return imagefontheight($bitmapFont) + 8;
}

/**
 * @param array<int,array{label:string,value:string}> $rows
 */
function rs_image_render_table(string $title, string $subtitle, array $rows): string
{
    rs_image_require_gd();

    $width = 1400;
    $margin = 46;
    $labelWidth = 390;
    $gap = 26;
    $valueWidth = $width - ($margin * 2) - $labelWidth - $gap;
    $fontPath = rs_image_font_path();
    $fontSize = 22.0;
    $titleSize = 32.0;
    $subtitleSize = 18.0;
    $lineHeight = rs_image_line_height($fontPath, $fontSize);
    $bitmapFont = 5;

    $prepared = [];
    $bodyHeight = 0;

    foreach ($rows as $row) {
        $label = rs_image_clean_text($row['label'] ?? '');
        $value = rs_image_clean_text($row['value'] ?? '');
        $labelLines = rs_image_wrap_lines(
            $label,
            $labelWidth - 30,
            $fontPath,
            $fontSize,
            $bitmapFont
        );
        $valueLines = rs_image_wrap_lines(
            $value,
            $valueWidth - 30,
            $fontPath,
            $fontSize,
            $bitmapFont
        );

        $lines = max(count($labelLines), count($valueLines));
        $rowHeight = max(54, ($lines * $lineHeight) + 22);

        $prepared[] = [$labelLines, $valueLines, $rowHeight];
        $bodyHeight += $rowHeight;
    }

    $headerHeight = 132;
    $footerHeight = 42;
    $height = $headerHeight + $bodyHeight + $footerHeight;

    $image = imagecreatetruecolor($width, $height);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('No fue posible crear el lienzo PNG.');
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $white = imagecolorallocate($image, 255, 255, 255);
    $ink = imagecolorallocate($image, 55, 38, 33);
    $muted = imagecolorallocate($image, 100, 90, 86);
    $navy = imagecolorallocate($image, 15, 83, 124);
    $gold = imagecolorallocate($image, 246, 179, 33);
    $labelBg = imagecolorallocate($image, 244, 248, 251);
    $line = imagecolorallocate($image, 220, 224, 227);

    imagefilledrectangle($image, 0, 0, $width, $height, $white);
    imagefilledrectangle($image, 0, 0, $width, 12, $gold);
    imagefilledrectangle($image, 0, 12, 14, $headerHeight - 1, $navy);

    rs_image_draw_text($image, $margin, 60, $title, $ink, $fontPath, $titleSize, $bitmapFont);
    rs_image_draw_text($image, $margin, 98, $subtitle, $muted, $fontPath, $subtitleSize, $bitmapFont);

    $y = $headerHeight;

    foreach ($prepared as [$labelLines, $valueLines, $rowHeight]) {
        imagefilledrectangle(
            $image,
            $margin,
            $y,
            $margin + $labelWidth,
            $y + $rowHeight,
            $labelBg
        );
        imageline($image, $margin, $y, $width - $margin, $y, $line);

        $textY = $y + 31;
        foreach ($labelLines as $lineText) {
            rs_image_draw_text(
                $image,
                $margin + 16,
                $textY,
                $lineText,
                $navy,
                $fontPath,
                $fontSize,
                $bitmapFont
            );
            $textY += $lineHeight;
        }

        $textY = $y + 31;
        foreach ($valueLines as $lineText) {
            rs_image_draw_text(
                $image,
                $margin + $labelWidth + $gap,
                $textY,
                $lineText,
                $ink,
                $fontPath,
                $fontSize,
                $bitmapFont
            );
            $textY += $lineHeight;
        }

        $y += $rowHeight;
    }

    imageline($image, $margin, $y, $width - $margin, $y, $line);

    rs_image_draw_text(
        $image,
        $margin,
        $height - 14,
        'Jardines de Juan Pablo | Registro de Servicios',
        $muted,
        $fontPath,
        14.0,
        3
    );

    ob_start();
    imagepng($image, null, 6);
    $png = ob_get_clean();
    imagedestroy($image);

    if (!is_string($png) || $png === '') {
        throw new RuntimeException('No fue posible generar la imagen PNG.');
    }

    return $png;
}

function rs_image_service_rows(array $payload): array
{
    $rescate = array_values(array_filter([
        rs_image_clean_text($payload['rescate1'] ?? ''),
        rs_image_clean_text($payload['rescate2'] ?? ''),
    ], static fn(string $value): bool => $value !== ''));

    $rows = [
        [
            'label' => 'Numero de referencia',
            'value' => rs_image_clean_text($payload['numeroReferencia'] ?? ''),
        ],
        [
            'label' => 'Referencia',
            'value' => rs_image_clean_text($payload['referencia'] ?? ''),
        ],
        [
            'label' => 'Servicio',
            'value' => rs_image_clean_text($payload['servicio'] ?? ''),
        ],
        [
            'label' => 'Ubicacion',
            'value' => rs_image_clean_text($payload['ubicacion'] ?? ''),
        ],
        [
            'label' => 'Sala',
            'value' => rs_image_clean_text($payload['sala'] ?? ''),
        ],
        [
            'label' => 'Inicio',
            'value' => rs_image_date((string)($payload['inicio'] ?? '')),
        ],
        [
            'label' => 'Termino',
            'value' => rs_image_date((string)($payload['termino'] ?? '')),
        ],
        [
            'label' => 'Exequia',
            'value' => (bool)($payload['llevaExequia'] ?? false)
                ? rs_image_date((string)($payload['horaExequia'] ?? ''))
                : 'No',
        ],
        [
            'label' => 'Prevision / Uso inmediato',
            'value' => rs_image_clean_text($payload['prevision'] ?? ''),
        ],
        [
            'label' => 'Ataud / Urna',
            'value' => rs_image_clean_text($payload['tipoAtaud'] ?? ''),
        ],
        [
            'label' => 'Titular / Responsable',
            'value' => rs_image_clean_text($payload['titular'] ?? ''),
        ],
        [
            'label' => 'Fallecido(a)',
            'value' => rs_image_clean_text($payload['fallecido'] ?? ''),
        ],
        [
            'label' => 'Fecha de nacimiento',
            'value' => rs_image_date(
                (string)($payload['fechaNacimiento'] ?? ''),
                false
            ),
        ],
        [
            'label' => 'Fecha de defuncion',
            'value' => rs_image_date((string)($payload['fechaDefuncion'] ?? '')),
        ],
        [
            'label' => 'Edad',
            'value' => rs_image_clean_text($payload['edad'] ?? ''),
        ],
        [
            'label' => 'Sexo',
            'value' => rs_image_clean_text($payload['sexo'] ?? ''),
        ],
        [
            'label' => 'Destino final',
            'value' => rs_image_clean_text($payload['destinoFinal'] ?? ''),
        ],
        [
            'label' => 'Embalsamador',
            'value' => rs_image_clean_text($payload['embalsamador'] ?? ''),
        ],
        [
            'label' => 'Ubicacion de rescate',
            'value' => rs_image_clean_text($payload['ubicacionRescate'] ?? ''),
        ],
        [
            'label' => 'Personal de rescate',
            'value' => implode(' y ', $rescate),
        ],
        [
            'label' => 'Motivo de fallecimiento',
            'value' => rs_image_clean_text($payload['motivo'] ?? ''),
        ],
    ];

    $servicio = mb_strtolower(
        rs_image_clean_text($payload['servicio'] ?? ''),
        'UTF-8'
    );

    if (str_contains($servicio, 'cremac')) {
        $rows[] = [
            'label' => 'Referencia crematorio',
            'value' => rs_image_clean_text($payload['referenciaCrematorio'] ?? ''),
        ];
        $rows[] = [
            'label' => 'Inicio crematorio',
            'value' => rs_image_date((string)($payload['inicioCrematorio'] ?? '')),
        ];
        $rows[] = [
            'label' => 'Personal crematorio',
            'value' => rs_image_clean_text($payload['personalCrematorio'] ?? ''),
        ];
    }

    if (str_contains($servicio, 'inhum')) {
        $rows[] = [
            'label' => 'Fecha y hora inhumacion',
            'value' => rs_image_date(
                (string)($payload['fechaHoraInhumacion'] ?? '')
            ),
        ];
    }

    return $rows;
}

function rs_image_sale_rows(array $payload): array
{
    $extras = is_array($payload['serviciosExtra'] ?? null)
        ? $payload['serviciosExtra']
        : [];
    $extraAmounts = is_array($payload['extraAmounts'] ?? null)
        ? $payload['extraAmounts']
        : [];

    $extraParts = [];
    foreach ($extras as $extra) {
        $label = rs_image_clean_text($extra);
        if ($label === '') {
            continue;
        }

        $amount = $extraAmounts[$label] ?? null;
        $extraParts[] = $amount === null || $amount === ''
            ? $label
            : $label . ' (' . rs_image_money($amount) . ')';
    }

    return [
        [
            'label' => 'Fecha de compra',
            'value' => rs_image_date(
                (string)($payload['fechaCompra'] ?? ''),
                false
            ),
        ],
        [
            'label' => 'Personal de venta',
            'value' => rs_image_clean_text($payload['personalVenta'] ?? ''),
        ],
        [
            'label' => 'Precio de venta',
            'value' => rs_image_money($payload['precioVenta'] ?? null),
        ],
        [
            'label' => 'Servicios extra',
            'value' => $extraParts !== []
                ? implode(', ', $extraParts)
                : 'Sin servicios extra',
        ],
        [
            'label' => 'Venta total',
            'value' => rs_image_money($payload['ventaTotal'] ?? null),
        ],
    ];
}

/**
 * @return array{
 *   serviceName:string,
 *   servicePng:string,
 *   saleName:string,
 *   salePng:string,
 *   font:?string
 * }
 */
function rs_generate_service_information_images(array $payload): array
{
    $reference = rs_image_clean_text(
        $payload['referencia'] ?? $payload['numeroReferencia'] ?? ''
    );
    $subtitle = $reference !== ''
        ? 'Referencia: ' . $reference
        : 'Registro de servicio';

    return [
        'serviceName' => 'Informacion_Servicio.png',
        'servicePng' => rs_image_render_table(
            'INFORMACION DEL SERVICIO',
            $subtitle,
            rs_image_service_rows($payload)
        ),
        'saleName' => 'Informacion_Venta.png',
        'salePng' => rs_image_render_table(
            'INFORMACION DE VENTA',
            $subtitle,
            rs_image_sale_rows($payload)
        ),
        'font' => rs_image_font_path(),
    ];
}
