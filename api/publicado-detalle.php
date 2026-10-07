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

    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='POST'){
        if($area!=='parque')pd_json(400,['ok'=>false,'message'=>'Reenvio solo disponible para Parque.']);

        require_once dirname(__DIR__).'/includes/registro-parque-imagenes.php';
        require_once dirname(__DIR__).'/includes/registro-parque-correo.php';
        require_once dirname(__DIR__).'/includes/registro-parque-documentos.php';

        $info=rp_generate_information_images($payload);
        $operational=rp_generate_operational_tables($payload);
        $letters=rp_generate_letter_attachments($payload);
        $attachments=array_merge($info,$operational,$letters);
        $email=rp_send_service_email($payload,$attachments,false);

        $plateEmails=[];
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

    pd_json(200,['ok'=>true,'area'=>$area,'itemId'=>$itemId,'payload'=>$payload]);
}catch(Throwable $e){
    error_log('Publicado detalle v2: '.$e->getMessage());
    pd_json(500,['ok'=>false,'message'=>'No fue posible cargar el servicio: '.$e->getMessage()]);
}
