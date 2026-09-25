<?php
declare(strict_types=1);

/**
 * FASE 4 AISLADA - Constancia / Carta de Servicio Otorgado.
 *
 * Este modulo:
 * - Genera el documento con el formato visual de la constancia vigente.
 * - Selecciona beneficios segun servicio + paquete/ataud.
 * - NO envia correo.
 * - NO modifica SharePoint.
 * - NO ejecuta Power Automate ni TellMeBye.
 *
 * La salida se rasteriza con GD y se encapsula en un PDF Carta (612 x 792 pt)
 * para evitar dependencias externas de PDF en cPanel.
 */

require_once __DIR__ . '/registro-imagenes.php';

function rs_carta_norm(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') $value = $ascii;

    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $value) ?? $value);
}

function rs_carta_package_key(array $payload): string
{
    $servicio = rs_carta_norm((string)($payload['servicio'] ?? ''));
    if (str_contains($servicio, 'cremacion directa')) {
        return 'CDURNABAS';
    }

    $referencia = strtoupper((string)($payload['referencia'] ?? ''));
    foreach (['ATMETBA', 'ATMADBA', 'ATMADEX', 'ATMADLX', 'CDURNABAS'] as $code) {
        if (str_contains($referencia, $code)) return $code;
    }

    $ataud = rs_carta_norm((string)($payload['tipoAtaud'] ?? ''));
    if (str_contains($ataud, 'metalico basico')) return 'ATMETBA';
    if (str_contains($ataud, 'madera basico')) return 'ATMADBA';
    if (str_contains($ataud, 'madera exclusivo')) return 'ATMADEX';
    if (str_contains($ataud, 'madera de lujo')) return 'ATMADLX';

    return 'ATMETBA';
}

/**
 * Beneficios derivados exclusivamente de Plantilla_UI JdJP suministrada
 * para esta fase. Para codigos no documentados se usa ATMETBA como fallback
 * visual de prueba, sin inferir beneficios nuevos.
 *
 * @return string[]
 */
function rs_carta_benefits(array $payload): array
{
    $package = rs_carta_package_key($payload);
    $servicio = rs_carta_norm((string)($payload['servicio'] ?? ''));
    $isCremation = str_contains($servicio, 'cremacion');
    $isDirect = str_contains($servicio, 'cremacion directa');
    $directWithWake = str_contains($servicio, 'con velacion');
    $tiempoCapillas = trim((string)($payload['tiempoCapillas'] ?? ''));
    $salaVelacion = 'Sala de velación con oratorio'
        . ($tiempoCapillas !== '' ? ' (' . $tiempoCapillas . ')' : '');

    if ($isDirect || $package === 'CDURNABAS') {
        $items = [
            'Traslados locales zona metropolitana de la persona fallecida a CREMATORIO',
            'Cremación',
            'Urna especial en mármol',
            'Placa personalizada',
        ];

        if ($directWithWake) {
            $items[] = 'Velación de cenizas 2 hrs. a disposición de sala';
        }

        return array_merge($items, [
            'Espacio especial para fotografía y Rosario de mármol',
            'Arreglo floral especial para urna',
            'Esquela digital en página web',
        ]);
    }

    $definitions = [
        'ATMETBA' => [
            $salaVelacion,
            'Ataúd metal básico',
            'Embalsamamiento',
            'Preparación estética',
            'Traslados locales de la persona fallecida zona metropolitana',
            'Carroza para traslado a parque funeral zona metropolitana',
            'Confortables instalaciones con área de privado familiar',
            'Espacio especial para fotografía y Rosario de mármol',
            'Arreglo floral especial',
            'Servicio cafetería',
            'Área infantil',
            'Esquela digital en página web y para redes sociales',
        ],
        'ATMADBA' => [
            $salaVelacion,
            'Ataúd madera básico',
            'Embalsamamiento',
            'Preparación estética',
            'Traslados locales de la persona fallecida zona metropolitana',
            'Carroza para traslado a parque funeral zona metropolitana',
            'Confortables instalaciones con área de privado familiar',
            'Espacio especial para fotografía y Rosario de mármol',
            'Arreglo floral especial',
            'Servicio cafetería',
            'Área infantil',
            'Esquela digital en página web y para redes sociales',
        ],
        'ATMADEX' => [
            $salaVelacion,
            'Ataúd madera exclusiva',
            'Embalsamamiento',
            'Preparación estética',
            'Traslados locales de la persona fallecida zona metropolitana',
            'Carroza para traslado a parque funeral zona metropolitana',
            'Confortables instalaciones con área de privado familiar',
            'Espacio especial para fotografía y Rosario de mármol',
            'Arreglo floral especial',
            'Servicio cafetería especial',
            'Área infantil',
            'Esquela digital en página web y para redes sociales',
        ],
        'ATMADLX' => [
            $salaVelacion,
            'Ataúd madera de lujo',
            'Embalsamamiento',
            'Preparación estética',
            'Traslados locales de la persona fallecida zona metropolitana',
            'Carroza para traslado a parque funeral zona metropolitana',
            'Confortables instalaciones con área de privado familiar',
            'Espacio especial para fotografía y Rosario de mármol',
            'Ceremonia de pétalos de flores con violinista',
            'Servicio cafetería especial catering',
            'Área infantil',
            'Esquela digital en página web',
        ],
    ];

    $items = $definitions[$package] ?? $definitions['ATMETBA'];

    if ($isCremation) {
        $items[] = 'Cremación';
        $items[] = 'Urna especial en mármol';
        $items[] = 'Placa personalizada';
    }

    if ($package === 'ATMADLX') {
        $items[] = 'Arreglo floral especial de cuatro piezas';
    }

    // Pagos a terceros incluidos por la plantilla comercial.
    if (!$isDirect) {
        $items[] = 'Servicio ceremonia religiosa exequia (excepto en domingos)';
        $items[] = 'Coro para servicio religioso';
    }

    return $items;
}

