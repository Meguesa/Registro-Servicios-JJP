<?php
declare(strict_types=1);

header('Cache-Control: no-store');

$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$bootstrap = $root . '/includes/bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
    if (function_exists('portal_require_authentication')) {
        portal_require_authentication();
    }
}

require_once __DIR__ . '/registro-imagenes.php';

$sample = [
    'numeroReferencia' => '0000',
    'referencia' => 'VI - ATMADBA - 1234',
    'servicio' => 'Inhumacion',
    'ubicacion' => 'Churubusco',
    'sala' => 'Sala 1',
    'inicio' => '2026-09-25T10:00',
    'termino' => '2026-09-25T22:00',
    'llevaExequia' => true,
    'horaExequia' => '2026-09-25T14:00',
    'prevision' => 'Uso Inmediato',
    'tipoAtaud' => 'Ataud Madera Basico',
    'titular' => 'PRUEBA TITULAR',
    'fallecido' => 'PRUEBA FALLECIDO',
    'fechaNacimiento' => '2000-09-01',
    'fechaDefuncion' => '2026-09-23T10:30',
    'edad' => '26',
    'sexo' => 'Masculino',
    'destinoFinal' => 'Panteon Jardines de Juan Pablo',
    'embalsamador' => 'PRUEBA EMBALSAMADOR',
    'rescate1' => 'PRUEBA RESCATE 1',
    'rescate2' => 'PRUEBA RESCATE 2',
    'ubicacionRescate' => 'DOMICILIO',
    'motivo' => 'INFARTO',
    'fechaHoraInhumacion' => '2026-09-25T23:00',
    'fechaCompra' => '2026-09-20',
    'personalVenta' => 'PRUEBA ASESOR',
    'precioVenta' => 32500,
    'serviciosExtra' => ['Misa y Coro', 'Flores'],
    'extraAmounts' => ['Misa y Coro' => 2500, 'Flores' => 1800],
    'ventaTotal' => 36800,
];

$type = strtolower(trim((string)($_GET['tipo'] ?? '')));

if ($type === 'servicio' || $type === 'venta') {
    try {
        $images = rs_generate_service_information_images($sample);

        $isService = $type === 'servicio';
        $name = $isService ? $images['serviceName'] : $images['saleName'];
        $png = $isService ? $images['servicePng'] : $images['salePng'];

        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

$gd = extension_loaded('gd');
$imagick = extension_loaded('imagick') && class_exists('Imagick');
$gdInfo = $gd && function_exists('gd_info') ? gd_info() : [];
$font = rs_image_font_path();

echo json_encode([
    'ok' => true,
    'purpose' => 'Fase 2 aislada - generacion de Informacion_Servicio.png e Informacion_Venta.png.',
    'gd' => [
        'available' => $gd,
        'freetype' => (bool)($gdInfo['FreeType Support'] ?? false),
        'png' => (bool)($gdInfo['PNG Support'] ?? false),
        'jpeg' => (bool)($gdInfo['JPEG Support'] ?? false),
    ],
    'imagick' => [
        'available' => $imagick,
    ],
    'truetypeFont' => [
        'available' => $font !== null,
        'path' => $font,
        'fallback' => $font === null ? 'GD built-in bitmap font' : null,
    ],
    'tests' => [
        'serviceImage' => 'registro-imagen-test.php?tipo=servicio',
        'saleImage' => 'registro-imagen-test.php?tipo=venta',
    ],
    'integration' => [
        'email' => false,
        'sharepoint' => false,
        'calendar' => false,
        'tellmebye' => false,
    ],
    'php' => PHP_VERSION,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
