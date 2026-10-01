<?php
declare(strict_types=1);

require_once __DIR__ . '/registro-parque-imagenes.php';
require_once __DIR__ . '/registro-carta.php';
require_once __DIR__ . '/registro-placa.php';

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


function rp_generate_nicho_plate(array $payload): array
{
    rs_image_require_gd();

    $family=mb_strtoupper(trim((string)($payload['nombreFamilia']??'')),'UTF-8');
    if($family===''){
        throw new RuntimeException('La placa de nicho requiere Nombre de Familia.');
    }

    // Proporción exacta de la plantilla oficial: 544.8 x 271.2 pt a 300 dpi.
    $width=2270;
    $height=1130;
    $image=imagecreatetruecolor($width,$height);
    if(!$image instanceof GdImage){
        throw new RuntimeException('No fue posible crear la placa de nicho.');
    }

    $white=imagecolorallocate($image,255,255,255);
    $black=imagecolorallocate($image,0,0,0);
    imagefilledrectangle($image,0,0,$width,$height,$white);

    // Cuatro logotipos, respetando la distribución del PDF oficial.
    $logoW=260;
    $logoH=150;
    $positions=[
        [70,170],
        [580,170],
        [1090,170],
        [1600,170],
    ];
    foreach($positions as [$x,$y]){
        rs_plate_draw_logo($image,$x,$y,$logoW,$logoH);
    }

    // Nombre de familia centrado en el ancho total de la placa.
    try{
        $font=rs_plate_pagella_font_path();
    }catch(Throwable){
        $font=rs_image_bold_font_path() ?: rs_image_font_path();
    }
    if($font===null){
        imagedestroy($image);
        throw new RuntimeException('No fue posible resolver la tipografia para la placa de nicho.');
    }

    $size=108.0;
    while($size>58.0){
        $box=@imagettfbbox($size,0,$font,$family);
        $textWidth=is_array($box)?abs((int)$box[2]-(int)$box[0]):0;
        if($textWidth<=1950)break;
        $size-=3.0;
    }

    $box=@imagettfbbox($size,0,$font,$family);
    $textWidth=is_array($box)?abs((int)$box[2]-(int)$box[0]):0;
    $x=max(30,(int)round(($width-$textWidth)/2));
    imagettftext($image,$size,0,$x,665,$black,$font,$family);

    ob_start();
    imagepng($image,null,6);
    $png=(string)ob_get_clean();
    imagedestroy($image);

    if($png===''){
        throw new RuntimeException('No fue posible codificar la placa de nicho.');
    }

    return [
        'name'=>'Placa_Nicho.png',
        'contentType'=>'image/png',
        'bytes'=>$png,
    ];
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

    $destape=rp_doc_norm((string)($payload['destape']??''));
    if($typePlate==='nicho' && $destape==='primero'){
        $attachments[]=rp_generate_nicho_plate($payload);
    }

    $requiereUrnaAdicional=!empty($payload['requiereCambioUrna']);
    if($typePlate==='urna' || $requiereUrnaAdicional){
        $urna=rs_generate_urna_plate($payload);
        $attachments[]=[
            'name'=>'Placa_Urna.png',
            'contentType'=>'image/png',
            'bytes'=>(string)($urna['png']??''),
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

function rp_doc_template_pdf(string $filename): string
{
    $path=dirname(__DIR__).'/assets/templates/parque/'.$filename;

    if(is_file($path) && is_readable($path)){
        $pdf=@file_get_contents($path);
        if(is_string($pdf) && str_starts_with($pdf,'%PDF-')){
            return $pdf;
        }
    }

    // En cPanel los workflows existentes publican el codigo de Registro de Servicios,
    // pero no necesariamente los binarios nuevos. Si la plantilla no esta local,
    // obtenerla directamente del mismo repo Registro-Servicios-JJP y usarla en memoria.
    $allowed=[
        'CARTA EXHUMACION.pdf',
        'CARTA RETIRO DE CENIZAS.pdf',
        'CARTA REUBICACION.pdf',
    ];
    if(!in_array($filename,$allowed,true)){
        throw new RuntimeException('Plantilla de Parque no permitida: '.$filename);
    }

    $url='https://raw.githubusercontent.com/Meguesa/Registro-Servicios-JJP/main/assets/templates/parque/'
        .rawurlencode($filename);

    $curl=curl_init($url);
    if($curl===false){
        throw new RuntimeException('No fue posible inicializar la descarga de la plantilla de Parque: '.$filename);
    }

    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Accept: application/pdf'],
        CURLOPT_USERAGENT=>'Registro-Servicios-JJP',
    ]);

    $pdf=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if(!is_string($pdf) || $status<200 || $status>=300 || !str_starts_with($pdf,'%PDF-')){
        $detail=$error!==''?' '.$error:'';
        throw new RuntimeException(
            'No fue posible cargar la plantilla oficial de Parque: '.$filename
            .' (HTTP '.$status.').'.$detail
        );
    }

    // Cache opcional local. Si cPanel no permite escritura, la plantilla ya queda
    // disponible en memoria y el flujo continua sin depender del cache.
    $cacheDir=dirname(__DIR__).'/.registro-servicios-data/templates/parque';
    if((is_dir($cacheDir) || @mkdir($cacheDir,0750,true)) && is_writable($cacheDir)){
        @file_put_contents($cacheDir.'/'.$filename,$pdf,LOCK_EX);
    }

    return $pdf;
}

function rp_doc_compact_location(array $payload,bool $includeServicePrefix=true): string
{
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    $lot=mb_strtoupper(trim((string)($payload['numLoteNicho']??'')),'UTF-8');
    $block=mb_strtoupper(trim((string)($payload['manzana']??'')),'UTF-8');

    $prefix='';
    if($includeServicePrefix){
        $service=rp_doc_norm((string)($payload['servicio']??''));
        $prefix=$service==='totalservicecomplemento'?'TSC':($service==='totalservice'?'TS':'');
    }

    return implode('-',array_values(array_filter(
        [$prefix,$section,$lot,$block],
        static fn(string $value):bool=>$value!==''
    )));
}

function rp_doc_long_date_value(array $payload): string
{
    $raw=trim((string)($payload['fechaHoraInicio']??''));
    if($raw==='')return '';

    $timezone=new DateTimeZone('America/Monterrey');
    $date=null;
    foreach([
        'd/m/Y H:i','d/m/Y H:i:s','d/m/Y',
        'Y-m-d H:i','Y-m-d H:i:s','Y-m-d',
        'Y-m-d\TH:i','Y-m-d\TH:i:s'
    ] as $format){
        $candidate=DateTimeImmutable::createFromFormat('!'.$format,$raw,$timezone);
        if($candidate instanceof DateTimeImmutable){
            $date=$candidate;
            break;
        }
    }

    if(!$date instanceof DateTimeImmutable){
        try{
            $date=new DateTimeImmutable($raw,$timezone);
        }catch(Throwable){
            return '';
        }
    }

    $months=[
        1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',
        5=>'MAYO',6=>'JUNIO',7=>'JULIO',8=>'AGOSTO',
        9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE',
    ];

    return 'A '.$date->format('j').' DE '.$months[(int)$date->format('n')].' DEL '.$date->format('Y');
}

function rp_doc_pdf_literal(string $value): string
{
    if(function_exists('iconv')){
        $encoded=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);
        if(is_string($encoded))$value=$encoded;
    }

    return '('.str_replace(
        ['\\','(',')',"\r","\n"],
        ['\\\\','\\(','\\)',' ',' '],
        $value
    ).')';
}

