<?php
declare(strict_types=1);

require_once __DIR__ . '/registro-parque-imagenes.php';
require_once __DIR__ . '/registro-carta.php';

function rp_doc_norm(string $value): string
{
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($value));
    if(is_string($ascii)&&$ascii!=='')$value=$ascii;
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/','', $value)??'');
}

function rp_doc_location(array $payload): string
{
    return rp_image_location($payload);
}

function rp_doc_flower_arrangement(array $payload): string
{
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    if(str_starts_with($section,'ORO')) return '34 Rosas Blancas y 2 arreglos de exterior';
    if($section==='PLATA') return '18 Rosas Blancas y 2 arreglos de exterior';
    if($section==='BRONCE') return '12 Rosas Blancas y 2 arreglos de exterior';
    if(in_array($section,['PLATINO','SJV','SMV','SPV','SPN','PLN'],true)){
        return '40 Rosas Blancas y 2 arreglos de exterior';
    }
    return '40 Rosas Blancas y 2 arreglos de exterior';
}

/**
 * @return array<int,array{name:string,contentType:string,bytes:string}>
 */
function rp_generate_operational_tables(array $payload): array
{
    $attachments=[];
    $service=trim((string)($payload['servicio']??''));
    $typePlate=rp_doc_norm((string)($payload['tipoPlaca']??''));

    if(rp_doc_norm($service)==='totalservice'){
        $attachments[]=[
            'name'=>'Flores.png',
            'contentType'=>'image/png',
            'bytes'=>rs_image_render_table(
                'FLORES',
                rp_doc_location($payload),
                [
                    ['label'=>'TIPO DE SERVICIO','value'=>$service],
                    ['label'=>'REFERENCIA','value'=>rp_doc_location($payload)],
                    ['label'=>'FECHA Y HORA INICIO','value'=>rs_image_date((string)($payload['fechaHoraInicio']??''),true)],
                    ['label'=>'TIPO DE ARREGLO','value'=>rp_doc_flower_arrangement($payload)],
                    ['label'=>'NOMBRE DE QUIEN SOLICITA','value'=>rs_image_clean_text($payload['asistenteFunerarioTexto']??'')],
                ]
            ),
        ];
    }

    if($typePlate==='granito'){
        $phrase=rs_image_clean_text($payload['frase']??'');
        if($phrase==='')$phrase='NO APLICA';
        $attachments[]=[
            'name'=>'Placa_Granito.png',
            'contentType'=>'image/png',
            'bytes'=>rs_image_render_table(
                'PLACA GRANITO',
                rp_doc_location($payload),
                [
                    ['label'=>'FALLECIDO','value'=>rs_image_clean_text($payload['fallecido']??'')],
                    ['label'=>'FECHA NACIMIENTO','value'=>rs_image_date((string)($payload['fechaNacimiento']??''),false)],
                    ['label'=>'FECHA DE DEFUNCIÓN','value'=>rs_image_date((string)($payload['fechaDefuncion']??''),false)],
                    ['label'=>'UBICACIÓN','value'=>rp_doc_location($payload)],
                    ['label'=>'No. GRABADO','value'=>rs_image_clean_text($payload['destape']??'')],
                    ['label'=>'FRASE','value'=>$phrase],
                ]
            ),
        ];
    }

    return $attachments;
}

function rp_doc_paragraph(
    GdImage $image,
    string $text,
    int $x,
    int $y,
    int $maxWidth,
    int $color,
    ?string $font,
    float $size=20.0,
    int $lineHeight=34
): int {
    $lines=rs_carta_wrap($text,$maxWidth,$font,$size);
    foreach($lines as $line){
        rs_carta_text($image,$x,$y,$line,$color,$font,$size,false);
        $y+=$lineHeight;
    }
    return $y;
}

