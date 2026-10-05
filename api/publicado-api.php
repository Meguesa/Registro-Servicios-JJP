<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__).'/includes/registro-storage.php';
require_once dirname(__DIR__).'/includes/registro-sharepoint.php';

function pe_json(int $status,array $payload): never {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function pe_norm(string $value): string {
    $value=trim($value);
    $value=preg_replace_callback('/_x([0-9a-fA-F]{4})_/',static function(array $m):string{
        $code=hexdec($m[1]);
        if($code<=0x7F)return chr($code);
        return html_entity_decode('&#'.$code.';',ENT_QUOTES|ENT_HTML5,'UTF-8');
    },$value)??$value;
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
    if(is_string($ascii)&&$ascii!=='')$value=$ascii;
    return preg_replace('/[^a-z0-9]+/','',strtolower($value))??'';
}

function pe_fields_index(array $rows): array {
    $out=[];
    foreach($rows as $row){
        if(!is_array($row))continue;
        foreach([(string)($row['Title']??''),(string)($row['InternalName']??'')] as $candidate){
            $key=pe_norm($candidate);
            if($key!==''&&!isset($out[$key]))$out[$key]=$row;
        }
    }
    return $out;
}

function pe_internal(array $index,array $aliases): ?string {
    foreach($aliases as $alias){
        $key=pe_norm((string)$alias);
        if($key!==''&&isset($index[$key])){
            $name=trim((string)($index[$key]['InternalName']??''));
            if($name!=='')return $name;
        }
    }
    return null;
}

function pe_value(array $item,array $index,array $aliases,mixed $default=''): mixed {
    // 1) Intentar primero contra las claves reales devueltas por SharePoint.
    // Esto cubre aliases que ya son InternalName (field_1, FechaHoraInicio, etc.).
    foreach($aliases as $alias){
        $alias=(string)$alias;
        if($alias!=='' && array_key_exists($alias,$item)){
            return $item[$alias];
        }
    }

    // 2) Comparar de forma normalizada las claves del elemento. Asi soportamos
    // InternalName codificados (_x0020_, _x00f3_, etc.) sin depender del titulo.
    $itemByNorm=[];
    foreach($item as $key=>$value){
        $norm=pe_norm((string)$key);
        if($norm!=='' && !array_key_exists($norm,$itemByNorm)){
            $itemByNorm[$norm]=$value;
        }
    }
    foreach($aliases as $alias){
        $norm=pe_norm((string)$alias);
        if($norm!=='' && array_key_exists($norm,$itemByNorm)){
            return $itemByNorm[$norm];
        }
    }

    // 3) Resolver por el catalogo de columnas para aliases que son nombres
    // visibles (Title) y no InternalName.
    $internal=pe_internal($index,$aliases);
    if($internal!==null){
        if(array_key_exists($internal,$item))return $item[$internal];
        $norm=pe_norm($internal);
        if($norm!=='' && array_key_exists($norm,$itemByNorm)){
            return $itemByNorm[$norm];
        }
    }

    return $default;
}

function pe_text(mixed $value): string {
    if(is_array($value)){
        if(array_is_list($value))return implode(', ',array_map('strval',$value));
        return trim((string)($value['Value']??$value['value']??''));
    }
    return trim((string)$value);
}

function pe_bool(mixed $value): bool {
    if(is_bool($value))return $value;
    if(is_int($value)||is_float($value))return (int)$value===1;
    $v=pe_norm((string)$value);
    return in_array($v,['1','si','true','yes'],true);
}

function pe_dt(mixed $value,bool $dateOnly=false): string {
    $raw=trim((string)$value);
    if($raw==='')return '';
    try{
        if($dateOnly){
            $datePart=substr($raw,0,10);
            $dt=DateTimeImmutable::createFromFormat('Y-m-d',$datePart,new DateTimeZone('UTC'));
            return $dt instanceof DateTimeImmutable?$dt->format('d/m/Y'):$raw;
        }
        $dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));
        $dt=$dt->setTimezone(new DateTimeZone('America/Monterrey'));
        return $dt->format('d/m/Y H:i');
    }catch(Throwable){
        return $raw;
    }
}

function pe_request(string $url,string $token): array {
    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible iniciar la consulta de SharePoint.');
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
        throw new RuntimeException('SharePoint respondio HTTP '.$status.' al cargar el registro publicado.');
    }
    return $decoded;
}

