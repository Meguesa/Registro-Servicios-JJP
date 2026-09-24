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

    if ($formato === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $plate['pdfName'] . '"');
        header('Content-Length: ' . strlen($plate['pdf']));
        echo $plate['pdf'];
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'purpose' => 'Fase 3 aislada - generacion de placa de urna para servicios de cremacion.',
        'templateReference' => 'Eventos Capillas / Plantillas / plantilla_placa_urna.pdf',
        'tests' => [
            'png' => 'registro-placa-test.php?formato=png',
            'pdf' => 'registro-placa-test.php?formato=pdf',
        ],
        'integration' => [
            'registroServicio' => false,
            'email' => false,
            'sharepoint' => false,
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
