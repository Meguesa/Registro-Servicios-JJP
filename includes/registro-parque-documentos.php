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


function rp_nicho_template_image(): GdImage
{
    $filename='plantilla_placa_nicho_correcta.png';
    $path=dirname(__DIR__).'/assets/templates/parque/'.$filename;

    // Primero intentar la copia desplegada en cPanel. Algunos despliegues
    // historicos publicaron binarios como texto, por lo que tambien se valida
    // que GD realmente pueda abrir el archivo antes de usarlo.
    if(is_file($path) && is_readable($path)){
        $bytes=@file_get_contents($path);
        if(is_string($bytes) && $bytes!==''){
            $image=@imagecreatefromstring($bytes);
            if($image instanceof GdImage){
                return $image;
            }
        }
    }

    // Fallback confiable: obtener el PNG binario directamente del repo fuente.
    // Registro-Servicios-JJP es la fuente de verdad; Portal-Interno-JJP solo
    // publica el contenido en cPanel.
    $url='https://raw.githubusercontent.com/Meguesa/Registro-Servicios-JJP/main/assets/templates/parque/'.$filename;
    $curl=curl_init($url);
    if($curl===false){
        throw new RuntimeException('No fue posible inicializar la plantilla correcta de placa de nicho.');
    }

    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Accept: image/png'],
        CURLOPT_USERAGENT=>'Registro-Servicios-JJP',
    ]);

    $bytes=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if(!is_string($bytes) || $bytes==='' || $status<200 || $status>=300){
        throw new RuntimeException(
            'No fue posible cargar la plantilla correcta de placa de nicho'
            .' (HTTP '.$status.($error!==''?', '.$error:'').').'
        );
    }

    $image=@imagecreatefromstring($bytes);
    if(!$image instanceof GdImage){
        throw new RuntimeException('La plantilla correcta de placa de nicho no es una imagen valida.');
    }

    return $image;
}


function rp_nicho_center_text(
    GdImage $image,
    string $text,
    string $font,
    float $size,
    int $baseline,
    int $color
): void {
    $box=@imagettfbbox($size,0,$font,$text);
    if(!is_array($box))return;
    $width=abs((int)$box[2]-(int)$box[0]);
    $x=(int)round((imagesx($image)-$width)/2);
    @imagettftext($image,$size,0,max(10,$x),$baseline,$color,$font,$text);
}

