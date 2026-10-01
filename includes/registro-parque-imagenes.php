<?php
declare(strict_types=1);

require_once __DIR__ . '/registro-imagenes.php';

function rp_image_location(array $payload): string
{
    $seccion=rs_image_clean_text($payload['seccion']??'');
    $manzana=rs_image_clean_text($payload['manzana']??'');
    $lote=rs_image_clean_text($payload['numLoteNicho']??'');
    $service=rs_image_lower((string)($payload['servicio']??''));
    $serviceNorm=preg_replace('/[^a-z0-9]+/','',$service)??'';
    $prefix=$serviceNorm==='totalservicecomplemento'?'TSC':($serviceNorm==='totalservice'?'TS':'');

    return implode(' - ',array_values(array_filter([
        $prefix,
        $seccion,
        $lote,
        $manzana,
    ],static fn(string $v):bool=>$v!=='')));
}

function rp_image_service_rows(array $payload): array
{
    return [
        ['label'=>'Fecha inicio','value'=>rs_image_date((string)($payload['fechaHoraInicio']??''),false)],
        ['label'=>'Hora inicio','value'=>rs_image_time((string)($payload['fechaHoraInicio']??''))],
        ['label'=>'Fecha fin','value'=>rs_image_date((string)($payload['fechaHoraFin']??''),false)],
        ['label'=>'Hora fin','value'=>rs_image_time((string)($payload['fechaHoraFin']??''))],
        ['label'=>'Previsión / Uso inmediato','value'=>rs_image_clean_text($payload['previsionUsoInmediato']??'')],
        ['label'=>'Tipo de servicio','value'=>rs_image_clean_text($payload['tipoServicio']??'')],
        ['label'=>'Servicio','value'=>rs_image_clean_text($payload['servicio']??'')],
        ['label'=>'Asistente funerario','value'=>rs_image_clean_text($payload['asistenteFunerarioTexto']??'')],
        ['label'=>'Número de contrato','value'=>rs_image_clean_text($payload['numeroContrato']??'')],
    ];
}

function rp_image_property_rows(array $payload): array
{
    $hasRelocation=!empty($payload['requiereReubicacion']);
    $locationLabel=$hasRelocation?'Ubicación anterior':'Ubicación';

    $rows=[
        ['label'=>'Sección','value'=>rs_image_clean_text($payload['seccion']??'')],
        ['label'=>'Manzana','value'=>rs_image_clean_text($payload['manzana']??'')],
        ['label'=>'Lote / Nicho','value'=>rs_image_clean_text($payload['numLoteNicho']??'')],
        ['label'=>$locationLabel,'value'=>rp_image_location($payload)],
        ['label'=>'Destape','value'=>rs_image_clean_text($payload['destape']??'')],
        ['label'=>'Tipo de placa','value'=>rs_image_clean_text($payload['tipoPlaca']??'')],
        ['label'=>'Nombre de familia','value'=>rs_image_clean_text($payload['nombreFamilia']??'')],
        ['label'=>'Requiere placa de urna adicional','value'=>!empty($payload['requiereCambioUrna'])?'SI':'NO'],
    ];

    if($hasRelocation){
        $rows[]=['label'=>'Requiere reubicación','value'=>'SI'];
        $rows[]=['label'=>'Ubicación nueva','value'=>rs_image_clean_text($payload['ubicacionNueva']??'')];

        $reason=rs_image_clean_text($payload['motivoReubicacion']??'');
        if($reason!==''){
            $rows[]=['label'=>'Motivo de reubicación','value'=>$reason];
        }
    }

    $frase=rs_image_clean_text($payload['frase']??'');
    if($frase!==''){
        $rows[]=['label'=>'Frase','value'=>$frase];
    }
    return $rows;
}

function rp_image_person_rows(array $payload): array
{
    $rows=[
        ['label'=>'Titular','value'=>rs_image_clean_text($payload['titular']??'')],
        ['label'=>'Parentesco del titular','value'=>rs_image_clean_text($payload['parentescoTitular']??'')],
        ['label'=>'Fallecido(a)','value'=>rs_image_clean_text($payload['fallecido']??'')],
        ['label'=>'Fecha de nacimiento','value'=>rs_image_date((string)($payload['fechaNacimiento']??''),false)],
        ['label'=>'Fecha de defunción','value'=>rs_image_date((string)($payload['fechaDefuncion']??''),false)],
        ['label'=>'Estatus de liquidación','value'=>rs_image_clean_text($payload['estatusLiquidacion']??'')],
    ];

    $obs=rs_image_clean_text($payload['observaciones']??'');
    if($obs!==''){
        $rows[]=['label'=>'Observaciones','value'=>$obs];
    }
    return $rows;
}

/**
 * @return array<int,array{name:string,contentType:string,bytes:string}>
 */
function rp_generate_information_images(array $payload): array
{
    $subtitle=rp_image_location($payload);
    if($subtitle==='')$subtitle='Servicio Parque';

    return [
        [
            'name'=>'Informacion_Servicio.png',
            'contentType'=>'image/png',
            'bytes'=>rs_image_render_table('INFORMACIÓN DEL SERVICIO',$subtitle,rp_image_service_rows($payload)),
        ],
        [
            'name'=>'Informacion_Propiedad.png',
            'contentType'=>'image/png',
            'bytes'=>rs_image_render_table('INFORMACIÓN DE PROPIEDAD',$subtitle,rp_image_property_rows($payload)),
        ],
        [
            'name'=>'Informacion_Fallecido.png',
            'contentType'=>'image/png',
            'bytes'=>rs_image_render_table('INFORMACIÓN DEL FALLECIDO',$subtitle,rp_image_person_rows($payload)),
        ],
    ];
}
