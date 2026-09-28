<?php
declare(strict_types=1);

require_once __DIR__ . '/registro-sharepoint.php';

function rs_plate_template_pdf(): string {
    $dir = __DIR__ . '/.registro-servicios-data/templates';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $cache = $dir . '/plantilla_placa_urna.pdf';

    if (is_file($cache) && (time() - (int)@filemtime($cache) < 86400)) {
        $b=@file_get_contents($cache);
        if (is_string($b) && str_starts_with($b,'%PDF-')) return $b;
    }

    $host='meguesajdjp.sharepoint.com';
    $site='/sites/Operaciones';
    $rel='/sites/Operaciones/Documentos compartidos/Automaticaciones/Eventos Capillas/Plantillas/plantilla_placa_urna.pdf';
    try {
        $cfg=rs_sharepoint_config();
        $tok=rs_sharepoint_token($cfg,$host);
        $url='https://'.$host.$site.'/_api/web/GetFileByServerRelativePath(decodedurl=@f)/$value?'
            .http_build_query(['@f'=>"'".$rel."'"],'','&',PHP_QUERY_RFC3986);
        $ch=curl_init($url);
        if ($ch===false) throw new RuntimeException('No fue posible iniciar descarga de plantilla.');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>30,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok,'Accept: application/pdf'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        $b=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
        if ($b===false) throw new RuntimeException('Error descargando plantilla: '.$err);
        if ($status<200||$status>=300) throw new RuntimeException('SharePoint HTTP '.$status.' al descargar plantilla.');
        $pdf=(string)$b;
        if (!str_starts_with($pdf,'%PDF-')) throw new RuntimeException('La plantilla descargada no es PDF.');
        @file_put_contents($cache,$pdf,LOCK_EX);
        return $pdf;
    } catch (Throwable $e) {
        if (is_file($cache)) {
            $b=@file_get_contents($cache);
            if (is_string($b)&&str_starts_with($b,'%PDF-')) return $b;
        }
        throw $e;
    }
}

function rs_plate_pdf_bytes(string $s): string {
    if (function_exists('iconv')) {
        $x=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s);
        if (is_string($x)) return $x;
    }
    return rs_image_builtin_text($s);
}

function rs_plate_pdf_lit(string $s): string {
    $s=rs_plate_pdf_bytes($s);
    $s=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','', '\\n'],$s);
    return '('.$s.')';
}

function rs_plate_obj(string $pdf,int $n): string {
    if (!preg_match('/(?:^|[\r\n])'.$n.' 0 obj\s*(.*?)\s*endobj/s',$pdf,$m))
        throw new RuntimeException('Objeto PDF '.$n.' no encontrado en plantilla.');
    return (string)$m[1];
}

function rs_plate_set_lit(string $obj,string $key,string $lit): string {
    $p='~(/'.preg_quote($key,'~').'\s*)\((?:\\\\.|[^\\\\)])*\)~s';
    $r=preg_replace($p,'$1'.$lit,$obj,1,$count);
    if (!is_string($r)||$count!==1) throw new RuntimeException('No se pudo actualizar /'.$key.'.');
    return $r;
}

function rs_plate_widths(string $pdf): array {
    if (!preg_match('~/BaseFont/PalatinoLinotype.*?/FirstChar\s+(\d+).*?/Widths\s*\[([^\]]+)\]~s',$pdf,$m))
        throw new RuntimeException('Metricas PalatinoLinotype no encontradas.');
    preg_match_all('/-?\d+(?:\.\d+)?/',(string)$m[2],$nums);
    $first=(int)$m[1]; $out=[];
    foreach($nums[0] as $i=>$v) $out[$first+$i]=(float)$v;
    return $out;
}

function rs_plate_text_width(string $s,array $w,float $fs): float {
    $b=rs_plate_pdf_bytes($s); $sum=0.0;
    for($i=0;$i<strlen($b);$i++) $sum += $w[ord($b[$i])] ?? 500.0;
    return $sum*$fs/1000.0;
}

