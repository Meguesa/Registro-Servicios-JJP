<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');

require_once dirname(__DIR__).'/includes/registro-storage.php';
require_once dirname(__DIR__).'/includes/registro-sharepoint.php';

function pd_json(int $status,array $payload): never {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    exit;
}

function pd_norm(string $value): string {
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim($value));
    if(is_string($ascii)&&$ascii!=='')$value=$ascii;
    return preg_replace('/[^a-z0-9]+/','',strtolower($value))??'';
}

function pd_item_map(array $item): array {
    $out=[];
    foreach($item as $key=>$value){
        $out[pd_norm((string)$key)]=$value;
    }
    return $out;
}

function pd_pick(array $item,array $map,array $aliases,mixed $default=''): mixed {
    foreach($aliases as $alias){
        if(array_key_exists($alias,$item))return $item[$alias];
        $k=pd_norm((string)$alias);
        if($k!==''&&array_key_exists($k,$map))return $map[$k];
    }
    return $default;
}

function pd_text(mixed $value): string {
    if(is_array($value)){
        if(array_is_list($value))return implode(', ',array_map('strval',$value));
        return trim((string)($value['Value']??$value['value']??''));
    }
    return trim((string)$value);
}

function pd_bool(mixed $value): bool {
    if(is_bool($value))return $value;
    if(is_int($value)||is_float($value))return (int)$value===1;
    return in_array(pd_norm((string)$value),['1','si','true','yes'],true);
}

function pd_dt(mixed $value,bool $dateOnly=false): string {
    $raw=trim((string)$value);
    if($raw==='')return '';
    try{
        if($dateOnly){
            $dt=new DateTimeImmutable(substr($raw,0,10),new DateTimeZone('UTC'));
            return $dt->format('d/m/Y');
        }
        $dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('America/Monterrey'))->format('d/m/Y H:i');
    }catch(Throwable){
        return $raw;
    }
}

function pd_request(string $url,string $token): array {
    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible iniciar la consulta.');
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$token,
            'Accept: application/json;odata=nometadata',
        ],
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);
    if($response===false)throw new RuntimeException('SharePoint no respondio: '.$error);
    $decoded=json_decode((string)$response,true);
    if($status<200||$status>=300||!is_array($decoded)){
        throw new RuntimeException('SharePoint respondio HTTP '.$status.'.');
    }
    return $decoded;
}