function rs_carta_reference(array $payload): string
{
    $reference = trim((string)($payload['referencia'] ?? ''));
    if ($reference !== '') return $reference;

    $serviceCodeMap = [
        'inhumacion' => 'VI',
        'cremacion' => 'VC',
        'cremacion directa con velacion' => 'CD',
        'cremacion directa sin velacion' => 'CD',
    ];
    $serviceKey = rs_carta_norm((string)($payload['servicio'] ?? ''));
    $serviceCode = $serviceCodeMap[$serviceKey] ?? '';
    $package = rs_carta_package_key($payload);
    $numero = trim((string)($payload['numeroServicio'] ?? $payload['numeroReferencia'] ?? ''));

    $parts = array_values(array_filter([$serviceCode, $package, $numero], static fn($v) => $v !== ''));
    return implode(' - ', $parts);
}

function rs_carta_destination(array $payload): string
{
    $dest = trim((string)($payload['destinoFinal'] ?? ''));
    if ($dest !== '') return $dest;

    $servicio = rs_carta_norm((string)($payload['servicio'] ?? ''));
    if (str_contains($servicio, 'cremacion')) {
        return 'Crematorio Jardines de Juan Pablo';
    }
    return 'Parque de Descanso Jardines de Juan Pablo';
}

function rs_carta_measure(string $text, ?string $font, float $size): int
{
    if ($font !== null && function_exists('imagettfbbox')) {
        $box = @imagettfbbox($size, 0, $font, $text);
        if (is_array($box)) return abs((int)$box[2] - (int)$box[0]);
    }
    return strlen(rs_image_builtin_text($text)) * imagefontwidth(5);
}

/** @return string[] */
function rs_carta_wrap(string $text, int $maxWidth, ?string $font, float $size): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') return [''];

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && rs_carta_measure($candidate, $font, $size) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') $lines[] = $line;

    return $lines !== [] ? $lines : [''];
}

function rs_carta_text(
    GdImage $image,
    int $x,
    int $y,
    string $text,
    int $color,
    ?string $font,
    float $size,
    bool $bold = false
): void {
    if ($bold) {
        rs_image_draw_text_bold($image, $x, $y, $text, $color, $font, $size, 5);
    } else {
        rs_image_draw_text($image, $x, $y, $text, $color, $font, $size, 5);
    }
}

function rs_carta_center(
    GdImage $image,
    int $centerX,
    int $y,
    string $text,
    int $color,
    ?string $font,
    float $size,
    bool $bold = false
): void {
    $useFont = $bold ? (rs_image_bold_font_path() ?: $font) : $font;
    $width = rs_carta_measure($text, $useFont, $size);
    rs_carta_text($image, (int)round($centerX - ($width / 2)), $y, $text, $color, $font, $size, $bold);
}