function rp_generate_nicho_plate(array $payload): array
{
    rs_image_require_gd();

    $family=mb_strtoupper(trim((string)($payload['nombreFamilia']??'')),'UTF-8');
    if($family===''){
        throw new RuntimeException('La placa de nicho requiere Nombre de Familia.');
    }

    // La plantilla oficial ya incluye la composicion correcta y el emblema
    // negro de Jardines de Juan Pablo. Solo sustituimos el texto de familia.
    $family=preg_replace('/^FAMILIA\\s+/u','',$family)??$family;
    $family=trim($family);
    if($family===''){
        throw new RuntimeException('La placa de nicho requiere el nombre de la familia.');
    }

    $source=rp_nicho_template_image();
    $width=2048;
    $height=1024;
    $image=imagecreatetruecolor($width,$height);
    if(!$image instanceof GdImage){
        imagedestroy($source);
        throw new RuntimeException('No fue posible crear la placa de nicho.');
    }

    $white=imagecolorallocate($image,255,255,255);
    $black=imagecolorallocate($image,0,0,0);
    imagefilledrectangle($image,0,0,$width,$height,$white);

    try{
        // Escalar la plantilla oficial al tamano estandar que usa el correo.
        imagecopyresampled(
            $image,$source,
            0,0,0,0,
            $width,$height,
            imagesx($source),imagesy($source)
        );
        imagedestroy($source);
        $source=null;

        // La imagen oficial contiene texto de ejemplo. Se limpia unicamente
        // esa zona y se conserva intacto el emblema negro de la plantilla.
        imagefilledrectangle($image,0,0,$width,625,$white);

        $familyFont=function_exists('rs_plate_pagella_font_path')
            ? rs_plate_pagella_font_path()
            : rs_image_font_path();
        if(!is_string($familyFont) || $familyFont===''){
            throw new RuntimeException('No fue posible resolver la tipografia de la placa de nicho.');
        }

        rp_nicho_center_text($image,'FAMILIA',$familyFont,105.0,335,$black);

        $maxWidth=(int)round($width*0.90);
        $size=112.0;
        while($size>52.0){
            $box=@imagettfbbox($size,0,$familyFont,$family);
            $textWidth=is_array($box)?abs((int)$box[2]-(int)$box[0]):0;
            if($textWidth<=$maxWidth)break;
            $size-=3.0;
        }
        rp_nicho_center_text($image,$family,$familyFont,$size,530,$black);

        ob_start();
        imagepng($image,null,6);
        $png=(string)ob_get_clean();
    }finally{
        if(isset($source) && $source instanceof GdImage)imagedestroy($source);
        imagedestroy($image);
    }

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


function rp_doc_letter_font(string $style='regular'): ?string
{
    static $cache=[];

    if(isset($cache[$style]))return $cache[$style];

    if($style==='bold'){
        return $cache[$style]=rs_image_bold_font_path();
    }
    if($style==='regular'){
        return $cache[$style]=rs_image_font_path();
    }

    $filename=$style==='bolditalic'?'Carlito-BoldItalic.ttf':'Carlito-Italic.ttf';
    $local=[
        dirname(__DIR__).'/assets/fonts/'.$filename,
        dirname(__DIR__).'/fonts/'.$filename,
    ];
    foreach($local as $candidate){
        if(function_exists('rs_image_valid_font_file') && rs_image_valid_font_file($candidate)){
            return $cache[$style]=$candidate;
        }
    }

    if(function_exists('rs_image_download_font')){
        foreach([
            'https://raw.githubusercontent.com/googlefonts/carlito/main/fonts/ttf/'.$filename,
            'https://raw.githubusercontent.com/google/fonts/main/ofl/carlito/'.$filename,
        ] as $url){
            $downloaded=rs_image_download_font($url,$filename);
            if($downloaded!==null)return $cache[$style]=$downloaded;
        }
    }

    return $cache[$style]=($style==='bolditalic'
        ? (rs_image_bold_font_path() ?: rs_image_font_path())
        : rs_image_font_path());
}

function rp_doc_image_width(string $text,?string $font,float $size): int
{
    if($text==='' || $font===null)return 0;
    $box=@imagettfbbox($size,0,$font,$text);
    if(!is_array($box))return 0;
    return abs((int)$box[2]-(int)$box[0]);
}

function rp_doc_image_text(
    GdImage $image,
    string $text,
    int $x,
    int $baseline,
    float $size,
    int $color,
    string $style='regular',
    bool $underline=false
): int {
    $font=rp_doc_letter_font($style);
    if($font===null || $text==='')return 0;

    @imagettftext($image,$size,0,$x,$baseline,$color,$font,$text);
    $width=rp_doc_image_width($text,$font,$size);

    if($underline && $width>0){
        $lineY=$baseline+3;
        imageline($image,$x,$lineY,$x+$width,$lineY,$color);
    }
    return $width;
}

/**
 * @param array<int,array{text:string,style?:string,underline?:bool}> $segments
 * @return array{baseline:int,lines:int}
 */
function rp_doc_image_flow(
    GdImage $image,
    array $segments,
    int $left,
    int $baseline,
    int $maxWidth,
    float $size,
    int $lineHeight,
    int $color
): array {
    $x=$left;
    $right=$left+$maxWidth;
    $lines=1;
    $pendingSpace=false;

    foreach($segments as $segment){
        $text=(string)($segment['text']??'');
        if($text==='')continue;

        $style=(string)($segment['style']??'regular');
        $underline=(bool)($segment['underline']??false);
        $font=rp_doc_letter_font($style);
        if($font===null)continue;

        $tokens=preg_split('/(\n|\s+)/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY)?:[$text];

        foreach($tokens as $token){
            if(str_contains($token,"\n")){
                $newLines=substr_count($token,"\n");
                for($i=0;$i<$newLines;$i++){
                    $x=$left;
                    $baseline+=$lineHeight;
                    $lines++;
                }
                $pendingSpace=false;
                continue;
            }

            if(trim($token)===''){
                $pendingSpace=true;
                continue;
            }

            $wordWidth=rp_doc_image_width($token,$font,$size);
            $spaceWidth=$pendingSpace
                ? rp_doc_image_width(' ',rp_doc_letter_font('regular'),$size)
                : 0;

            if($x>$left && $x+$spaceWidth+$wordWidth>$right){
                $x=$left;
                $baseline+=$lineHeight;
                $lines++;
                $spaceWidth=0;
            }

            $x+=$spaceWidth;
            $x+=rp_doc_image_text(
                $image,$token,$x,$baseline,$size,$color,$style,$underline
            );
            $pendingSpace=false;
        }
    }

    return ['baseline'=>$baseline,'lines'=>$lines];
}

function rp_doc_image_right(
    GdImage $image,
    string $text,
    int $right,
    int $baseline,
    float $size,
    int $color,
    string $style='regular',
    bool $underline=false
): void {
    $font=rp_doc_letter_font($style);
    $width=rp_doc_image_width($text,$font,$size);
    rp_doc_image_text($image,$text,$right-$width,$baseline,$size,$color,$style,$underline);
}

function rp_doc_image_center(
    GdImage $image,
    string $text,
    int $center,
    int $baseline,
    float $size,
    int $color,
    string $style='regular'
): void {
    $font=rp_doc_letter_font($style);
    $width=rp_doc_image_width($text,$font,$size);
    rp_doc_image_text($image,$text,(int)round($center-$width/2),$baseline,$size,$color,$style,false);
}

function rp_doc_image_to_pdf(GdImage $image,int $width,int $height,string $filename): array
{
    ob_start();
    imagejpeg($image,null,92);
    $jpeg=(string)ob_get_clean();
    imagedestroy($image);

    if($jpeg===''){
        throw new RuntimeException('No fue posible codificar la carta de Parque.');
    }

    return [
        'name'=>$filename,
        'contentType'=>'application/pdf',
        'bytes'=>rs_carta_jpeg_to_pdf($jpeg,$width,$height),
    ];
}

function rp_doc_clean_location_text(string $value): string
{
    $value=mb_strtoupper(trim($value),'UTF-8');
    $value=preg_replace('/\s*-\s*/u','-',$value)??$value;
    return preg_replace('/\s+/u',' ',$value)??$value;
}

function rp_doc_relocation_date_value(array $payload): string
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
        try{$date=new DateTimeImmutable($raw,$timezone);}
        catch(Throwable){return '';}
    }

    $months=[
        1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
        5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
        9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre',
    ];

    return 'Monterrey, Nuevo León '.$date->format('j').' '.$months[(int)$date->format('n')].' del '.$date->format('Y');
}