function pe_capillas_payload(array $item,array $fields): array {
    $service=pe_text(pe_value($item,$fields,['field_32','Servicio','Tipo de Servicio']));
    $prevision=pe_text(pe_value($item,$fields,['field_9','Prevision/Uso Inmediato','Previsión/Uso Inmediato']));
    $codigo=pe_text(pe_value($item,$fields,['field_46','Codigo','Código','Codigo de Servicio','Código de Servicio']));

    $extras=pe_value($item,$fields,['ServiciosExtra','Servicios Extra','Servicios Adicionales'],[]);
    if(!is_array($extras)){
        $extras=array_values(array_filter(array_map('trim',preg_split('/[;,]+/',(string)$extras)?:[])));
    }

    $extraAliases=[
        'Misa y Coro'=>['Misa y Coro'],
        'Flores'=>['Flores'],
        'Cobro por Enfermedad'=>['Cobro por Enfermedad'],
        'Horas Extras'=>['Horas Extras'],
        'Cambio de Ataud'=>['Cambio de Ataud','Cambio de Ataúd'],
        'Cambio a Cremacion'=>['Cambio a Cremacion','Cambio a Cremación'],
        'Cambio de Capilla'=>['Cambio de Capilla'],
        'Cambio de Urna'=>['Cambio de Urna'],
        'Traslado'=>['Traslado'],
        'Resguardo'=>['Resguardo'],
        'Retiro de Marcapasos'=>['Retiro de Marcapasos'],
        'Sobrepeso'=>['Sobrepeso'],
        'Destape'=>['Destape'],
        'Impuestos'=>['Impuestos'],
    ];
    $amounts=[];
    foreach($extraAliases as $label=>$aliases){
        $value=pe_value($item,$fields,$aliases,null);
        $amounts[$label]=($value===null||$value==='')?null:(float)$value;
    }

    return [
        '_area'=>'capillas','area'=>'capillas',
        'numeroReferencia'=>pe_text(pe_value($item,$fields,['field_1','Numero de Referencia','Número de Referencia'])),
        'servicio'=>$service,
        'ubicacion'=>pe_text(pe_value($item,$fields,['field_39','Ubicación Servicio Capillas','Ubicacion Servicio Capillas'])),
        'sala'=>pe_text(pe_value($item,$fields,['field_40','Sala'])),
        'inicio'=>pe_dt(pe_value($item,$fields,['field_36','Fecha y Hora Inicio'])),
        'termino'=>pe_dt(pe_value($item,$fields,['field_37','Fecha y Hora Termino','Fecha y Hora Término'])),
        'llevaExequia'=>pe_bool(pe_value($item,$fields,['Misa','Lleva exequia','Lleva exequia?'],false)),
        'tiempoCapillas'=>pe_text(pe_value($item,$fields,['field_45','Tiempo de Capillas'])),
        'horaExequia'=>pe_dt(pe_value($item,$fields,['field_44','Hora Misa','Fecha y Hora Exequia'])),
        'prevision'=>$prevision,
        'previsionViOpcionCremacion'=>pe_norm($prevision)==='prevision' && pe_norm($service)==='cremacion' && pe_norm($codigo)==='vi',
        'tipoAtaud'=>pe_text(pe_value($item,$fields,['field_49','Tipo de Ataud/Urna','Tipo de Ataúd/Urna'])),
        'numeroServicio'=>pe_text(pe_value($item,$fields,['field_48','Numero de Servicio','Número de Servicio'])),
        'codigoServicio'=>$codigo,
        'codigoAtaud'=>pe_text(pe_value($item,$fields,['field_47','Ataud/Urna','Ataúd/Urna'])),
        'referencia'=>pe_text(pe_value($item,$fields,['Referencia'])),
        'requierePlaca'=>pe_bool(pe_value($item,$fields,['RequierePlacadeUrna_x003f_','Requiere Placa','Requiere Placa de Urna','Requiere Placa de Urna?'],false)),
        'titular'=>pe_text(pe_value($item,$fields,['field_7','Titular Responsable','Titular/Responsable','Titular / Responsable','Titular','Responsable'])),
        'fallecido'=>pe_text(pe_value($item,$fields,['field_2','Nombre de Fallecido (a)','Nombre Fallecido','Nombre de Fallecido(a)'])),
        'fechaNacimiento'=>pe_dt(pe_value($item,$fields,['field_4','Fecha Nacimiento Fallecido (a)','Fecha Nacimiento','Fecha de Nacimiento']),true),
        'fechaDefuncion'=>pe_dt(pe_value($item,$fields,['FechaDefuncion','Fecha y Hora Defuncion Fallecido (a)','Fecha y Hora Defunción Fallecido (a)','Fecha y Hora Defuncion','Fecha y Hora Defunción','Fecha Defuncion','Fecha Defunción'])),
        'sexo'=>pe_text(pe_value($item,$fields,['Sexo'])),
        'edad'=>pe_text(pe_value($item,$fields,['field_3','Edad'])),
        'destinoFinal'=>pe_text(pe_value($item,$fields,['field_52','Ubicacion Post Capillas','Ubicación Post Capillas','Ubicacion Destino Final','Ubicación Destino Final'])),
        'embalsamador'=>pe_text(pe_value($item,$fields,['field_69','Embalsamador'])),
        'rescate1'=>pe_text(pe_value($item,$fields,['field_28','Personal Rescate 1'])),
        'rescate2'=>pe_text(pe_value($item,$fields,['field_29','Personal Rescate 2'])),
        'ubicacionRescate'=>pe_text(pe_value($item,$fields,['field_31','Ubicacion de Rescate','Ubicación de Rescate'])),
        'motivo'=>pe_text(pe_value($item,$fields,['field_30','Motivo de Fallecimiento'])),
        'referenciaCrematorio'=>pe_text(pe_value($item,$fields,['field_64','Referencia Crematorio'])),
        'inicioCrematorio'=>pe_dt(pe_value($item,$fields,['FechayHoraInicioCrematorio','Fecha y Hora Inicio Crematorio'])),
        'personalCrematorio'=>pe_text(pe_value($item,$fields,['field_68','Personal de Crematorio','Personal Crematorio'])),
        'fechaHoraInhumacion'=>pe_dt(pe_value($item,$fields,['FechayHoraInhumaci_x00f3_n','Fecha y Hora Inhumacion','Fecha y Hora Inhumación'])),
        'fechaCompra'=>pe_dt(pe_value($item,$fields,['field_10','Fecha Compra']),true),
        'personalVenta'=>pe_text(pe_value($item,$fields,['field_11','Personal Venta'])),
        'precioVenta'=>pe_value($item,$fields,['field_12','Precio de Venta','Precio Venta'],null),
        'serviciosExtra'=>array_values(array_filter(array_map('strval',$extras),static fn(string $v):bool=>trim($v)!==''&&pe_norm($v)!=='noaplica')),
        'extraAmounts'=>$amounts,
        'ventaTotal'=>pe_value($item,$fields,['field_27','Venta Total Servicio'],null),
    ];
}

