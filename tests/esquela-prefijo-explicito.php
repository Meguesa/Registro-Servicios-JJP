<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/registro-esquela.php';
function eq(string $actual,string $expected,string $name): void { if($actual!==$expected) throw new RuntimeException($name.' expected '.var_export($expected,true).' got '.var_export($actual,true)); }
eq(rs_esquela_prefix(['sexo'=>'Femenino','edad'=>38]), '', 'Sin seleccion: mujer adulta');
eq(rs_esquela_prefix(['sexo'=>'Masculino','edad'=>66]), '', 'Sin seleccion: hombre adulto');
eq(rs_esquela_prefix(['sexo'=>'Femenino','edad'=>4]), '', 'Sin seleccion: menor de edad');
eq(rs_esquela_prefix(['prefijo'=>'','sexo'=>'FEMENINO']), '', 'Vacio');
eq(rs_esquela_prefix(['prefijo'=>'--','sexo'=>'FEMENINO']), '', 'Marcador legacy');
eq(rs_esquela_prefix(['prefijo'=>'Automático','sexo'=>'FEMENINO']), '', 'Automatico legacy');
eq(rs_esquela_prefix(['prefijo'=>'Sin prefijo','sexo'=>'FEMENINO']), '', 'Sin prefijo textual');
eq(rs_esquela_prefix(['prefijo'=>'Sra.']), 'Sra.', 'Seleccion Sra');
eq(rs_esquela_prefix(['prefijo'=>'Sr.']), 'Sr.', 'Seleccion Sr');
eq(rs_esquela_prefix(['prefijo'=>'Srta.']), 'Srta.', 'Seleccion Srta');
eq(rs_esquela_prefix(['prefijo'=>'Joven']), 'Joven', 'Seleccion Joven');
$source=file_get_contents(dirname(__DIR__).'/includes/registro-esquela.php');
if (!str_contains($source, "\$displayName = \$prefix !== '' ? \$prefix . ' ' . \$name : \$name;")) throw new RuntimeException('Nombre debe omitir espacio extra sin prefijo');
echo "OK: prefijos solo cuando son expresamente seleccionados\n";
