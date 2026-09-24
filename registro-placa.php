<?php

// Fondo fijo EXACTO de la placa
$backgroundPath = __DIR__ . '/assets/FONDO FIJO PLACA URNA.png';

// Fuente
$fontTitle = __DIR__ . '/assets/fonts/times.ttf';      // o la que estés usando
$fontDate  = __DIR__ . '/assets/fonts/calibri.ttf';    // o Arial/DejaVuSans si no tienes calibri

$img = imagecreatefrompng($backgroundPath);
imagesavealpha($img, true);

// Color del texto
$textColor = imagecolorallocate($img, 70, 70, 70);

// Datos
$nombre = strtoupper(trim($nombreFallecido));
$fechaNacimiento = trim($fechaNacimiento);
$fechaDefuncion = trim($fechaDefuncion);

// =============================
// AJUSTA ESTAS COORDENADAS
// =============================

// Centro del bloque del nombre
$nameCenterX = 365;

// Baselines de los 3 renglones del nombre
$nameLineYs = [
    82,   // renglón superior
    109,  // renglón central
    136   // renglón inferior
];

// Ancho máximo del nombre
$nameMaxWidth = 300;

// Dibujar nombre con tu lógica de 1/2/3 líneas
rs_draw_name_block(
    $img,
    $nombre,
    $fontTitle,
    26,
    $textColor,
    $nameCenterX,
    $nameLineYs,
    $nameMaxWidth
);

// Fechas
rs_draw_centered_text($img, $fechaNacimiento, $fontDate, 22, $textColor, 150, 180);
rs_draw_centered_text($img, $fechaDefuncion,  $fontDate, 22, $textColor, 355, 180);
