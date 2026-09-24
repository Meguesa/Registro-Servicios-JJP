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

require_once __DIR__ . '/registro-placa.php';

$sample = [
    'fallecido' => 'PRUEBA FALLECIDO DE REGISTRO DE SERVICIOS',
    'fechaNacimiento' => '1981-03-28',
    'fechaDefuncion' => '2026-09-23T10:30',
];

$formato = strtolower(trim((string)($_GET['formato'] ?? '')));

try {
    $plate = rs_generate_urna_plate($sample);

    if ($formato === 'png') {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $plate['pngName'] . '"');
        header('Content-Length: ' . strlen($plate['png']));
        echo $plate['png'];
        exit;
    }

    if ($formato !== '') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'La placa final solo se genera en PNG.',
            'png' => 'registro-placa-test.php?formato=png',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'purpose' => 'Fase 3 aislada - generacion final de placa de urna exclusivamente en PNG.',
        'templateReference' => 'Eventos Capillas / Plantillas / plantilla_placa_urna.pdf',
        'tests' => [
            'png' => 'registro-placa-test.php?formato=png',
        ],
        'integration' => [
            'registroServicio' => false,
            'sharepointPlacas' => false,
            'email' => false,
            'tellmebye' => false,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