function rs_carta_check(GdImage $image, int $x, int $y, int $color): void
{
    imagesetthickness($image, 3);
    imageline($image, $x, $y - 5, $x + 7, $y + 3, $color);
    imageline($image, $x + 7, $y + 3, $x + 19, $y - 12, $color);
    imagesetthickness($image, 1);
}

function rs_carta_service_date(array $payload): DateTimeImmutable
{
    $timezone = new DateTimeZone('America/Monterrey');
    $candidates = [
        (string)($payload['inicio'] ?? ''),
        (string)($payload['fechaServicio'] ?? ''),
        (string)($payload['inicioCrematorio'] ?? ''),
        (string)($payload['fechaHoraInhumacion'] ?? ''),
        (string)($payload['fechaCompra'] ?? ''),
    ];

    $formats = [
        'Y-m-d\\TH:i',
        'Y-m-d\\TH:i:s',
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'd/m/Y H:i',
        'd/m/Y H:i:s',
        'd/m/Y',
        'Y-m-d',
    ];

    foreach ($candidates as $raw) {
        $raw = trim($raw);
        if ($raw === '') continue;

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $raw, $timezone);
            if ($parsed instanceof DateTimeImmutable) return $parsed;
        }

        try {
            return new DateTimeImmutable($raw, $timezone);
        } catch (Throwable) {
            // Probar el siguiente candidato.
        }
    }

    return new DateTimeImmutable('now', $timezone);
}

function rs_carta_spanish_date(?DateTimeImmutable $date = null): string
{
    $date ??= new DateTimeImmutable('now', new DateTimeZone('America/Monterrey'));
    $months = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];
    return 'MONTERREY, N. L. A ' . $date->format('j') . ' DE ' . $months[(int)$date->format('n')] . ' ' . $date->format('Y');
}

/**
 * Genera la carta como PNG Carta a 150 dpi.
 *
 * @return array{pngName:string,png:string,pdfName:string,pdf:string,package:string,benefits:string[]}
 */