function rp_doc_pdf_fit_size(
    string $text,
    float $size,
    float $maxWidth,
    float $factor=0.50,
    float $minSize=7.0
): float {
    if($text==='' || $maxWidth<=0)return $size;

    $encoded=function_exists('iconv')
        ? (@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text) ?: $text)
        : $text;
    $estimated=max(1,strlen((string)$encoded))*$size*$factor;
    if($estimated>$maxWidth){
        $size=max($minSize,$size*($maxWidth/$estimated));
    }
    return $size;
}

function rp_doc_pdf_white(float $x,float $y,float $width,float $height): string
{
    return "q 1 1 1 rg "
        .sprintf('%.2F %.2F %.2F %.2F re f',$x,$y,$width,$height)
        ." Q\n";
}

function rp_doc_pdf_text(
    string $font,
    string $text,
    float $x,
    float $y,
    float $size,
    float $maxWidth=0.0,
    bool $underline=false,
    float $widthFactor=0.50
): string {
    if($text==='')return '';

    $size=$maxWidth>0
        ? rp_doc_pdf_fit_size($text,$size,$maxWidth,$widthFactor)
        : $size;

    $stream="BT 0 g /".$font." ".sprintf('%.2F',$size)
        ." Tf 1 0 0 1 ".sprintf('%.2F %.2F',$x,$y)
        ." Tm ".rp_doc_pdf_literal($text)." Tj ET\n";

    if($underline){
        $encoded=function_exists('iconv')
            ? (@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text) ?: $text)
            : $text;
        $width=strlen((string)$encoded)*$size*$widthFactor;
        if($maxWidth>0)$width=min($width,$maxWidth);
        $underlineY=$y-1.5;
        $stream.="0 g 0.65 w ".sprintf(
            '%.2F %.2F m %.2F %.2F l S',
            $x,$underlineY,$x+$width,$underlineY
        )."\n";
    }

    return $stream;
}