function pd_capillas(array $item): array {
    $m=pd_item_map($item);
    $service=pd_text(pd_pick($item,$m,['field_32','Servicio']));
    $prevision=pd_text(pd_pick($item,$m,['field_9','PrevisionUsoInmediato']));
    $codigo=pd_text(pd_pick($item,$m,['field_46','Codigo']));

    $extras=pd_pick($item,$m,['ServiciosExtra'],[]);
    if(!is_array($extras)){
        $extras=array_values(array_filter(array_map('trim',preg_split('/[;,]+/',(string)$extras)?:[])));
    }

    $extraFields=[
        'Misa y Coro'=>['Misa_x0020_y_x0020_Coro','Misa y Coro'],
        'Flores'=>['Flores'],
        'Cobro por Enfermedad'=>['Cobro_x0020_por_x0020_Enfermedad','Cobro por Enfermedad'],
        'Horas Extras'=>['Horas_x0020_Extras','Horas Extras'],
        'Cambio de Ataud'=>['Cambio_x0020_de_x0020_Ataud','Cambio de Ataud'],
        'Cambio a Cremacion'=>['Cambio_x0020_a_x0020_Cremacion','Cambio a Cremacion'],
        'Cambio de Capilla'=>['Cambio_x0020_de_x0020_Capilla','Cambio de Capilla'],
        'Cambio de Urna'=>['Cambio_x0020_de_x0020_Urna','Cambio de Urna'],
        'Traslado'=>['Traslado'],
        'Resguardo'=>['Resguardo'],
        'Retiro de Marcapasos'=>['Retiro_x0020_de_x0020_Marcapasos','Retiro de Marcapasos'],
        'Sobrepeso'=>['Sobrepeso'],
        'Destape'=>['Destape'],
        'Impuestos'=>['Impuestos'],
    ];
    $amounts=[];
    foreach($extraFields as $label=>$aliases){
        $v=pd_pick($item,$m,$aliases,null);
        $amounts[$label]=($v===null||$v==='')?null:(float)$v;
    }

    return [
        '_area'=>'capillas','area'=>'capillas',
        'numeroReferencia'=>pd_text(pd_pick($item,$m,['field_1'])),
        'servicio'=>$service,
        'ubicacion'=>pd_text(pd_pick($item,$m,['field_39'])),
        'sala'=>pd_text(pd_pick($item,$m,['field_40'])),
        'inicio'=>pd_dt(pd_pick($item,$m,['field_36'])),
        'termino'=>pd_dt(pd_pick($item,$m,['field_37'])),
        'llevaExequia'=>pd_bool(pd_pick($item,$m,['Misa'],false)),
        'tiempoCapillas'=>pd_text(pd_pick($item,$m,['field_45'])),
        'horaExequia'=>pd_dt(pd_pick($item,$m,['field_44'])),
        'prevision'=>$prevision,
        'previsionViOpcionCremacion'=>pd_norm($prevision)==='prevision'&&pd_norm($service)==='cremacion'&&pd_norm($codigo)==='vi',
        'tipoAtaud'=>pd_text(pd_pick($item,$m,['field_49'])),
        'numeroServicio'=>pd_text(pd_pick($item,$m,['field_48'])),
        'codigoServicio'=>$codigo,
        'codigoAtaud'=>pd_text(pd_pick($item,$m,['field_47'])),
        'referencia'=>pd_text(pd_pick($item,$m,['Referencia'])),
        'requierePlaca'=>pd_bool(pd_pick($item,$m,['RequierePlacadeUrna_x003f_'],false)),
        'titular'=>pd_text(pd_pick($item,$m,['field_7'])),
        'prefijo'=>pd_text(pd_pick($item,$m,['Prefijo','Trato','Tratamiento'])),
        'fallecido'=>pd_text(pd_pick($item,$m,['field_2'])),
        'fechaNacimiento'=>pd_dt(pd_pick($item,$m,['field_4']),true),
        'fechaDefuncion'=>pd_dt(pd_pick($item,$m,['FechaDefuncion'])),
        'sexo'=>pd_text(pd_pick($item,$m,['Sexo'])),
        'edad'=>pd_text(pd_pick($item,$m,['field_3'])),
        'destinoFinal'=>pd_text(pd_pick($item,$m,['field_52'])),
        'embalsamador'=>pd_text(pd_pick($item,$m,['field_69'])),
        'rescate1'=>pd_text(pd_pick($item,$m,['field_28'])),
        'rescate2'=>pd_text(pd_pick($item,$m,['field_29'])),
        'ubicacionRescate'=>pd_text(pd_pick($item,$m,['field_31'])),
        'motivo'=>pd_text(pd_pick($item,$m,['field_30'])),
        'referenciaCrematorio'=>pd_text(pd_pick($item,$m,['field_64'])),
        'inicioCrematorio'=>pd_dt(pd_pick($item,$m,['FechayHoraInicioCrematorio'])),
        'personalCrematorio'=>pd_text(pd_pick($item,$m,['field_68'])),
        'fechaHoraInhumacion'=>pd_dt(pd_pick($item,$m,['FechayHoraInhumaci_x00f3_n'])),
        'fechaCompra'=>pd_dt(pd_pick($item,$m,['field_10']),true),
        'personalVenta'=>pd_text(pd_pick($item,$m,['field_11'])),
        'precioVenta'=>pd_pick($item,$m,['field_12'],null),
        'serviciosExtra'=>array_values(array_filter(array_map('strval',$extras),static fn(string $v):bool=>trim($v)!==''&&pd_norm($v)!=='noaplica')),
        'extraAmounts'=>$amounts,
        'ventaTotal'=>pd_pick($item,$m,['field_27'],null),
    ];
}

