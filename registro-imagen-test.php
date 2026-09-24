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
    'numeroReferencia' => '0672',
    'numeroServicio' => '0672',
    'referencia' => 'VI - ATMADBA - 4274-AF',
    'servicio' => 'Inhumación',
    'ubicacion' => 'Apodaca',
    'sala' => 'Sala 2',
    'inicio' => '2026-09-24T12:00',
    'termino' => '2026-09-25T12:00',
    'llevaExequia' => false,
    'horaExequia' => '',
    'prevision' => 'Uso Inmediato',
    'tipoAtaud' => 'Ataud Madera Basico',
    'titular' => 'ROSA ISELA SANCHEZ TOTO',
    'fallecido' => 'MIGUEL ANGEL SANCHEZ TOTO',
    'fechaNacimiento' => '1981-03-28',
    'fechaDefuncion' => '2026-09-23T10:30',
    'edad' => '45',
    'sexo' => 'Masculino',
    'destinoFinal' => 'Jardines de Juan Pablo',
    'embalsamador' => 'No Aplica',
    'rescate1' => 'ELIAS',
    'rescate2' => 'RAFAEL',
    'ubicacionRescate' => 'CLINICA 33',
    'motivo' => 'ENCEFALOPATIA HEPATICA',
    'fechaHoraInhumacion' => '2026-09-25T23:00',
    'fechaCompra' => '2026-09-20',
    'personalVenta' => 'MARTHA MARTINEZ',
    'precioVenta' => 39280,
    'serviciosExtra' => ['Misa y Coro'],
    'extraAmounts' => ['Misa y Coro' => 2100],
    'ventaTotal' => 41380,
];

$type = strtolower(trim((string)($_GET['tipo'] ?? '')));
if (in_array($type, ['servicio', 'obituario', 'venta'], true)) {
    try {
        $images = rs_generate_service_information_images($sample);
        $map = [
            'servicio' => ['name' => $images['serviceName'], 'png' => $images['servicePng']],
            'obituario' => ['name' => $images['obitName'], 'png' => $images['obitPng']],
            'venta' => ['name' => $images['saleName'], 'png' => $images['salePng']],
        ];

        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $map[$type]['name'] . '"');
        header('Content-Length: ' . strlen($map[$type]['png']));
        echo $map[$type]['png'];
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
    'purpose' => 'Fase 2 aislada - generacion de Informacion_Servicio.png, Obituario.png e Informacion_Venta.png.',
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
        'obitImage' => 'registro-imagen-test.php?tipo=obituario',
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
