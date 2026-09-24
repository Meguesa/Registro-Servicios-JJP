<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$bootstrap = $root . '/includes/bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
    if (function_exists('portal_require_authentication')) {
        portal_require_authentication();
    }
}

$gd = extension_loaded('gd');
$imagick = extension_loaded('imagick') && class_exists('Imagick');
$gdInfo = $gd && function_exists('gd_info') ? gd_info() : [];

$fontCandidates = [
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
];
$font = null;
foreach ($fontCandidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $font = $candidate;
        break;
    }
}

echo json_encode([
    'ok' => true,
    'purpose' => 'Diagnostico aislado para Fase 2 - generacion de tablas PNG/JPG.',
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
    ],
    'php' => PHP_VERSION,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
