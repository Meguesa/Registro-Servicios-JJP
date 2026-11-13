<?php
declare(strict_types=1);

// Reproduce la ruta histórica de reconstrucción sin consultar SharePoint.
// Las funciones del correo y del visor deben usar la misma fuente TrueType.
require_once dirname(__DIR__) . '/includes/registro-publicado-archivos.php';
require_once dirname(__DIR__) . '/includes/registro-imagenes.php';
require_once dirname(__DIR__) . '/includes/registro-esquela.php';

$font = rs_esquela_font(false);
$bold = rs_esquela_font(true);
if (!is_string($font) || !is_readable($font)
    || !is_string($bold) || !is_readable($bold)) {
    throw new RuntimeException('No se encontró fuente TrueType normal y negrita.');
}
if (!function_exists('imagettftext')) {
    throw new RuntimeException('GD no permite renderizar caracteres UTF-8.');
}
if (rs_esquela_font(false) !== rs_image_font_path()
    || rs_esquela_font(true) !== rs_image_bold_font_path()) {
    throw new RuntimeException('El visor no usa las mismas fuentes del correo.');
}

$payload = [
    'itemId' => '999',
    'fallecido' => 'MARÍA JOSÉ ÁLVAREZ',
    'edad' => '66',
    'sexo' => 'Femenino',
    'ubicacion' => 'Churubusco',
    'sala' => 'SALA 1',
    'inicio' => '2026-11-11T09:00:00',
    'termino' => '2026-11-12T09:00:00',
    'servicio' => 'INHUMACIÓN',
    'fechaHoraInhumacion' => '2026-11-12T11:00:00',
    'destinoFinal' => 'PARQUE FUNERAL',
];

$result = rs_generate_local_esquela($payload);
if (!isset($result['jpeg']) || !is_string($result['jpeg'])
    || !str_starts_with($result['jpeg'], "\xFF\xD8\xFF")) {
    throw new RuntimeException('No fue posible generar JPG con fuente TrueType.');
}
$info = getimagesizefromstring($result['jpeg']);
if (!is_array($info) || ($info[0] ?? 0) !== 720 || ($info[1] ?? 0) !== 1024) {
    throw new RuntimeException('El tamaño de la esquela JPG es incorrecto.');
}
$caption = rs_esquela_spanish_datetime('2026-11-11T09:00:00');
if (!str_contains($caption, 'Miércoles') || !str_contains($caption, 'Noviembre')) {
    throw new RuntimeException('La fecha en español perdió los acentos.');
}
echo "OK: Esquela del correo reconstruida con fuentes TTF normal/negrita y UTF-8\n";