function pd_parque(array $item): array {
    $m=pd_item_map($item);
    $inicio=pd_dt(pd_pick($item,$m,['FechaHoraInicio']));
    $fin=pd_dt(pd_pick($item,$m,['FechaHoraFin']));
    $split=static function(string $v):array{
        if($v==='')return ['',''];
        $p=preg_split('/\s+/',trim($v),2)?:[];
        return [$p[0]??'',$p[1]??''];
    };
    [$fechaInicio,$horaInicio]=$split($inicio);
    [$fechaFin,$horaFin]=$split($fin);

    return [
        '_area'=>'parque','area'=>'parque',
        'fechaInicio'=>$fechaInicio,'horaInicio'=>$horaInicio,
        'fechaFin'=>$fechaFin,'horaFin'=>$horaFin,
        'fechaHoraInicio'=>$inicio,'fechaHoraFin'=>$fin,
        'velacion'=>pd_text(pd_pick($item,$m,['Velacion'])),
        'previsionUsoInmediato'=>pd_text(pd_pick($item,$m,['PrevisionUsoInmediato'])),
        'tipoServicio'=>pd_text(pd_pick($item,$m,['TipodeServicio'])),
        'servicio'=>pd_text(pd_pick($item,$m,['Servicio'])),
        'asistenteFunerarioTexto'=>pd_text(pd_pick($item,$m,['AsistenteFunerarioTexto'])),
        'numeroContrato'=>pd_text(pd_pick($item,$m,['Numero_x0020_de_x0020_Contrato'])),
        'seccion'=>pd_text(pd_pick($item,$m,['Seccion'])),
        'manzana'=>pd_text(pd_pick($item,$m,['Manzana'])),
        'numLoteNicho'=>pd_text(pd_pick($item,$m,['NumLote_x002f_Nicho','NumLoteNicho'])),
        'destape'=>pd_text(pd_pick($item,$m,['Destape'])),
        'tipoPlaca'=>pd_text(pd_pick($item,$m,['TipoPlaca','PlacaParqueTipo'])),
        'nombreFamilia'=>pd_text(pd_pick($item,$m,['NombreFamilia'])),
        'requiereCambioUrna'=>pd_bool(pd_pick($item,$m,['RequiereCambioUrna'],false)),
        'titular'=>pd_text(pd_pick($item,$m,['Titular'])),
        'fallecido'=>pd_text(pd_pick($item,$m,['TitularSubstituto'])),
        'parentescoTitular'=>pd_text(pd_pick($item,$m,['ParentescoTitular'])),
        'frase'=>pd_text(pd_pick($item,$m,['Frase'])),
        'fechaNacimiento'=>pd_dt(pd_pick($item,$m,['FechaNacimiento']),true),
        'fechaDefuncion'=>pd_dt(pd_pick($item,$m,['FechaDefuncion']),true),
        'estatusLiquidacion'=>pd_text(pd_pick($item,$m,['EstatusLiquidacion'])),
        'requiereReubicacion'=>pd_bool(pd_pick($item,$m,['RequiereReubicacion'],false)),
        'seccionNueva'=>pd_text(pd_pick($item,$m,['SeccionNueva'])),
        'manzanaNueva'=>pd_text(pd_pick($item,$m,['ManzanaNueva'])),
        'numLoteNichoNuevo'=>pd_text(pd_pick($item,$m,['NumLoteNichoNuevo'])),
        'ubicacionNueva'=>pd_text(pd_pick($item,$m,['UbicacionNueva'])),
        'motivoReubicacion'=>pd_text(pd_pick($item,$m,['MotivoReubicacion'])),
        'observaciones'=>pd_text(pd_pick($item,$m,['Observaciones'])),
    ];
}