function rp_doc_letter_pdf(array $payload,string $kind): array
{
    rs_image_require_gd();
    $width=1275;
    $height=1650;
    $image=imagecreatetruecolor($width,$height);
    if(!$image instanceof GdImage)throw new RuntimeException('No fue posible crear la carta de Parque.');

    $white=imagecolorallocate($image,255,255,255);
    $black=imagecolorallocate($image,20,20,20);
    $gray=imagecolorallocate($image,90,90,90);
    imagefilledrectangle($image,0,0,$width,$height,$white);

    $font=rs_image_font_path();
    if($font===null){
        imagedestroy($image);
        throw new RuntimeException('No fue posible resolver la tipografia para la carta de Parque.');
    }

    $cx=(int)round($width/2);
    $location=mb_strtoupper(rp_doc_location($payload),'UTF-8');
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    $contract=mb_strtoupper(trim((string)($payload['numeroContrato']??'')),'UTF-8');
    $deceased=mb_strtoupper(trim((string)($payload['fallecido']??'')),'UTF-8');
    $holder=mb_strtoupper(trim((string)($payload['titular']??'')),'UTF-8');
    $relationship=mb_strtoupper(trim((string)($payload['parentescoTitular']??'')),'UTF-8');
    $newLocation=mb_strtoupper(trim((string)($payload['ubicacionNueva']??'')),'UTF-8');

    $datePayload=['fechaServicio'=>(string)($payload['fechaHoraInicio']??'')];
    $date=mb_strtoupper(rs_carta_spanish_date(rs_carta_service_date($datePayload)),'UTF-8');
    rs_carta_center($image,$cx,115,$date,$black,$font,20.0,false);

    $title='';
    $filename='';
    $y=210;

    if($kind==='retiro'){
        $title='RETIRO DE CENIZAS';
        $filename='Carta_Retiro_Cenizas.pdf';
        rs_carta_center($image,$cx,$y,'A QUIEN CORRESPONDA',$black,$font,25.0,true);
        $y+=70;
        rs_carta_center($image,$cx,$y,'ASUNTO: RETIRO DE CENIZAS',$black,$font,22.0,true);
        $y+=80;
        $y=rp_doc_paragraph($image,
            'POR MEDIO DE LA PRESENTE SOLICITAMOS FORMALMENTE EL RETIRO DE CENIZAS DEL NICHO '.$location
            .', UBICADO EN EL SECTOR '.$section.', Y NUMERO DE CONTRATO '.$contract
            .' DE LA PERSONA QUE EN VIDA LLEVO EL NOMBRE DE '.$deceased.'.',
            145,$y,985,$black,$font);
        $y+=30;
        $y=rp_doc_paragraph($image,
            'EL RETIRO SE REALIZA DE TOTAL CONFORMIDAD, CUBRIENDO LOS COSTOS CORRESPONDIENTES Y CEDIENDO A MEGUESA, S.A. DE C.V. EL DERECHO DE USO DEL NICHO EN LOS TERMINOS DEL CONTRATO.',
            145,$y,985,$black,$font);
        $y+=35;
        $y=rp_doc_paragraph($image,
            'RECIBO DE CONFORMIDAD LAS CENIZAS DE MI '.$relationship.' Y AGRADEZCO LAS ATENCIONES BRINDADAS. ESTA CARTA ES DE CARACTER IRREVOCABLE.',
            145,$y,985,$black,$font);
    }elseif($kind==='exhumacion'){
        $title='EXHUMACION DE RESTOS';
        $filename='Carta_Exhumacion.pdf';
        rs_carta_center($image,$cx,$y,'A QUIEN CORRESPONDA',$black,$font,25.0,true);
        $y+=70;
        rs_carta_center($image,$cx,$y,'ASUNTO: EXHUMACION DE RESTOS',$black,$font,22.0,true);
        $y+=80;
        $y=rp_doc_paragraph($image,
            'POR MEDIO DE LA PRESENTE, EN RELACION CON EL CONTRATO NO. '.$contract
            .', SOLICITO Y AUTORIZO LA EXHUMACION DE LOS RESTOS DE '.$deceased
            .', QUIEN DESCANSA EN '.$location.', SECTOR '.$section.'.',
            145,$y,985,$black,$font);
        $y+=30;
        $y=rp_doc_paragraph($image,
            'LA EXHUMACION SE REALIZA DE TOTAL CONFORMIDAD, CUBRIENDO LOS COSTOS DE EXHUMACION Y TRASLADO, ASI COMO LOS GASTOS ADMINISTRATIVOS QUE CORRESPONDAN.',
            145,$y,985,$black,$font);
        $y+=35;
        $y=rp_doc_paragraph($image,
            'RECIBO DE CONFORMIDAD LOS RESTOS DE MI '.$relationship.' Y AGRADEZCO LAS ATENCIONES BRINDADAS. ESTA CARTA ES DE CARACTER IRREVOCABLE.',
            145,$y,985,$black,$font);
    }else{
        $title='REUBICACION DE LOTE';
        $filename='Carta_Reubicacion.pdf';
        rs_carta_center($image,$cx,$y,'ATENCION: '.$holder,$black,$font,24.0,true);
        $y+=90;
        $y=rp_doc_paragraph($image,
            'POR MEDIO DE LA PRESENTE LE INFORMAMOS QUE, DEBIDO A LA NECESIDAD DE USO SOBRE SU LOTE Y DE ACUERDO CON LAS CONDICIONES DEL CONTRATO, ES NECESARIO REALIZAR UNA REUBICACION PARA PODER LLEVAR A CABO EL SERVICIO.',
            145,$y,985,$black,$font);
        $y+=35;
        $y=rp_doc_paragraph($image,
            'UBICACION ORIGINAL: '.$location.'. NUEVA UBICACION: '.($newLocation!==''?$newLocation:'POR CONFIRMAR').'. FALLECIDO(A): '.$deceased.'.',
            145,$y,985,$black,$font);
        $y+=35;
        $y=rp_doc_paragraph($image,
            'LA REASIGNACION SE REALIZARA SIN CARGO ADICIONAL POR ESTE CONCEPTO. SIN MAS POR EL MOMENTO, QUEDAMOS A SUS ORDENES.',
            145,$y,985,$black,$font);
    }

    rs_carta_center($image,$cx,1425,$title,$gray,$font,18.0,true);
    imageline($image,190,1490,520,1490,$gray);
    imageline($image,755,1490,1085,1490,$gray);
    rs_carta_center($image,355,1530,'NOMBRE Y FIRMA',$black,$font,16.0,false);
    rs_carta_center($image,920,1530,'NOMBRE Y FIRMA',$black,$font,16.0,false);

    ob_start();
    imagejpeg($image,null,92);
    $jpeg=(string)ob_get_clean();
    imagedestroy($image);
    if($jpeg==='')throw new RuntimeException('No fue posible codificar la carta de Parque.');

    return [
        'name'=>$filename,
        'contentType'=>'application/pdf',
        'bytes'=>rs_carta_jpeg_to_pdf($jpeg,$width,$height),
    ];
}

/**
 * Reglas del flujo actual de Parque:
 * - Reubicacion: genera Carta Reubicacion.
 * - No liquidado + SPN/PLN: Carta Retiro de Cenizas.
 * - No liquidado + otra seccion: Carta Exhumacion.
 *
 * @return array<int,array{name:string,contentType:string,bytes:string}>
 */
function rp_generate_letter_attachments(array $payload): array
{
    $attachments=[];
    $liquidation=rp_doc_norm((string)($payload['estatusLiquidacion']??''));
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');

    if(!empty($payload['requiereReubicacion'])){
        $attachments[]=rp_doc_letter_pdf($payload,'reubicacion');
    }

    if($liquidation==='noliquidado'){
        if(in_array($section,['SPN','PLN'],true)){
            $attachments[]=rp_doc_letter_pdf($payload,'retiro');
        }else{
            $attachments[]=rp_doc_letter_pdf($payload,'exhumacion');
        }
    }

    return $attachments;
}