function rs_plate_name_lines(string $name,array $w): array {
    $name=preg_replace('/\s+/u',' ',trim($name))??trim($name);
    $words=preg_split('/\s+/u',$name)?:[$name];
    $lines=[]; $line='';
    foreach($words as $word){
        $cand=$line===''?$word:$line.' '.$word;
        if($line!=='' && rs_plate_text_width($cand,$w,28.0)>325.0){$lines[]=$line;$line=$word;}
        else $line=$cand;
    }
    if($line!=='')$lines[]=$line;
    if(count($lines)<=3) return $lines;

    $n=count($words); $best=null; $bestScore=INF;
    for($i=1;$i<=$n-2;$i++) for($j=$i+1;$j<=$n-1;$j++){
        $cand=[implode(' ',array_slice($words,0,$i)),implode(' ',array_slice($words,$i,$j-$i)),implode(' ',array_slice($words,$j))];
        $ww=array_map(fn($x)=>rs_plate_text_width($x,$w,28.0),$cand);
        if(max($ww)>325.0) continue;
        $score=max($ww)+0.15*(max($ww)-min($ww));
        if($score<$bestScore){$bestScore=$score;$best=$cand;}
    }
    if(is_array($best)) return $best;
    throw new RuntimeException('El nombre no cabe en la plantilla original sin alterar tipografia.');
}

function rs_plate_stream_obj(int $n,string $dict,string $content): string {
    $z=gzcompress($content,9);
    if(!is_string($z)) throw new RuntimeException('No se pudo comprimir apariencia PDF.');
    return $n." 0 obj\n<<".$dict.'/Length '.strlen($z).">>stream\n".$z."\nendstream\nendobj\n";
}

function rs_plate_name_baselines(int $lineCount): array {
    // La plantilla tiene tres renglones disponibles.
    // PDF usa coordenadas desde abajo hacia arriba:
    // 1 linea -> renglon central.
    // 2 lineas -> renglon central + renglon inferior.
    // 3 lineas -> los tres renglones.
    $top = 74.574005;
    $middle = 46.126007;
    $bottom = 17.678009;

    if ($lineCount <= 1) return [$middle];
    if ($lineCount === 2) return [$middle, $bottom];
    return [$top, $middle, $bottom];
}

function rs_plate_flatten_catalog(string $catalog): string {
    $updated = preg_replace('~/AcroForm\s+3\s+0\s+R\s*~', '', $catalog, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        throw new RuntimeException('No se pudo retirar AcroForm de la plantilla de placa.');
    }
    return $updated;
}

function rs_plate_flatten_page(string $page): string {
    $updated = preg_replace('~/Annots\s*\[[^\]]*\]\s*~s', '', $page, 1, $countAnnots);
    if (!is_string($updated) || $countAnnots !== 1) {
        throw new RuntimeException('No se pudieron retirar los campos interactivos de la placa.');
    }

    $updated = preg_replace('~/Contents\s+17\s+0\s+R~', '/Contents [17 0 R 25 0 R]', $updated, 1, $countContents);
    if (!is_string($updated) || $countContents !== 1) {
        throw new RuntimeException('No se pudo agregar la capa fija de texto a la placa.');
    }

    $replacement = '/Resources << /XObject << /Im0 18 0 R /PlateName 22 0 R /PlateBirth 23 0 R /PlateDeath 24 0 R >> >>';
    $updated = preg_replace(
        '~/Resources\s*<<\s*/XObject\s*<<\s*/Im0\s+18\s+0\s+R\s*>>\s*>>~s',
        $replacement,
        $updated,
        1,
        $countResources
    );
    if (!is_string($updated) || $countResources !== 1) {
        throw new RuntimeException('No se pudieron registrar los elementos fijos de la placa.');
    }

    return $updated;
}