try{
    rs_storage_bootstrap();

    $area=pd_norm((string)($_GET['area']??'capillas'))==='parque'?'parque':'capillas';
    $itemId=(int)($_GET['id']??0);
    if($itemId<=0)pd_json(400,['ok'=>false,'message'=>'ID no valido.']);

    $config=rs_sharepoint_config();
    $host='meguesajdjp.sharepoint.com';
    $siteUrl='https://'.$host.'/sites/Operaciones';
    $token=rs_sharepoint_token($config,$host);
    $listTitle=$area==='parque'?'Eventos Parque':'Eventos Capillas';
    $listEsc=rawurlencode($listTitle);

    $item=pd_request(
        $siteUrl."/_api/web/lists/getbytitle('".$listEsc."')/items(".$itemId.")?$select=*",
        $token
    );
    if(isset($item['d'])&&is_array($item['d']))$item=$item['d'];
    elseif(isset($item['value'][0])&&is_array($item['value'][0]))$item=$item['value'][0];

    $payload=$area==='parque'?pd_parque($item):pd_capillas($item);

    // Vista y descarga de adjuntos y esquelas. Solo operaciones GET y lectura.
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET'
        && (isset($_GET['documentos']) || isset($_GET['documento']))) {
        require_once dirname(__DIR__).'/includes/registro-publicado-archivos.php';
        $storedFiles=pd_files_list($siteUrl,$listEsc,$itemId,$token);
        $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $query='?area='.rawurlencode($area).'&id='.$itemId;

        $tellmebyeFiles=[];
        if($area==='capillas') {
            foreach(['TellmebyeJpgVerticalNombre','TellmebyePdfVerticalNombre'] as $column) {
                $name=trim((string)($item[$column]??''));
                if(pd_files_valid_name($name))$tellmebyeFiles[]=$name;
            }
        }

        if(isset($_GET['documento'])) {
            $fileType=trim((string)($_GET['tipo']??'adjunto'));
            $fileName=trim((string)($_GET['documento']??''));
            $download=isset($_GET['descargar']);
            if($fileType==='adjunto') {
                // Solo archivos cuyos nombres figuran en este mismo item SharePoint.
                $allowed=false;
                foreach($storedFiles as $file){
                    if(hash_equals($file['name'],$fileName)){$allowed=true;break;}
                }
                if(!$allowed)pd_json(404,['ok'=>false,'message'=>'El adjunto no existe para este servicio.']);
                $bytes=pd_files_attachment_bytes($siteUrl,$listEsc,$itemId,$fileName,$token);
                pd_files_stream($fileName,$bytes,$download);
            }
            if($fileType==='tellmebye'&&$area==='capillas') {
                if(!in_array($fileName,$tellmebyeFiles,true)){
                    pd_json(404,['ok'=>false,'message'=>'La cartulina solicitada no existe en este registro.']);
                }
                $bytes=pd_files_cartulina_bytes($siteUrl,$fileName,$token);
                pd_files_stream($fileName,$bytes,$download);
            }
            if($fileType==='esquela-local'&&$area==='capillas'
                &&$fileName==='Esquela_'.$itemId.'.jpg') {
                $generated=pd_files_local_esquela($payload,$storedFiles,$siteUrl,$listEsc,$itemId,$token);
                $bytes=(string)($generated['jpeg']??'');
                if($bytes==='')throw new RuntimeException('La esquela generada no tiene contenido.');
                pd_files_stream($fileName,$bytes,$download);
            }
            pd_json(400,['ok'=>false,'message'=>'Tipo de documento no permitido.']);
        }

        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; frame-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'");
        header('X-Frame-Options: SAMEORIGIN');
        $areaLabel=$area==='parque'?'Parque':'Capillas';
        $fileLink=static function(string $type,string $name)use($query):string{
            return 'publicado-detalle.php'.$query.'&tipo='.rawurlencode($type).'&documento='.rawurlencode($name);
        };
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Documentos · '.$escape($areaLabel).' · ID '.$itemId.'</title>';
        echo '<style>body{background:#f7f7f7;font:16px Arial,sans-serif;color:#292522;margin:0}main{max-width:1100px;margin:0 auto;padding:24px}header{margin-bottom:18px}h1{margin:0 0 8px}p{color:#555;line-height:1.5}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}.file{background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px;overflow:hidden}.file h2{font-size:18px;overflow-wrap:anywhere;margin:0 0 12px}.file img{display:block;width:100%;height:auto;max-height:500px;object-fit:contain;background:#f5f5f5}.file iframe{width:100%;height:430px;border:1px solid #ddd}.actions{display:flex;flex-wrap:wrap;gap:14px;margin:12px 0}a{color:#185c8e;font-weight:600}.note{background:#fff4db;border:1px solid #e9c985;padding:12px;border-radius:8px;margin:14px 0}@media print{body{background:white}main{padding:0}.actions{display:none}.file{page-break-inside:avoid}}</style>';
        echo '</head><body><main><header><h1>Documentos y esquelas · '.$escape($areaLabel).' · ID '.$itemId.'</h1>';
        echo '<p>Archivos disponibles en SharePoint, independientes de los correos. No se modifican los servicios ni se ejecuta TellMeBye.</p>';
        echo '<p><a href="publicado-detalle.php'.$escape($query).'&tablas=1">Volver a las tablas</a></p></header>';

        // La esquela del Registro de Servicios se construye desde los campos
        // de SharePoint; las cartulinas de TellMeBye no son la esquela operativa.
        // Mostrar primero la JPG original guardada, si existe. Los registros
        // anteriores al respaldo conservan la opcion de regenerarla con GD.
        $entries=[];
        $savedEsquelaName='Esquela_'.$itemId.'.jpg';
        $hasSavedLocalEsquela=false;
        foreach($storedFiles as $file){
            if(strcasecmp((string)$file['name'],$savedEsquelaName)===0){
                $savedEsquelaName=(string)$file['name'];
                $hasSavedLocalEsquela=true;
                break;
            }
        }
        if($area==='capillas'){
            if($hasSavedLocalEsquela){
                $entries[]=['tipo'=>'adjunto','nombre'=>$savedEsquelaName,'titulo'=>'Esquela JJP generada por Registro de Servicios (original guardado)'];
            }else{
                $entries[]=['tipo'=>'esquela-local','nombre'=>'Esquela_'.$itemId.'.jpg','titulo'=>'Esquela JJP generada con datos de SharePoint (reconstruccion)'];
            }
        }
        foreach($storedFiles as $file){
            if($area==='capillas'&&$hasSavedLocalEsquela&&$file['name']===$savedEsquelaName)continue;
            $entries[]=['tipo'=>'adjunto','nombre'=>$file['name'],'titulo'=>'Documento adjunto del servicio'];
        }

        if($entries===[])echo '<div class="note">No se encontraron archivos almacenados para este servicio.</div>';
        if($area==='capillas'){
            echo $hasSavedLocalEsquela
                ? '<p class="note">Esquela original creada por Registro de Servicios, guardada como adjunto en SharePoint. No se usa la cartulina de TellMeBye.</p>'
                : '<p class="note">Este registro es anterior al respaldo de esquelas: se reconstruye la esquela JJP con los datos actuales de SharePoint y su fotografía adjunta, si existe. Si falta la foto, se usa la imagen de respaldo. El QR o ciertos elementos pueden diferir del archivo generado en la captura original. No se utiliza la cartulina de TellMeBye.</p>';
        }
        echo '<div class="grid">';
        foreach($entries as $entry){
            $url=$fileLink($entry['tipo'],$entry['nombre']);
            $downloadUrl=$url.'&descargar=1';
            $name=$escape($entry['nombre']);
            echo '<section class="file"><h2>'.$escape($entry['titulo']).'</h2><p>'.$name.'</p>';
            if(pd_files_is_image($entry['nombre'])){
                echo '<img src="'.$escape($url).'" alt="'.$name.'" loading="lazy">';
            }elseif(pd_files_mime($entry['nombre'])==='application/pdf'){
                echo '<iframe title="'.$name.'" src="'.$escape($url).'" loading="lazy"></iframe>';
            }else{
                echo '<p>Este formato no admite vista previa en el navegador.</p>';
            }
            echo '<div class="actions"><a href="'.$escape($url).'" target="_blank" rel="noopener">Abrir</a>';
            echo '<a href="'.$escape($downloadUrl).'">Descargar</a></div></section>';
        }
        echo '</div></main></body></html>';
        exit;
    }


    // Consultar o descargar las tablas existentes SIN enviar correo ni modificar SharePoint.
    // Se regeneran a partir del elemento publicado y las mismas funciones de imagen
    // utilizadas por los correos operativos de Capillas y Parque.
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET'
        && (isset($_GET['tablas']) || isset($_GET['tabla']))) {
        $tableNames=$area==='parque'
            ? [
                'servicio'=>['Informacion_Servicio.png','Información del servicio'],
                'propiedad'=>['Informacion_Propiedad.png','Información de propiedad'],
                'fallecido'=>['Informacion_Fallecido.png','Información del fallecido'],
            ]
            : [
                'servicio'=>['Informacion_Servicio.png','Información del servicio'],
                'obituario'=>['Obituario.png','Obituario'],
                'venta'=>['Informacion_Venta.png','Información de venta'],
            ];

        if(isset($_GET['tablas'])) {
            header('Content-Type: text/html; charset=utf-8');
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'");
            header('X-Frame-Options: SAMEORIGIN');
            $query='?area='.rawurlencode($area).'&id='.$itemId;
            $escape=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $title=$area==='parque'?'Parque':'Capillas';
            echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
            echo '<title>Tablas de '.$escape($title).' · ID '.$itemId.'</title>';
            echo '<style>body{margin:0;background:#f7f7f7;font:16px Arial,sans-serif;color:#292522}main{max-width:1150px;margin:0 auto;padding:24px}h1{margin:0 0 8px;font-size:25px}p{line-height:1.5;color:#555}section{background:white;border:1px solid #ddd;border-radius:12px;padding:16px;margin:18px 0;page-break-inside:avoid}section h2{font-size:19px;margin:0}section header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px}img{display:block;width:100%;height:auto;border:1px solid #ddd}a{color:#155c91;font-weight:600}footer{margin:24px 0;color:#555}@media print{body{background:white}main{padding:0}a{display:none}section{border:0;padding:0;page-break-inside:avoid}}</style>';
            echo '</head><body><main><h1>Tablas · '.$escape($title).' · ID '.$itemId.'</h1>';
            echo '<p>Regeneradas con la información actualmente guardada en SharePoint. No se enviarán correos, no se generarán nuevos servicios y no se ejecutará TellMeBye.</p>';
            echo '<p><a href="publicado-detalle.php'.$escape($query).'&documentos=1">Ver esquelas y documentos adjuntos</a></p>';
            foreach($tableNames as $key=>$meta) {
                $view='publicado-detalle.php'.$query.'&tabla='.rawurlencode($key);
                $download=$view.'&descargar=1';
                echo '<section><header><h2>'.$escape($meta[1]).'</h2><a href="'.$escape($download).'">Descargar PNG</a></header>';
                echo '<img src="'.$escape($view).'" alt="'.$escape($meta[1]).'" loading="eager"></section>';
            }
            echo '<footer>También puedes imprimir o guardar esta vista como PDF desde tu navegador (Ctrl+P).</footer></main></body></html>';
            exit;
        }

        $tableKey=mb_strtolower(trim((string)($_GET['tabla']??'')),'UTF-8');
        if(!isset($tableNames[$tableKey]))pd_json(400,['ok'=>false,'message'=>'Tabla no válida para esta área.']);
        if($area==='parque') {
            require_once dirname(__DIR__).'/includes/registro-parque-imagenes.php';
            $allTables=rp_generate_information_images($payload);
            $index=['servicio'=>0,'propiedad'=>1,'fallecido'=>2][$tableKey];
            $bytes=(string)($allTables[$index]['bytes']??'');
        } else {
            require_once dirname(__DIR__).'/includes/registro-imagenes.php';
            $allTables=rs_generate_service_information_images($payload);
            $bytesKey=['servicio'=>'servicePng','obituario'=>'obitPng','venta'=>'salePng'][$tableKey];
            $bytes=(string)($allTables[$bytesKey]??'');
        }
        if($bytes==='')throw new RuntimeException('No se pudo regenerar la imagen solicitada.');
        header('Content-Type: image/png');
        $attachment=isset($_GET['descargar'])?'attachment':'inline';
        header('Content-Disposition: '.$attachment.'; filename="'.$itemId.'-'.$tableNames[$tableKey][0].'"');
        header('Content-Length: '.strlen($bytes));
        echo $bytes;
        exit;
    }

    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='POST'){
        if($area==='parque'){
            require_once dirname(__DIR__).'/includes/registro-parque-imagenes.php';
            require_once dirname(__DIR__).'/includes/registro-parque-correo.php';
            require_once dirname(__DIR__).'/includes/registro-parque-documentos.php';

            $info=rp_generate_information_images($payload);

            // El reenvio no debe fallar completo si una placa histórica no puede
            // regenerarse. Se envia el correo principal con el resto de adjuntos
            // y se reporta la placa como aviso independiente.
            $operational=[];
            $operationalError=null;
            try{
                $operational=rp_generate_operational_tables($payload);
            }catch(Throwable $operationalException){
                $operationalError=$operationalException->getMessage();
                error_log('Reenvio Parque adjuntos operativos item '.$itemId.': '.$operationalError);
            }

            $letters=[];
            try{
                $letters=rp_generate_letter_attachments($payload);
            }catch(Throwable $letterException){
                error_log('Reenvio Parque cartas item '.$itemId.': '.$letterException->getMessage());
            }

            $serviceLetter=[];
            try{
                $letterPayload=$payload;
                $letterPayload['itemId']=(string)$itemId;
                $generatedLetter=rp_generate_service_letter($letterPayload);
                if(is_array($generatedLetter)){
                    $serviceLetter[]=$generatedLetter;
                }
            }catch(Throwable $serviceLetterException){
                error_log(
                    'Reenvio Parque Carta Servicio Otorgado item '.$itemId
                    .': '.$serviceLetterException->getMessage()
                );
            }

            $attachments=array_merge($info,$operational,$letters,$serviceLetter);
            $email=rp_send_service_email($payload,$attachments,false);

            $plateEmails=[];
            if($operationalError!==null){
                $plateEmails[]=[
                    'sent'=>false,
                    'kind'=>'nicho',
                    'error'=>$operationalError,
                ];
            }
            foreach($operational as $attachment){
                if(!is_array($attachment))continue;
                $name=mb_strtolower(trim((string)($attachment['name']??'')),'UTF-8');
                $kind=$name==='placa_nicho.png'?'nicho':($name==='placa_urna.png'?'urna':null);
                if($kind===null)continue;
                try{
                    $plateEmails[]=rp_send_plate_email($payload,$attachment,$kind);
                }catch(Throwable $plateError){
                    $plateEmails[]=['sent'=>false,'kind'=>$kind,'error'=>$plateError->getMessage()];
                }
            }

            pd_json(200,['ok'=>true,'area'=>$area,'itemId'=>$itemId,'email'=>$email,'plateEmails'=>$plateEmails]);
        }

        require_once dirname(__DIR__).'/includes/registro-imagenes.php';
        require_once dirname(__DIR__).'/includes/registro-placa.php';
        require_once dirname(__DIR__).'/includes/registro-carta.php';
        require_once dirname(__DIR__).'/includes/registro-capillas-correo.php';
        require_once dirname(__DIR__).'/includes/registro-tellmebye.php';

        $attachments=[];
        $plateAttachment=null;

        try{
            $images=rs_generate_service_information_images($payload);
            foreach([
                ['nameKey'=>'serviceName','bytesKey'=>'servicePng'],
                ['nameKey'=>'obitName','bytesKey'=>'obitPng'],
                ['nameKey'=>'saleName','bytesKey'=>'salePng'],
            ] as $part){
                $name=trim((string)($images[$part['nameKey']]??''));
                $bytes=(string)($images[$part['bytesKey']]??'');
                if($name!==''&&$bytes!==''){
                    $attachments[]=['name'=>$name,'contentType'=>'image/png','bytes'=>$bytes];
                }
            }
        }catch(Throwable $imageError){
            error_log('Reenvio Capillas imagenes item '.$itemId.': '.$imageError->getMessage());
        }

        try{
            $letterPayload=$payload;
            $letterPayload['itemId']=(string)$itemId;
            $letter=rs_generate_service_letter($letterPayload);
            $name=trim((string)($letter['pdfName']??''));
            $bytes=(string)($letter['pdf']??'');
            if($name!==''&&$bytes!==''){
                $attachments[]=['name'=>$name,'contentType'=>'application/pdf','bytes'=>$bytes];
            }
        }catch(Throwable $letterError){
            error_log('Reenvio Capillas carta item '.$itemId.': '.$letterError->getMessage());
        }

        $serviceNorm=pd_norm((string)($payload['servicio']??''));
        $plateRequired=!empty($payload['requierePlaca'])
            || str_contains($serviceNorm,'cremacion')
            || str_contains($serviceNorm,'cremaciondirecta');
        if($plateRequired){
            try{
                $platePayload=$payload;
                $platePayload['itemId']=(string)$itemId;
                $plate=rs_generate_urna_plate($platePayload);
                $name=trim((string)($plate['pngName']??''));
                $bytes=(string)($plate['png']??'');
                if($name!==''&&$bytes!==''){
                    $plateAttachment=['name'=>$name,'contentType'=>'image/png','bytes'=>$bytes];
                    $attachments[]=$plateAttachment;
                }
            }catch(Throwable $plateError){
                error_log('Reenvio Capillas placa item '.$itemId.': '.$plateError->getMessage());
            }
        }

        $email=rc_send_service_email($payload,$attachments);
        $plateEmails=[];
        if(is_array($plateAttachment)){
            try{
                $plateEmails[]=rc_send_plate_email($payload,$plateAttachment);
            }catch(Throwable $plateError){
                $plateEmails[]=['sent'=>false,'kind'=>'urna','error'=>$plateError->getMessage()];
            }
        }

        // Reenvio de correo exclusivamente: no activar TellMeBye ni alterar su estado.
        // La publicacion de obituarios tiene su propia operacion independiente.
        $tellmebye=[
            'enabled'=>false,
            'triggered'=>false,
            'skipped'=>true,
            'reason'=>'Reenvio de correo: TellMeBye no se ejecuta.',
        ];

        pd_json(200,[
            'ok'=>true,
            'area'=>$area,
            'itemId'=>$itemId,
            'email'=>$email,
            'plateEmails'=>$plateEmails,
            'tellmebye'=>$tellmebye,
        ]);
    }

    pd_json(200,['ok'=>true,'area'=>$area,'itemId'=>$itemId,'payload'=>$payload]);
}catch(Throwable $e){
    error_log('Publicado detalle v2: '.$e->getMessage());
    $isResend=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='POST';
    $isGraphTransient=$isResend && preg_match('/Microsoft Graph.*HTTP (429|502|503|504)\\b/i',$e->getMessage())===1;
    if($isGraphTransient){
        // Un 5xx en sendMail deja la entrega incierta: nunca reintentar automaticamente.
        pd_json(503,[
            'ok'=>false,
            'message'=>'Microsoft Graph/Exchange no pudo confirmar el envio. El servicio sigue registrado. Verifica Elementos enviados y seguimiento de mensajes antes de reintentar para evitar duplicados. Detalle: '.$e->getMessage(),
            'mailStatus'=>'unknown',
            'retryAutomatically'=>false,
        ]);
    }
    pd_json(500,[
        'ok'=>false,
        'message'=>($isResend?'No fue posible completar el reenvio de correos: ':'No fue posible cargar el servicio: ').$e->getMessage(),
    ]);
}