function rp_doc_pdf_estimated_width(string $text,float $size,float $factor=0.50): float
{
    $encoded=function_exists('iconv')
        ? (@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text) ?: $text)
        : $text;
    return max(0.0,strlen((string)$encoded)*$size*$factor);
}

/**
 * Compone un párrafo completo con segmentos de estilo distintos.
 * Los campos variables pueden ir en negrita/subrayado y el texto restante
 * fluye alrededor de ellos sin depender de posiciones fijas.
 *
 * @param array<int,array{text:string,font?:string,underline?:bool,factor?:float}> $segments
 */
function rp_doc_pdf_flow_segments(
    array $segments,
    float $left,
    float $topBaseline,
    float $width,
    float $size=9.4,
    float $lineHeight=13.0
): string {
    $x=$left;
    $y=$topBaseline;
    $right=$left+$width;
    $stream='';

    foreach($segments as $segment){
        $font=(string)($segment['font']??'JReg');
        $underline=(bool)($segment['underline']??false);
        $factor=(float)($segment['factor']??0.50);
        $text=(string)($segment['text']??'');
        if($text==='')continue;

        $tokens=preg_split('/(\s+)/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY)?:[$text];
        foreach($tokens as $token){
            if(trim($token)===''){
                $spaceWidth=rp_doc_pdf_estimated_width(' ',$size,$factor);
                if($x+$spaceWidth<=$right)$x+=$spaceWidth;
                continue;
            }

            $tokenWidth=rp_doc_pdf_estimated_width($token,$size,$factor);
            if($x>$left && $x+$tokenWidth>$right){
                $x=$left;
                $y-=$lineHeight;
            }

            $stream.=rp_doc_pdf_text(
                $font,
                $token,
                $x,
                $y,
                $size,
                0,
                $underline,
                $factor
            );
            $x+=$tokenWidth;
        }
    }

    return $stream;
}

function rp_doc_pdf_center_text(
    string $font,
    string $text,
    float $x,
    float $y,
    float $width,
    float $size,
    bool $underline=false,
    float $widthFactor=0.50
): string {
    if($text==='')return '';

    $size=rp_doc_pdf_fit_size($text,$size,$width,$widthFactor);
    $encoded=function_exists('iconv')
        ? (@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text) ?: $text)
        : $text;
    $estimated=min($width,strlen((string)$encoded)*$size*$widthFactor);
    $start=$x+max(0.0,($width-$estimated)/2);

    return rp_doc_pdf_text(
        $font,$text,$start,$y,$size,$width,$underline,$widthFactor
    );
}