function pe_parque_payload(array $item,array $fields): array {
    $inicio=pe_dt(pe_value($item,$fields,['FechaHoraInicio','Fecha Hora Inicio','Fecha y Hora Inicio']));
    $fin=pe_dt(pe_value($item,$fields,['FechaHoraFin','Fecha Hora Fin','Fecha y Hora Fin']));
    $split=static function(string $value):array{
        if($value==='')return ['',''];
        $parts=preg_split('/\s+/',trim($value),2)?:[];
        return [$parts[0]??'',$parts[1]??''];
    };
    [$fechaInicio,$horaInicio]=$split($inicio);
    [$fechaFin,$horaFin]=$split($fin);

    return [
        '_area'=>'parque','area'=>'parque',
        'fechaInicio'=>$fechaInicio,'horaInicio'=>$horaInicio,
        'fechaFin'=>$fechaFin,'horaFin'=>$horaFin,
        'fechaHoraInicio'=>$inicio,'fechaHoraFin'=>$fin,
        'velacion'=>pe_text(pe_value($item,$fields,['Velacion','Velación'])),
        'previsionUsoInmediato'=>pe_text(pe_value($item,$fields,['PrevisionUsoInmediato','Prevision Uso Inmediato','Previsión/Uso Inmediato'])),
        'tipoServicio'=>pe_text(pe_value($item,$fields,['TipodeServicio','Tipo de Servicio'])),
        'servicio'=>pe_text(pe_value($item,$fields,['Servicio'])),
        'asistenteFunerarioTexto'=>pe_text(pe_value($item,$fields,['AsistenteFunerarioTexto','Asistente Funerario Texto','Asistente Funerario'])),
        'numeroContrato'=>pe_text(pe_value($item,$fields,['Numero_x0020_de_x0020_Contrato','Numero de Contrato','Número de Contrato'])),
        'seccion'=>pe_text(pe_value($item,$fields,['Seccion','Sección'])),
        'manzana'=>pe_text(pe_value($item,$fields,['Manzana'])),
        'numLoteNicho'=>pe_text(pe_value($item,$fields,['NumLote_x002f_Nicho','NumLote/Nicho','Num Lote/Nicho','Lote/Nicho'])),
        'destape'=>pe_text(pe_value($item,$fields,['Destape'])),
        'tipoPlaca'=>pe_text(pe_value($item,$fields,['TipoPlaca','Tipo de Placa','PlacaParqueTipo'])),
        'nombreFamilia'=>pe_text(pe_value($item,$fields,['NombreFamilia','Nombre Familia'])),
        'requiereCambioUrna'=>pe_bool(pe_value($item,$fields,['RequiereCambioUrna','Requiere Cambio Urna'],false)),
        'titular'=>pe_text(pe_value($item,$fields,['Titular'])),
        'fallecido'=>pe_text(pe_value($item,$fields,['TitularSubstituto','Fallecido','Fallecido(a)'])),
        'parentescoTitular'=>pe_text(pe_value($item,$fields,['ParentescoTitular','Parentesco Titular'])),
        'frase'=>pe_text(pe_value($item,$fields,['Frase'])),
        'fechaNacimiento'=>pe_dt(pe_value($item,$fields,['FechaNacimiento','Fecha Nacimiento']),true),
        'fechaDefuncion'=>pe_dt(pe_value($item,$fields,['FechaDefuncion','Fecha Defuncion','Fecha Defunción']),true),
        'estatusLiquidacion'=>pe_text(pe_value($item,$fields,['EstatusLiquidacion','Estatus Liquidacion','Estatus Liquidación'])),
        'requiereReubicacion'=>pe_bool(pe_value($item,$fields,['RequiereReubicacion','Requiere Reubicacion','Requiere Reubicación'],false)),
        'seccionNueva'=>pe_text(pe_value($item,$fields,['SeccionNueva','Seccion Nueva','Sección Nueva'])),
        'manzanaNueva'=>pe_text(pe_value($item,$fields,['ManzanaNueva','Manzana Nueva'])),
        'numLoteNichoNuevo'=>pe_text(pe_value($item,$fields,['NumLoteNichoNuevo','Num Lote/Nicho Nuevo','Lote/Nicho Nuevo'])),
        'ubicacionNueva'=>pe_text(pe_value($item,$fields,['UbicacionNueva','Ubicacion Nueva','Ubicación Nueva'])),
        'motivoReubicacion'=>pe_text(pe_value($item,$fields,['MotivoReubicacion','Motivo Reubicacion','Motivo Reubicación'])),
        'observaciones'=>pe_text(pe_value($item,$fields,['Observaciones'])),
    ];
}

