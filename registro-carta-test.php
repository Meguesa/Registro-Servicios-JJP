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

require_once __DIR__ . '/registro-carta.php';

$cases = [
    'atmetba' => [
        'itemId' => 'PRUEBA-ATMETBA',
        'numeroServicio' => '5493',
        'numeroReferencia' => '0000',
        'referencia' => 'VC - ATMETBA - 5493',
        'servicio' => 'Cremación',
        'tipoAtaud' => 'Ataud Metalico Basico',
        'fallecido' => 'EMMA ALICIA LOPEZ SARMIENTO',
        'titular' => 'GINA YADIRA LOPEZ SARMIENTO',
        'destinoFinal' => 'Crematorio Jardines de Juan Pablo',
        'tiempoCapillas' => '12H',
        'inicio' => '2026-09-25T10:00',
    ],
    'atmadba' => [
        'itemId' => 'PRUEBA-ATMADBA',
        'numeroServicio' => '4274-AF',
        'numeroReferencia' => '0672',
        'referencia' => 'VI - ATMADBA - 4274-AF',
        'servicio' => 'Inhumación',
        'tipoAtaud' => 'Ataud Madera Basico',
        'fallecido' => 'MIGUEL ANGEL SANCHEZ TOTO',
        'titular' => 'ROSA ISELA SANCHEZ TOTO',
        'destinoFinal' => 'Parque de Descanso Jardines de Juan Pablo',
        'tiempoCapillas' => '12H',
        'inicio' => '2026-09-24T12:00',
    ],
    'atmadex' => [
        'itemId' => 'PRUEBA-ATMADEX',
        'numeroServicio' => '5489',
        'numeroReferencia' => '0000',
        'referencia' => 'VC - ATMADEX - 5489',
        'servicio' => 'Cremación',
        'tipoAtaud' => 'Ataud Madera Exclusivo',
        'fallecido' => 'PRUEBA NOMBRE SERVICIO EXCLUSIVO',
        'titular' => 'PRUEBA TITULAR',
        'destinoFinal' => 'Crematorio Jardines de Juan Pablo',
        'tiempoCapillas' => '12H',
        'inicio' => '2026-09-25T13:00',
    ],
    'atmadlx' => [
        'itemId' => 'PRUEBA-ATMADLX',
        'numeroServicio' => '5500',
        'numeroReferencia' => '0000',
        'referencia' => 'VC - ATMADLX - 5500',
        'servicio' => 'Cremación',
        'tipoAtaud' => 'Ataud Madera de Lujo',
        'fallecido' => 'PRUEBA NOMBRE SERVICIO DE LUJO',
        'titular' => 'PRUEBA TITULAR',
        'destinoFinal' => 'Crematorio Jardines de Juan Pablo',
        'tiempoCapillas' => '24H',
        'inicio' => '2026-09-26T10:00',
    ],
    'directa-con' => [
        'itemId' => 'PRUEBA-CD-CON',
        'numeroServicio' => '6001',
        'numeroReferencia' => '0000',
        'referencia' => 'CD - URNABAS - 6001',
        'servicio' => 'Cremación Directa (con velación)',
        'tipoAtaud' => 'Urna Marmol',
        'fallecido' => 'PRUEBA CREMACION DIRECTA CON VELACION',
        'titular' => 'PRUEBA TITULAR',
        'destinoFinal' => 'Crematorio Jardines de Juan Pablo',
        'tiempoCapillas' => '2H',
        'inicio' => '2026-09-27T14:00',
    ],
    'directa-sin' => [
        'itemId' => 'PRUEBA-CD-SIN',
        'numeroServicio' => '6002',
        'numeroReferencia' => '0000',
        'referencia' => 'CD - URNABAS - 6002',
        'servicio' => 'Cremación Directa (sin velación)',
        'tipoAtaud' => 'Urna Marmol',
        'fallecido' => 'PRUEBA CREMACION DIRECTA SIN VELACION',
        'titular' => 'PRUEBA TITULAR',
        'destinoFinal' => 'Crematorio Jardines de Juan Pablo',
        'inicioCrematorio' => '2026-09-28T09:00',
    ],
];

$caseKey = strtolower(trim((string)($_GET['caso'] ?? 'atmetba')));
if (!isset($cases[$caseKey])) $caseKey = 'atmetba';

$formato = strtolower(trim((string)($_GET['formato'] ?? '')));

try {
    $letter = rs_generate_service_letter($cases[$caseKey]);

    if ($formato === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $letter['pdfName'] . '"');
        header('Content-Length: ' . strlen($letter['pdf']));
        echo $letter['pdf'];
        exit;
    }

    if ($formato === 'png') {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $letter['pngName'] . '"');
        header('Content-Length: ' . strlen($letter['png']));
        echo $letter['png'];
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'purpose' => 'Fase 4 aislada - Carta/Constancia de Servicio Otorgado.',
        'case' => $caseKey,
        'package' => $letter['package'],
        'benefits' => $letter['benefits'],
        'tests' => [
            'pdf' => 'registro-carta-test.php?caso=' . rawurlencode($caseKey) . '&formato=pdf',
            'png' => 'registro-carta-test.php?caso=' . rawurlencode($caseKey) . '&formato=png',
        ],
        'availableCases' => array_keys($cases),
        'integration' => [
            'registroServicio' => false,
            'sharepoint' => false,
            'email' => false,
            'powerAutomate' => false,
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