function rp_doc_pdf_overlay(string $pdf,string $content,int $pageObject=3): string
{
    if(!preg_match(
        '~trailer\s*(<<.*?>>)\s*startxref\s*(\d+)\s*%%EOF\s*$~s',
        $pdf,
        $trailerMatch
    )){
        throw new RuntimeException('La plantilla de carta no contiene un trailer PDF compatible.');
    }

    $trailer=(string)$trailerMatch[1];
    $previousXref=(int)$trailerMatch[2];

    if(!preg_match('/\/Size\s+(\d+)/',$trailer,$sizeMatch)){
        throw new RuntimeException('La plantilla de carta no contiene /Size.');
    }

    $regularObject=(int)$sizeMatch[1];
    $boldObject=$regularObject+1;
    $boldItalicObject=$regularObject+2;
    $serifBoldObject=$regularObject+3;
    $contentObject=$regularObject+4;
    $newSize=$regularObject+5;

    if(!preg_match(
        '~(?:^|[\r\n])'.$pageObject.' 0 obj\s*(.*?)\s*endobj~s',
        $pdf,
        $pageMatch
    )){
        throw new RuntimeException('No se encontro la pagina de la plantilla oficial.');
    }
    $page=trim((string)$pageMatch[1]);

    if(!preg_match('/\/Contents\s+(\d+)\s+0\s+R/',$page,$contentsMatch)){
        throw new RuntimeException('La pagina oficial no contiene /Contents compatible.');
    }

    $page=preg_replace(
        '/\/Contents\s+\d+\s+0\s+R/',
        '/Contents ['.$contentsMatch[1].' 0 R '.$contentObject.' 0 R]',
        $page,
        1
    )??$page;

    $page=preg_replace_callback(
        '~/Font\s*<<(.+?)>>~s',
        static fn(array $match):string =>
            '/Font <<'.$match[1]
            .' /JReg '.$regularObject.' 0 R'
            .' /JBold '.$boldObject.' 0 R'
            .' /JBoldItalic '.$boldItalicObject.' 0 R'
            .' /JSerifBold '.$serifBoldObject.' 0 R >>',
        $page,
        1
    )??$page;

    $trailer=preg_replace('/\/Prev\s+\d+/','',$trailer)??$trailer;
    $trailer=preg_replace('/\/Size\s+\d+/','/Size '.$newSize,$trailer,1)??$trailer;
    $trailer=preg_replace(
        '/>>\s*$/',
        '/Prev '.$previousXref.' >>',
        $trailer
    )??$trailer;

    $append="\n";
    $offsets=[];

    $objects=[
        [$pageObject,$page],
        [$regularObject,'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'],
        [$boldObject,'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'],
        [$boldItalicObject,'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>'],
        [$serifBoldObject,'<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>'],
    ];

    foreach($objects as [$number,$body]){
        $offsets[$number]=strlen($pdf)+strlen($append);
        $append.=$number." 0 obj\n".$body."\nendobj\n";
    }

    $compressed=@gzcompress($content,9);
    if(!is_string($compressed)){
        throw new RuntimeException('No fue posible comprimir el contenido de la carta.');
    }

    $offsets[$contentObject]=strlen($pdf)+strlen($append);
    $append.=$contentObject." 0 obj\n"
        .'<< /Filter /FlateDecode /Length '.strlen($compressed)." >>\n"
        ."stream\n".$compressed."\nendstream\nendobj\n";

    $xrefOffset=strlen($pdf)+strlen($append);
    ksort($offsets);
    foreach($offsets as $number=>$offset){
        $append.="xref\n".$number." 1\n"
            .sprintf("%010d 00000 n \n",$offset);
    }

    $append.="trailer\n".$trailer
        ."\nstartxref\n".$xrefOffset
        ."\n%%EOF\n";

    return $pdf.$append;
}

