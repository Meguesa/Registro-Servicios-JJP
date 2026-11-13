<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/registro-publicado-archivos.php';

function assertSameValue(mixed $actual, mixed $expected, string $label): void {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': resultado inesperado');
    }
}

assertSameValue(pd_files_valid_name('Carta Servicio Otorgado 716.pdf'), true, 'archivo PDF permitido');
assertSameValue(pd_files_valid_name('../config.php'), false, 'ruta relativa');
assertSameValue(pd_files_valid_name('foo/bar.pdf'), false, 'ruta con slash');
assertSameValue(pd_files_valid_name("archivo\n.pdf"), false, 'salto de linea');
assertSameValue(pd_files_valid_name(''), false, 'nombre vacio');
assertSameValue(pd_files_mime('reporte.pdf'), 'application/pdf', 'PDF');
assertSameValue(pd_files_previewable('reporte.docx'), false, 'DOCX solo descarga');
assertSameValue(pd_files_previewable('esquela.jpg'), true, 'JPG vista previa');
assertSameValue(pd_files_photo_name([
    ['name'=>'Placa-716.png'],
    ['name'=>'Esquela_716.jpg'],
    ['name'=>'Imagen_Esquela.jpg'],
]), 'Imagen_Esquela.jpg', 'foto fuente');
assertSameValue(pd_files_photo_name([
    ['name'=>'Placa-716.png'],
    ['name'=>'Carta.pdf'],
]), null, 'sin foto fuente no reconstruir');
// El visor debe priorizar la esquela JJP y no mostrar la cartulina del bot.
$viewer = file_get_contents(dirname(__DIR__) . '/api/publicado-detalle.php');
if (!is_string($viewer)
    || !str_contains($viewer, 'Esquela JJP generada con datos de SharePoint')
    || !str_contains($viewer, "if(\$area==='capillas')")
    || str_contains($viewer, "\$entries[]=['tipo'=>'tellmebye'")) {
    throw new RuntimeException('El visor no prioriza la esquela JJP.');
}
echo "OK: rutas, tipos y fotografia fuente validados\n";
