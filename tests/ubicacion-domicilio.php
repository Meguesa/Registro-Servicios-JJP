<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$js=file_get_contents($root.'/assets/js/capillas.js');
$php=file_get_contents($root.'/api/registro-servicio.php');
$html=file_get_contents($root.'/capillas.php');
function checkFile(string $haystack,string $needle): void { if (!str_contains($haystack,$needle)) throw new RuntimeException('Missing expected rule: '.$needle); }
checkFile($js,'const UBICACIONES=');
checkFile($js,'"Domicilio"');
checkFile($js,'const domicilio=ubicacionServicio?.value==="Domicilio";');
checkFile($js,'toggle("wrapSala",!sinVelacion&&!domicilio)');
checkFile($js,'toggle("wrapTiempo",!sinVelacion&&!domicilio)');
checkFile($js,'sala:(sinVelacion||domicilio)?"":');
checkFile($js,'tiempoCapillas:(sinVelacion||domicilio)?"":');
if (str_contains($php,"\$service !== 'Inhumación'")) throw new RuntimeException('Domicilio sigue restringido a Inhumacion');
checkFile($php,"['inicio','termino']");
checkFile($php,"\$payload['sala'] = ''");
checkFile($php,"\$payload['tiempoCapillas'] = ''");
checkFile($php,"getbyinternalnameortitle('field_39')");
checkFile($html,'id="domicilioNota"');
echo "OK: Domicilio permitido para todos los servicios, sin sala ni tiempo de capillas\n";