function rs_plate_fill_original_template(string $name,string $birth,string $death): string {
    $pdf=rs_plate_template_pdf();
    $widths=rs_plate_widths($pdf);
    $lines=rs_plate_name_lines($name,$widths);
    $ys=rs_plate_name_baselines(count($lines));

    $bbox=334.877;
    $ap="/Tx BMC\nq\nBT\n0 g\n";
    $px=0.0;
    $py=0.0;

    foreach($lines as $i=>$line){
        $x=($bbox-rs_plate_text_width($line,$widths,28.0))/2;
        $y=$ys[$i];
        if($i===0) {
            $ap.=sprintf("%.6F %.6F Td\n/PalatinoLinotype 28 Tf\n%s Tj\n",$x,$y,rs_plate_pdf_lit($line));
        } else {
            $ap.=sprintf("%.6F %.6F Td\n%s Tj\n",$x-$px,$y-$py,rs_plate_pdf_lit($line));
        }
        $px=$x;
        $py=$y;
    }
    $ap.="ET\nQ\nEMC\n";

    $bap="/Tx BMC\nq\n0 0 139.948608 29.021706 re\nW\nn\nBT\n0 g\n4.9223022 5.1378517 Td\n/F0 26 Tf\n".rs_plate_pdf_lit($birth)." Tj\nET\nQ\nEMC\n";
    $dap="/Tx BMC\nq\n0 0 139.948975 29.021706 re\nW\nn\nBT\n0 g\n4.9224854 5.1378517 Td\n/F0 26 Tf\n".rs_plate_pdf_lit($death)." Tj\nET\nQ\nEMC\n";

    // Quita los campos editables del PDF y pinta sus apariencias como contenido fijo.
    // Esto elimina el resaltado/recuadro azul que Chrome y Outlook muestran en AcroForm.
    $catalog=rs_plate_flatten_catalog(rs_plate_obj($pdf,2));
    $page=rs_plate_flatten_page(rs_plate_obj($pdf,11));

    if(!preg_match('~trailer\s*(<<.*?>>)\s*startxref\s*(\d+)\s*%%EOF\s*$~s',$pdf,$m)) {
        throw new RuntimeException('Trailer PDF no encontrado.');
    }

    $trailer=preg_replace('~/Prev\s+\d+~','',(string)$m[1])??(string)$m[1];
    $prev=(int)$m[2];
    $trailer=preg_replace('~/Size\s+\d+~','/Size 26',$trailer,1,$sizeCount)??$trailer;
    if($sizeCount===0) {
        $trailer=preg_replace('~>>$~','/Size 26>>',$trailer)??$trailer;
    }
    $trailer=preg_replace('~>>$~','/Prev '.$prev.'>>',$trailer)??$trailer;

    $append="\n";
    $off=[];

    foreach([[2,$catalog],[11,$page]] as [$n,$body]){
        $off[$n]=strlen($pdf)+strlen($append);
        $append.=$n." 0 obj\n".$body."\nendobj\n";
    }

    $nameDict='/BBox[ 0 0 334.877 95.07]/Filter/FlateDecode/FormType 1/Matrix[ 1 0 0 1 0 0]/Resources<</Font<</F0 19 0 R /PalatinoLinotype 7 0 R >>>>/Subtype/Form/Type/XObject';
    $dateDict='/BBox[ 0 0 139.949 29.0217]/Filter/FlateDecode/FormType 1/Matrix[ 1 0 0 1 0 0]/Resources<</Font<</F0 6 0 R >>>>/Subtype/Form/Type/XObject';

    foreach([[22,$nameDict,$ap],[23,$dateDict,$bap],[24,$dateDict,$dap]] as [$n,$dict,$stream]){
        $off[$n]=strlen($pdf)+strlen($append);
        $append.=rs_plate_stream_obj($n,$dict,$stream);
    }

    // Posiciones exactas de los tres campos en plantilla_placa_urna.pdf.
    $staticContent=
        "q\n1 0 0 1 63.9765 118.087 cm\n/PlateName Do\nQ\n".
        "q\n1 0 0 1 66.9754 83.0613 cm\n/PlateBirth Do\nQ\n".
        "q\n1 0 0 1 285.975 83.0613 cm\n/PlateDeath Do\nQ\n";

    $off[25]=strlen($pdf)+strlen($append);
    $append.=rs_plate_stream_obj(25,'/Filter/FlateDecode',$staticContent);

    $xref=strlen($pdf)+strlen($append);
    $append.="xref\n";
    $append.="2 1\n".sprintf("%010d 00000 n \n",$off[2]);
    $append.="11 1\n".sprintf("%010d 00000 n \n",$off[11]);
    $append.="22 4\n";
    for($n=22;$n<=25;$n++) {
        $append.=sprintf("%010d 00000 n \n",$off[$n]);
    }
    $append.="trailer\n".$trailer."\nstartxref\n".$xref."\n%%EOF\n";

    return $pdf.$append;
}
