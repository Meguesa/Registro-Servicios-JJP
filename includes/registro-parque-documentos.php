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

function rp_doc_first_value(array $payload,array $keys): string
{
    foreach($keys as $key){
        $value=trim((string)($payload[$key]??''));
        if($value!=='')return $value;
    }
    return '';
}

function rp_doc_subject(string $kind): string
{
    return match($kind){
        'retiro'=>'RETIRO DE CENIZAS.',
        'exhumacion'=>'EXHUMACIÓN DE RESTOS.',
        default=>'REUBICACIÓN DE LOTE.',
    };
}

function rp_doc_letter_pdf(array $payload,string $kind): array
{
    rs_image_require_gd();

    // Carta tamaño oficio/letter vertical renderizada a alta resolución.
    // El objetivo es conservar una estructura fija y sustituir solamente
    // la información variable disponible en el registro.
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

    $location=mb_strtoupper(trim(rp_doc_location($payload)),'UTF-8');
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    $contract=mb_strtoupper(trim((string)($payload['numeroContrato']??'')),'UTF-8');
    $deceased=mb_strtoupper(trim((string)($payload['fallecido']??'')),'UTF-8');
    $holder=mb_strtoupper(trim((string)($payload['titular']??'')),'UTF-8');
    $relationship=mb_strtoupper(trim((string)($payload['parentescoTitular']??'')),'UTF-8');
    $newLocation=mb_strtoupper(trim((string)($payload['ubicacionNueva']??'')),'UTF-8');
    $beneficiary=mb_strtoupper(rp_doc_first_value($payload,[
        'avalBeneficiario','beneficiario','aval','nombreBeneficiario','nombreAval'
    ]),'UTF-8');

    $datePayload=['fechaServicio'=>(string)($payload['fechaHoraInicio']??'')];
    $date=mb_strtoupper(rs_carta_spanish_date(rs_carta_service_date($datePayload)),'UTF-8');

    // Encabezado oficial: debe conservarse igual para los tres formatos.
    rs_carta_center($image,$cx,115,$date,$black,$font,20.0,false);
    rs_carta_center($image,$cx,210,'A QUIEN CORRESPONDA',$black,$font,25.0,true);
    rs_carta_center($image,$cx,280,'ASUNTO: '.rp_doc_subject($kind),$black,$font,22.0,true);

    $y=380;
    $filename='Carta_Reubicacion.pdf';

    if($kind==='retiro'){
        $filename='Carta_Retiro_Cenizas.pdf';

        $parts=['POR MEDIO DE LA PRESENTE SOLICITAMOS FORMALMENTE EL RETIRO DE CENIZAS'];
        if($location!=='')$parts[]='DEL NICHO '.$location;
        if($section!=='')$parts[]='UBICADO EN EL SECTOR '.$section;
        if($contract!=='')$parts[]='CON NÚMERO DE CONTRATO '.$contract;
        if($deceased!=='')$parts[]='DE LA PERSONA QUE EN VIDA LLEVÓ EL NOMBRE DE '.$deceased;
        $y=rp_doc_paragraph($image,implode(', ',$parts).'.',145,$y,985,$black,$font);
        $y+=30;

        $y=rp_doc_paragraph($image,
            'RETIRAR LAS CENIZAS SIENDO POR MI CUENTA LOS COSTOS QUE SE GENEREN. ESTO DEBIDO A QUE HUBO INCUMPLIMIENTO DE PAGO DE LA OBLIGACIÓN CONTRAÍDA CON MEGUESA, S.A. DE C.V., LEGÍTIMA PROPIETARIA DEL PARQUE DE DESCANSO JARDINES DE JUAN PABLO.',
            145,$y,985,$black,$font,18.0,31);
        $y+=24;

        $y=rp_doc_paragraph($image,
            'DICHO RETIRO LO REALIZO DE TOTAL CONFORMIDAD, PAGANDO LOS COSTOS CORRESPONDIENTES, SIN PERJUICIO ALGUNO DEMANDABLE PARA MEGUESA, S.A. DE C.V.; POR LO TANTO, DEVUELVO Y CEDO EL DERECHO DE USO A PERPETUIDAD DEL NICHO ADQUIRIDO'.($contract!==''?' SEGÚN EL NÚMERO DE CONTRATO ANTES MENCIONADO':'').'.',
            145,$y,985,$black,$font,18.0,31);
        $y+=24;

        $closing='RECIBO DE CONFORMIDAD LAS CENIZAS';
        if($relationship!=='')$closing.=' DE MI '.$relationship;
        $closing.=' Y AGRADEZCO LAS ATENCIONES BRINDADAS A LA PRESENTE. ESTA CARTA ES DE CARÁCTER IRREVOCABLE.';
        $y=rp_doc_paragraph($image,$closing,145,$y,985,$black,$font,18.0,31);

    }elseif($kind==='exhumacion'){
        $filename='Carta_Exhumacion.pdf';

        $intro='POR MEDIO DE LA PRESENTE';
        if($contract!=='')$intro.=', EN RELACIÓN CON EL CONTRATO NO. '.$contract;
        $intro.=', SOLICITO Y AUTORIZO LA EXHUMACIÓN DE LOS RESTOS';
        if($deceased!=='')$intro.=' DE '.$deceased;
        if($location!=='')$intro.=', QUIEN DESCANSA EN '.$location;
        if($section!=='')$intro.=', SECTOR '.$section;
        $intro.='.';
        $y=rp_doc_paragraph($image,$intro,145,$y,985,$black,$font);
        $y+=30;

        $y=rp_doc_paragraph($image,
            'SIENDO POR MI CUENTA CUBIERTOS LOS COSTOS DE EXHUMACIÓN Y TRASLADO, ASÍ COMO EL PAGO CONVENCIONAL POR USO DE LOTE Y GASTOS ADMINISTRATIVOS DE COBRANZA.',
            145,$y,985,$black,$font,18.0,31);
        $y+=24;

        $y=rp_doc_paragraph($image,
            'DICHA EXHUMACIÓN LA REALIZO DE TOTAL CONFORMIDAD, PAGANDO LOS COSTOS CORRESPONDIENTES, SIN PERJUICIO ALGUNO DEMANDABLE PARA MEGUESA, S.A. DE C.V.; POR LO TANTO, DEVUELVO Y CEDO EL DERECHO DE USO A PERPETUIDAD DEL LOTE ADQUIRIDO'.($contract!==''?' SEGÚN EL NÚMERO DE CONTRATO ANTES MENCIONADO':'').'.',
            145,$y,985,$black,$font,18.0,31);
        $y+=24;

        $closing='RECIBO DE CONFORMIDAD LOS RESTOS';
        if($relationship!=='')$closing.=' DE MI '.$relationship;
        $closing.=' Y AGRADEZCO LAS ATENCIONES BRINDADAS A LA PRESENTE. ESTA CARTA ES DE CARÁCTER IRREVOCABLE.';
        $y=rp_doc_paragraph($image,$closing,145,$y,985,$black,$font,18.0,31);

    }else{
        $filename='Carta_Reubicacion.pdf';

        if($holder!==''){
            rs_carta_text($image,145,$y,'TITULAR: '.$holder,$black,$font,18.0,true);
            $y+=55;
        }

        $y=rp_doc_paragraph($image,
            'POR MEDIO DE LA PRESENTE LE INFORMAMOS QUE, DEBIDO A LA NECESIDAD DE USO SOBRE SU LOTE QUE AÚN ESTÁ EN PROCESO DE CONSTRUCCIÓN Y DE ACUERDO CON LAS CONDICIONES DE SU CONTRATO, SE LE ASIGNARÁ UNO CON LAS MISMAS CARACTERÍSTICAS Y PRECIO U OTRO, PREVIA AUTORIZACIÓN Y ACUERDO CON EL CLIENTE.',
            145,$y,985,$black,$font,18.0,31);
        $y+=24;

        $move=[];
        if($location!=='')$move[]='SU UBICACIÓN ACTUAL '.$location;
        if($newLocation!=='')$move[]='SERÁ REASIGNADA A '.$newLocation;
        if($deceased!=='')$move[]='PARA PODER LLEVAR A CABO EL SERVICIO DE INHUMACIÓN DE '.$deceased;
        if($move!==[]){
            $y=rp_doc_paragraph($image,implode(' ',$move).'.',145,$y,985,$black,$font,18.0,31);
            $y+=24;
        }

        $y=rp_doc_paragraph($image,
            'LA REASIGNACIÓN QUEDA SIN NINGÚN CARGO ADICIONAL POR ESTE CONCEPTO. SIN MÁS POR EL MOMENTO, QUEDAMOS A SUS ÓRDENES.',
            145,$y,985,$black,$font,18.0,31);
    }

    // Firmas: sólo se imprime información que realmente exista.
    $leftLabel=$holder!==''?$holder:'NOMBRE Y FIRMA';
    $rightLabel=$beneficiary!==''?$beneficiary:'NOMBRE Y FIRMA';

    imageline($image,190,1490,520,1490,$gray);
    imageline($image,755,1490,1085,1490,$gray);
    rs_carta_center($image,355,1530,$leftLabel,$black,$font,15.0,false);
    rs_carta_center($image,920,1530,$rightLabel,$black,$font,15.0,false);

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