function rp_doc_letter_pdf(array $payload,string $kind): array
{
    $date=rp_doc_long_date_value($payload);
    $contract=mb_strtoupper(trim((string)($payload['numeroContrato']??'')),'UTF-8');
    $deceased=mb_strtoupper(trim((string)($payload['fallecido']??'')),'UTF-8');
    $relationship=mb_strtoupper(trim((string)($payload['parentescoTitular']??'')),'UTF-8');
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    $holder=mb_strtoupper(trim((string)($payload['titular']??'')),'UTF-8');
    $letterLocation=rp_doc_compact_location($payload,true);
    $propertyLocation=rp_doc_compact_location($payload,false);
    $newLocation=mb_strtoupper(trim((string)($payload['ubicacionNueva']??'')),'UTF-8');

    if($kind==='retiro'){
        $pdf=rp_doc_template_pdf('CARTA RETIRO DE CENIZAS.pdf');
        $stream='';

        if($date!==''){
            $stream.=rp_doc_pdf_white(315,657,240,16);
            $stream.=rp_doc_pdf_text('JReg',$date,315,660,11,240,true,0.49);
        }

        // Elimina exclusivamente los valores de ejemplo impresos en el PDF maestro.
        $stream.=rp_doc_pdf_white(477,432,45,15);
        $stream.=rp_doc_pdf_white(97,401,177,19);
        $stream.=rp_doc_pdf_white(388,401,70,19);
        $stream.=rp_doc_pdf_white(484,401,38,19);

        if($contract!==''){
            $stream.=rp_doc_pdf_text('JBold',$contract,479,436.5,10,42,true,0.50);
        }
        if($deceased!==''){
            $stream.=rp_doc_pdf_text('JBoldItalic',$deceased,98,406,12,174,true,0.49);
        }
        if($letterLocation!==''){
            $stream.=rp_doc_pdf_text('JBold',$letterLocation,389,406,11,68,true,0.49);
        }
        if($section!==''){
            $stream.=rp_doc_pdf_text('JBold',$section,485,406,10,36,true,0.50);
        }

        if($relationship!==''){
            $stream.=rp_doc_pdf_white(250,263,86,29);
            $stream.=rp_doc_pdf_center_text('JBold',$relationship,250,276,86,10,true,0.50);
        }

        return [
            'name'=>'Carta_Retiro_Cenizas.pdf',
            'contentType'=>'application/pdf',
            'bytes'=>rp_doc_pdf_overlay($pdf,$stream,3),
        ];
    }

    if($kind==='exhumacion'){
        $pdf=rp_doc_template_pdf('CARTA EXHUMACION.pdf');
        $stream='';

        if($date!==''){
            $stream.=rp_doc_pdf_white(315,657,240,16);
            $stream.=rp_doc_pdf_text('JReg',$date,315,660,11,240,true,0.49);
        }

        // El cuerpo original contiene valores de ejemplo embebidos.
        // Se limpia el bloque completo y se recompone para que los campos variables
        // formen parte natural del texto y no queden superpuestos.
        $stream.=rp_doc_pdf_white(80,255,455,220);

        $segments=[
            ['text'=>'Por medio de la presente debido al incumplimiento de pago por mi parte en la obligación contraída con la empresa MEGUESA S.A. DE C.V. propietaria del Parque de Descanso Jardines de Juan Pablo'],
        ];
        if($contract!==''){
            $segments[]=['text'=>' en el contrato '];
            $segments[]=['text'=>$contract,'font'=>'JBold','underline'=>true];
        }
        $segments[]=['text'=>' solicito y autorizo el retiro de los restos de la persona que en vida llevó el nombre de '];
        if($deceased!==''){
            $segments[]=['text'=>'(+) '.$deceased,'font'=>'JBold','underline'=>true];
        }
        if($letterLocation!==''){
            $segments[]=['text'=>' el cual descansa en el lote '];
            $segments[]=['text'=>$letterLocation,'font'=>'JBold','underline'=>true];
        }
        if($section!==''){
            $segments[]=['text'=>' sector '];
            $segments[]=['text'=>$section,'font'=>'JBold','underline'=>true];
        }
        $segments[]=['text'=>' siendo por mi cuenta cubiertos los costos de exhumación y traslado, así como el pago convencional por uso de lote y gastos administrativos de cobranza.'];

        $stream.=rp_doc_pdf_flow_segments($segments,85,454,440,9.2,12.5);

        $fixed2=[
            ['text'=>'Dicha exhumación la realizo de total conformidad pagando los costos correspondientes sin perjuicio alguno demandable para la empresa MEGUESA S.A. DE C.V. por lo tanto devuelvo y cedo el derecho de uso a perpetuidad del lote adquirido'],
        ];
        if($contract!==''){
            $fixed2[]=['text'=>' según número de contrato antes mencionado'];
        }
        $fixed2[]=['text'=>' donde sepultamos a nuestro familiar. Teniendo la empresa MEGUESA, S.A. DE C.V. nuevamente el derecho sobre uso de este lote.'];
        $stream.=rp_doc_pdf_flow_segments($fixed2,85,352,440,9.2,12.5);

        $closing=[['text'=>'Recibo de conformidad los restos de ']];
        if($relationship!==''){
            $closing[]=['text'=>$relationship,'font'=>'JBold','underline'=>true];
        }else{
            $closing[]=['text'=>'___________________'];
        }
        $closing[]=['text'=>' y agradezco las atenciones brindadas a la presente. Esta carta es de carácter irrevocable.'];
        $stream.=rp_doc_pdf_flow_segments($closing,85,286,440,9.2,12.5);

        return [
            'name'=>'Carta_Exhumacion.pdf',
            'contentType'=>'application/pdf',
            'bytes'=>rp_doc_pdf_overlay($pdf,$stream,3),
        ];
    }

    $pdf=rp_doc_template_pdf('CARTA REUBICACION.pdf');
    $stream='';

    if($date!==''){
        $stream.=rp_doc_pdf_white(395,616,137,17);
        $stream.=rp_doc_pdf_text('JReg',$date,397,620,10,132,true,0.49);
    }

    // Encabezado de titular.
    $stream.=rp_doc_pdf_white(83,527,250,34);
    if($holder!==''){
        $stream.=rp_doc_pdf_text('JBold',"At'n.",85,542,11,40,false,0.50);
        $stream.=rp_doc_pdf_text('JBold',$holder,119,542,11,180,true,0.49);
        $stream.=rp_doc_pdf_text('JBold','(Titular)',155,526,11,70,false,0.50);
    }

    // Recompone los dos párrafos que contienen las ubicaciones.
    // Esto evita que valores de diferente longitud se monten sobre el texto fijo.
    $stream.=rp_doc_pdf_white(80,335,455,165);

    $p1=[
        ['text'=>'Por medio de la presente le informamos que debido a la necesidad de uso sobre su lote funerario '],
    ];
    if($propertyLocation!==''){
        $p1[]=['text'=>$propertyLocation,'font'=>'JBold','underline'=>true];
    }
    $p1[]=['text'=>' que aún está en proceso de construcción y de acuerdo a la cláusula Primera inciso “a)” de su contrato donde se indica “Que por causa fortuita de fuerza mayor o no estuviera disponible el bien contratado en la fecha del requerimiento del cliente no se hubiese terminado su construcción, se le asignará uno con las mismas características y precio u otro previa autorización y acuerdo con el cliente”.'];
    $stream.=rp_doc_pdf_flow_segments($p1,85,484,440,9.2,12.5);

    $p2=[['text'=>'Su lote ']];
    if($propertyLocation!==''){
        $p2[]=['text'=>$propertyLocation,'font'=>'JBold','underline'=>true];
    }
    $p2[]=['text'=>' será reasignado por el lote '];
    if($newLocation!==''){
        $p2[]=['text'=>$newLocation,'font'=>'JBold','underline'=>true];
    }else{
        $p2[]=['text'=>'________________','font'=>'JBold','underline'=>true];
    }
    $p2[]=['text'=>' para poder llevar a cabo su servicio de Inhumación a su ser querido con la calidad y servicio el cual como empresa nos comprometimos con usted, quedando sin ningún cargo adicional por este concepto. Sin más por el momento quedo a sus órdenes.'];
    $stream.=rp_doc_pdf_flow_segments($p2,85,394,440,9.2,12.5);

    return [
        'name'=>'Carta_Reubicacion.pdf',
        'contentType'=>'application/pdf',
        'bytes'=>rp_doc_pdf_overlay($pdf,$stream,3),
    ];
}

/**
 * Reglas del flujo actual de Parque:
 * - Reubicacion: genera Carta Reubicacion.
 * - No liquidado + TipoPlaca=Nicho: Carta Retiro de Cenizas.
 * - No liquidado + cualquier otra placa/propiedad: Carta Exhumacion.
 *
 * @return array<int,array{name:string,contentType:string,bytes:string}>
 */
function rp_generate_letter_attachments(array $payload): array
{
    $attachments=[];
    $liquidation=rp_doc_norm((string)($payload['estatusLiquidacion']??''));
    $plateType=rp_doc_norm((string)($payload['tipoPlaca']??''));

    if(!empty($payload['requiereReubicacion'])){
        $attachments[]=rp_doc_letter_pdf($payload,'reubicacion');
    }

    if($liquidation==='noliquidado'){
        if($plateType==='nicho'){
            $attachments[]=rp_doc_letter_pdf($payload,'retiro');
        }else{
            $attachments[]=rp_doc_letter_pdf($payload,'exhumacion');
        }
    }

    return $attachments;
}