function rs_generate_service_letter(array $payload): array
{
    rs_image_require_gd();

    $width = 1275;
    $height = 1650;
    $image = imagecreatetruecolor($width, $height);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('No fue posible crear la carta.');
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 16, 16, 16);
    $gray = imagecolorallocate($image, 80, 80, 80);
    imagefilledrectangle($image, 0, 0, $width, $height, $white);

    $font = rs_image_font_path();
    $bold = rs_image_bold_font_path() ?: $font;
    if ($font === null) {
        imagedestroy($image);
        throw new RuntimeException('No fue posible resolver la tipografía para la carta.');
    }

    $centerX = (int)round($width / 2);
    $fallecido = mb_strtoupper(trim((string)($payload['fallecido'] ?? '')), 'UTF-8');
    $titular = mb_strtoupper(trim((string)($payload['titular'] ?? '')), 'UTF-8');
    $reference = mb_strtoupper(rs_carta_reference($payload), 'UTF-8');
    $destination = rs_carta_destination($payload);
    $benefits = rs_carta_benefits($payload);
    $package = rs_carta_package_key($payload);

    if ($fallecido === '') $fallecido = 'NOMBRE DEL FALLECIDO';
    if ($titular === '') $titular = 'TITULAR / RESPONSABLE';

    // Encabezado, replicando jerarquía y espacios de la constancia vigente.
    rs_carta_center($image, $centerX, 298, 'CONSTANCIA DE PRESTACION DE SERVICIO FUNERAL', $black, $font, 30.0, true);
    rs_carta_center($image, $centerX, 395, 'EN LA PRESENTE CONSTANCIA SE DETALLA LA PRESTACIÓN DE UN SERVICIO FUNERAL PARA', $black, $font, 18.0, false);

    $nameLines = rs_carta_wrap($fallecido, 760, $bold, 31.0);
    $nameY = 505;
    foreach (array_slice($nameLines, 0, 2) as $line) {
        rs_carta_center($image, $centerX, $nameY, $line, $black, $font, 31.0, true);
        $lineWidth = rs_carta_measure($line, $bold, 31.0);
        imageline($image, (int)round($centerX - $lineWidth / 2), $nameY + 8, (int)round($centerX + $lineWidth / 2), $nameY + 8, $black);
        $nameY += 42;
    }

    $infoY = max(545, $nameY + 5);
    rs_carta_text($image, 345, $infoY, 'DE ACUERDO A EL PLAN ADQUIRIDO:', $black, $font, 20.0, false);
    rs_carta_text($image, 750, $infoY, $reference, $black, $font, 20.0, true);
    rs_carta_text($image, 345, $infoY + 38, 'POR:', $black, $font, 20.0, false);
    rs_carta_center($image, $centerX, $infoY + 38, $titular, $black, $font, 20.0, true);

    $destY = $infoY + 88;
    rs_carta_center($image, $centerX, $destY, 'DESTINO:  ' . $destination, $black, $font, 18.5, true);
    rs_carta_center($image, $centerX, $destY + 36, 'DESCRIPCIÓN DE BENEFICIOS', $black, $font, 20.0, true);
    rs_carta_center($image, $centerX, $destY + 66, str_repeat('*', 82), $black, $font, 13.0, false);

    $listX = 350;
    $textX = 385;
    $y = $destY + 105;
    $availableBottom = 1265;
    $maxListHeight = $availableBottom - $y;

    // Ajuste automático para paquetes con más beneficios.
    $fontSize = count($benefits) >= 17 ? 18.5 : (count($benefits) >= 15 ? 19.5 : 21.0);
    $lineHeight = count($benefits) >= 17 ? 34 : (count($benefits) >= 15 ? 36 : 39);
    $maxWidth = 760;

    foreach ($benefits as $benefit) {
        $lines = rs_carta_wrap($benefit, $maxWidth, $font, $fontSize);
        $needed = max($lineHeight, count($lines) * $lineHeight);

        if (($y - ($destY + 125)) + $needed > $maxListHeight) {
            // Último recurso: comprime un poco sin invadir las firmas.
            $lineHeight = max(29, $lineHeight - 3);
            $fontSize = max(16.0, $fontSize - 1.0);
            $lines = rs_carta_wrap($benefit, $maxWidth, $font, $fontSize);
        }

        rs_carta_check($image, $listX, $y - 5, $black);
        $lineY = $y;
        foreach ($lines as $line) {
            rs_carta_text($image, $textX, $lineY, $line, $black, $font, $fontSize, false);
            $lineY += $lineHeight;
        }
        $y += max($lineHeight, count($lines) * $lineHeight);
    }

    // Firmas.
    $sigY = 1415;
    imageline($image, 230, $sigY, 565, $sigY, $gray);
    imageline($image, 800, $sigY, 1145, $sigY, $gray);
    rs_carta_center($image, 398, $sigY + 37, 'JARDINES DE JUAN PABLO', $black, $font, 17.0, false);

    $signatureLines = rs_carta_wrap($titular, 330, $font, 16.0);
    $sigTextY = $sigY + 37;
    foreach (array_slice($signatureLines, 0, 2) as $line) {
        rs_carta_center($image, 972, $sigTextY, $line, $black, $font, 16.0, false);
        $sigTextY += 22;
    }

    $serviceDate = rs_carta_service_date($payload);
    rs_carta_center($image, $centerX, 1558, rs_carta_spanish_date($serviceDate), $black, $font, 16.0, false);

    ob_start();
    imagepng($image, null, 6);
    $png = (string)ob_get_clean();

    ob_start();
    imagejpeg($image, null, 92);
    $jpeg = (string)ob_get_clean();

    imagedestroy($image);

    if ($png === '' || $jpeg === '') {
        throw new RuntimeException('No fue posible codificar la carta.');
    }

    $id = preg_replace('/[^0-9A-Za-z_-]+/', '', trim((string)($payload['itemId'] ?? $payload['numeroServicio'] ?? 'PRUEBA')));
    if ($id === '') $id = 'PRUEBA';

    $pdf = rs_carta_jpeg_to_pdf($jpeg, $width, $height);

    return [
        'pngName' => 'Carta_Servicio_Otorgado_' . $id . '.png',
        'png' => $png,
        'pdfName' => 'Carta_Servicio_Otorgado_' . $id . '.pdf',
        'pdf' => $pdf,
        'package' => $package,
        'benefits' => $benefits,
    ];
}

function rs_carta_jpeg_to_pdf(string $jpeg, int $pixelWidth, int $pixelHeight): string
{
    $objects = [];

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
        . '/Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>';

    $objects[4] = '<< /Type /XObject /Subtype /Image /Width ' . $pixelWidth
        . ' /Height ' . $pixelHeight
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
        . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";

    $content = "q\n612 0 0 792 0 0 cm\n/Im0 Do\nQ\n";
    $objects[5] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];

    for ($i = 1; $i <= 5; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}