try{
    rs_storage_bootstrap(); // valida sesión/autenticación disponible para AJAX.

    $area=mb_strtolower(trim((string)($_GET['area']??'capillas')),'UTF-8')==='parque'?'parque':'capillas';
    $itemId=(int)($_GET['id']??0);
    if($itemId<=0)pe_json(400,['ok'=>false,'message'=>'ID de servicio no valido.']);

    $config=rs_sharepoint_config();
    $host='meguesajdjp.sharepoint.com';
    $siteUrl='https://'.$host.'/sites/Operaciones';
    $token=rs_sharepoint_token($config,$host);
    $listTitle=$area==='parque'?'Eventos Parque':'Eventos Capillas';
    $listEsc=rawurlencode($listTitle);

    $fields=pe_request(
        $siteUrl."/_api/web/lists/getbytitle('".$listEsc."')/fields?$select=Title,InternalName,TypeAsString",
        $token
    )['value']??[];
    $index=pe_fields_index(is_array($fields)?$fields:[]);

    $item=pe_request(
        $siteUrl."/_api/web/lists/getbytitle('".$listEsc."')/items(".$itemId.")?$select=*",
        $token
    );

    // SharePoint normalmente responde el elemento directamente con
    // odata=nometadata, pero algunas respuestas pueden venir dentro de "d".
    // Desempaquetarlo evita que el formulario de Modificar reciba un payload vacio.
    if(isset($item['d']) && is_array($item['d'])){
        $item=$item['d'];
    }elseif(isset($item['value']) && is_array($item['value']) && isset($item['value'][0]) && is_array($item['value'][0])){
        $item=$item['value'][0];
    }

    $modoPrueba=pe_bool(pe_value($item,$index,['ModoPrueba','Modo Prueba'],false));
    if($modoPrueba)pe_json(409,['ok'=>false,'message'=>'Los registros de prueba no se editan desde la lista productiva.']);

    $payload=$area==='parque'
        ? pe_parque_payload($item,$index)
        : pe_capillas_payload($item,$index);

    pe_json(200,[
        'ok'=>true,
        'area'=>$area,
        'itemId'=>$itemId,
        'payload'=>$payload,
    ]);
}catch(Throwable $e){
    error_log('Registro Servicios cargar publicado: '.$e->getMessage());
    pe_json(500,['ok'=>false,'message'=>'No fue posible cargar el servicio publicado: '.$e->getMessage()]);
}
