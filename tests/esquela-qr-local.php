<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/registro-esquela.php';

if (!extension_loaded('gd')) {
    throw new RuntimeException('La extension GD es obligatoria para generar esquelas.');
}
$url = rs_esquela_qr_url('SONIA JUANA SALGADO BARRIOS');
if (!str_starts_with($url, 'https://tellmebye.com/')) {
    throw new RuntimeException('El QR no tiene la URL de homenaje esperada.');
}
$qr = rs_esquela_fetch_qr($url);
if (!$qr instanceof GdImage) {
    throw new RuntimeException('No fue posible generar QR local con PHP.');
}
if (imagesx($qr) < 100 || imagesy($qr) !== imagesx($qr)) {
    throw new RuntimeException('Dimensiones invalidas para el QR local.');
}
$dark = 0;
$white = 0;
for ($y=0; $y<imagesy($qr); $y+=3) {
    for ($x=0; $x<imagesx($qr); $x+=3) {
        $rgb = imagecolorat($qr,$x,$y) & 0xFFFFFF;
        if ($rgb === 0) $dark++;
        if ($rgb === 0xFFFFFF) $white++;
    }
}
if ($dark < 50 || $white < 50) {
    throw new RuntimeException('QR local sin modulos blancos y negros validos.');
}
imagedestroy($qr);

$canvas=imagecreatetruecolor(150,150);
$whiteColor=imagecolorallocate($canvas,255,255,255);
imagefilledrectangle($canvas,0,0,149,149,$whiteColor);
if (!rs_esquela_draw_qr($canvas,$url,20,20,110)) {
    throw new RuntimeException('No se pudo insertar el QR local en la esquela.');
}
imagedestroy($canvas);
echo "OK: QR JJP generado y dibujado localmente sin HTTP\n";