function rp_doc_letter_pdf(array $payload,string $kind): array
{
    rs_image_require_gd();

    // Las cartas se construyen completas siguiendo los Word oficiales.
    // No se escribe texto encima de un PDF con valores de ejemplo.
    $width=1275;
    $height=1650;
    $image=imagecreatetruecolor($width,$height);
    if(!$image instanceof GdImage){
        throw new RuntimeException('No fue posible crear la carta de Parque.');
    }

    $white=imagecolorallocate($image,255,255,255);
    $black=imagecolorallocate($image,18,18,18);
    imagefilledrectangle($image,0,0,$width,$height,$white);

    $date=rp_doc_long_date_value($payload);
    $contract=mb_strtoupper(trim((string)($payload['numeroContrato']??'')),'UTF-8');
    $deceased=mb_strtoupper(trim((string)($payload['fallecido']??'')),'UTF-8');
    $relationship=mb_strtoupper(trim((string)($payload['parentescoTitular']??'')),'UTF-8');
    $section=mb_strtoupper(trim((string)($payload['seccion']??'')),'UTF-8');
    $holder=mb_strtoupper(trim((string)($payload['titular']??'')),'UTF-8');
    $letterLocation=rp_doc_clean_location_text(rp_doc_compact_location($payload,true));
    $propertyLocation=rp_doc_clean_location_text(rp_doc_compact_location($payload,false));

    $serviceNorm=rp_doc_norm((string)($payload['servicio']??''));
    $servicePrefix=$serviceNorm==='totalservicecomplemento'?'TSC':($serviceNorm==='totalservice'?'TS':'');
    $newLocation=rp_doc_clean_location_text((string)($payload['ubicacionNueva']??''));
    if($newLocation!=='' && $servicePrefix!==''){
        $newLocationNorm=rp_doc_norm($newLocation);
        if(!str_starts_with($newLocationNorm,strtolower($servicePrefix))){
            $newLocation=rp_doc_clean_location_text($servicePrefix.' - '.$newLocation);
        }
    }

    if($kind==='reubicacion'){
        $right=1135;
        $left=180;
        $bodyWidth=965;

        $relocationDate=rp_doc_relocation_date_value($payload);
        if($relocationDate!==''){
            rp_doc_image_right($image,$relocationDate,$right,300,20,$black,'regular',false);
        }

        $attention=$holder!==''?"At´n. ".$holder:"At´n.";
        rp_doc_image_text($image,$attention,$left,440,22,$black,'bold',false);
        rp_doc_image_text($image,'(Titular)',$left,472,22,$black,'bold',false);

        $baseline=575;
        $p1=[
            ['text'=>'Por medio de la presente le informamos que debido a la necesidad de uso sobre su lote funerario '],
        ];
        if($letterLocation!==''){
            $p1[]=['text'=>$letterLocation,'style'=>'bold'];
        }
        $p1[]=['text'=>' que aún está en proceso de construcción y de acuerdo a la cláusula Primera inciso '];
        $p1[]=['text'=>'“a)” de su contrato','style'=>'italic'];
        $p1[]=['text'=>' donde se indica '];
        $p1[]=['text'=>'“Que por causa fortuita de fuerza mayor o no estuviera disponible el bien contratado en la fecha del requerimiento del cliente no se hubiese terminado su construcción, se le asignará uno con las mismas características y precio u otro previa autorización y acuerdo con el cliente”.','style'=>'italic'];

        $result=rp_doc_image_flow($image,$p1,$left,$baseline,$bodyWidth,20,36,$black);
        $baseline=$result['baseline']+50;

        $p2=[['text'=>'Su lote ']];
        if($letterLocation!=='')$p2[]=['text'=>$letterLocation];
        $p2[]=['text'=>' será reasignado por el lote '];
        if($newLocation!==''){
            $p2[]=['text'=>$newLocation,'style'=>'bold'];
        }else{
            $p2[]=['text'=>'________________','style'=>'bold','underline'=>true];
        }
        $p2[]=['text'=>' para poder llevar a cabo su servicio de Inhumación a su ser querido con la calidad y servicio el cual como empresa nos comprometimos con usted, quedando sin ningún cargo adicional por este concepto.'];

        $result=rp_doc_image_flow($image,$p2,$left,$baseline,$bodyWidth,20,36,$black);
        $baseline=$result['baseline']+68;

        rp_doc_image_text($image,'Sin más por el momento quedo a sus órdenes.',$left,$baseline,20,$black,'regular',false);
        rp_doc_image_right($image,'MEGUESA, S.A. DE C.V',$right,1240,20,$black,'bold',false);

        return rp_doc_image_to_pdf($image,$width,$height,'Carta_Reubicacion.pdf');
    }

    $left=175;
    $right=1150;
    $bodyWidth=975;

    if($date!==''){
        rp_doc_image_right($image,'MONTERREY N.L. '.$date,$right,240,20,$black,'regular',true);
    }else{
        rp_doc_image_right($image,'MONTERREY N.L.',$right,240,20,$black,'regular',false);
    }

    rp_doc_image_text($image,'Atención.-',$left,335,24,$black,'bold',false);
    rp_doc_image_text($image,'MEGUESA S.A. DE C.V. /',$left,390,24,$black,'bold',false);
    rp_doc_image_text($image,'PARQUE DE DESCANSO JARDINES DE JUAN PABLO',$left,425,24,$black,'bold',false);
    rp_doc_image_text($image,'A quien corresponda',$left,505,20,$black,'regular',false);

    $isRetiro=$kind==='retiro';
    $subject=$isRetiro?'RETIRO DE CENIZAS.':'EXHUMACIÓN DE RESTOS.';
    rp_doc_image_text($image,'Asunto:',$left,555,20,$black,'regular',false);
    rp_doc_image_text($image,$subject,$left+88,555,20,$black,'bold',false);

    $baseline=655;
    $first=[
        ['text'=>'Por medio de la presente debido al incumplimiento de pago por mi parte en la obligación contraída con la empresa MEGUESA S.A. DE C.V. propietaria del Parque de Descanso Jardines de Juan Pablo'],
    ];
    if($contract!==''){
        $first[]=['text'=>' en el contrato '];
        $first[]=['text'=>$contract,'style'=>'bold','underline'=>true];
    }

    if($isRetiro){
        $first[]=['text'=>' solicito y autorizo el retiro de cenizas de la persona que en vida llevó el nombre de'."\n"];
    }else{
        $first[]=['text'=>' solicito y autorizo el retiro de los restos de la persona que en vida llevó el nombre de'."\n"];
    }

    $first[]=['text'=>'(+) '];
    if($deceased!==''){
        $first[]=['text'=>$deceased,'style'=>'bold','underline'=>true];
    }

    $first[]=['text'=>$isRetiro?' el cual descansa en el nicho ':' el cual descansa en el lote '];
    if($letterLocation!==''){
        $first[]=['text'=>$letterLocation,'style'=>'bold','underline'=>true];
    }
    if($section!==''){
        $first[]=['text'=>' sector '.$section];
    }

    $first[]=['text'=>$isRetiro
        ? ', siendo por mi cuenta cubiertos los costos de traslado, así como el pago convencional por uso de nicho y gastos administrativos de cobranza.'
        : ' siendo por mi cuenta cubiertos los costos de exhumación y traslado, así como el pago convencional por uso de lote y gastos administrativos de cobranza.'
    ];

    $result=rp_doc_image_flow($image,$first,$left,$baseline,$bodyWidth,18.5,34,$black);
    $baseline=$result['baseline']+52;

    $secondText=$isRetiro
        ? 'Dicho retiro lo realizo de total conformidad pagando los costos correspondientes sin perjuicio alguno demandable para la empresa MEGUESA S.A. DE C.V. por lo tanto devuelvo y cedo el derecho de uso a perpetuidad del nicho adquirido'
        : 'Dicha exhumación la realizo de total conformidad pagando los costos correspondientes sin perjuicio alguno demandable para la empresa MEGUESA S.A. DE C.V. por lo tanto devuelvo y cedo el derecho de uso a perpetuidad del lote adquirido';

    if($contract!==''){
        $secondText.=' según número de contrato antes mencionado';
    }
    $secondText.=$isRetiro
        ? ' donde esta nuestro familiar.'
        : ' donde sepultamos a nuestro familiar.';

    $result=rp_doc_image_flow(
        $image,
        [['text'=>$secondText]],
        $left,$baseline,$bodyWidth,18.5,34,$black
    );
    $baseline=$result['baseline']+46;

    $thirdText=$isRetiro
        ? 'Teniendo la empresa MEGUESA, S.A. DE C.V. nuevamente el derecho sobre uso de este nicho.'
        : 'Teniendo la empresa MEGUESA, S.A. DE C.V. nuevamente el derecho sobre uso de este lote.';
    $result=rp_doc_image_flow(
        $image,
        [['text'=>$thirdText]],
        $left,$baseline,$bodyWidth,18.5,34,$black
    );
    $baseline=$result['baseline']+46;

    $closing=[
        ['text'=>$isRetiro?'Recibo de conformidad las cenizas de ':'Recibo de conformidad los restos de '],
    ];
    if($relationship!==''){
        $closing[]=['text'=>$relationship,'style'=>'bold','underline'=>true];
    }else{
        $closing[]=['text'=>'___________________','underline'=>true];
    }
    $closing[]=['text'=>' y agradezco las atenciones brindadas a la presente. Esta carta es de carácter irrevocable.'];

    $result=rp_doc_image_flow($image,$closing,$left,$baseline,$bodyWidth,18.5,34,$black);
    $baseline=$result['baseline'];

    // Identificador visual del campo de parentesco, como en los Word oficiales.
    rp_doc_image_center($image,'(Parentesco)',640,$baseline+27,14,$black,'regular');

    $atteY=max(1260,$baseline+105);
    rp_doc_image_text($image,'ATTE.',$left,$atteY,20,$black,'regular',false);

    $signatureY=1460;
    imageline($image,210,$signatureY,455,$signatureY,$black);
    imageline($image,815,$signatureY,1060,$signatureY,$black);

    rp_doc_image_center($image,'NOMBRE Y FIRMA',332,$signatureY+34,16,$black,'bold');
    rp_doc_image_center(
        $image,
        $holder!==''?$holder:'TITULAR DEL CONTRATO',
        332,
        $signatureY+60,
        16,
        $black,
        'bold'
    );
    rp_doc_image_center($image,'NOMBRE Y TEL/CEL',938,$signatureY+34,16,$black,'bold');
    rp_doc_image_center($image,'AVAL Y/O BENEFICIARIO',938,$signatureY+60,16,$black,'bold');

    return rp_doc_image_to_pdf(
        $image,$width,$height,
        $isRetiro?'Carta_Retiro_Cenizas.pdf':'Carta_Exhumacion.pdf'
    );
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
