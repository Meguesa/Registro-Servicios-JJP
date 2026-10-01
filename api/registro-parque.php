<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/registro-sharepoint.php';
require_once __DIR__ . '/../includes/registro-parque-calendario.php';
require_once __DIR__ . '/../includes/registro-parque-imagenes.php';
require_once __DIR__ . '/../includes/registro-parque-correo.php';
require_once __DIR__ . '/../includes/registro-parque-documentos.php';
require_once __DIR__ . '/../includes/registro-storage.php';

function rp_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rp_norm(string $value): string
{
    $value = trim($value);
    $value = preg_replace_callback(
        '/_x([0-9a-fA-F]{4})_/',
        static function (array $m): string {
            $code = hexdec($m[1]);
            if ($code <= 0x7F) return chr($code);
            return html_entity_decode('&#' . $code . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        },
        $value
    ) ?? $value;

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') $value = $ascii;
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

/** @return array<string,array<string,mixed>> */
function rp_fields_by_norm(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        foreach ([(string)($row['Title'] ?? ''), (string)($row['InternalName'] ?? '')] as $candidate) {
            $key = rp_norm($candidate);
            if ($key !== '' && !isset($out[$key])) $out[$key] = $row;
        }
    }
    return $out;
}

/** @return array<string,mixed>|null */
function rp_find_field(array $fields, array $aliases): ?array
{
    foreach ($aliases as $alias) {
        $key = rp_norm((string)$alias);
        if ($key !== '' && isset($fields[$key])) return $fields[$key];
    }
    return null;
}

function rp_upper(mixed $value): mixed
{
    if (is_string($value)) return mb_strtoupper(trim($value), 'UTF-8');
    if (is_array($value)) {
        $out=[];
        foreach ($value as $k=>$v) $out[$k]=rp_upper($v);
        return $out;
    }
    return $value;
}

function rp_local_datetime_to_utc(string $value): ?string
{
    $value=trim($value);
    if($value==='')return null;
    foreach(['d/m/Y H:i','Y-m-d H:i','Y-m-d\\TH:i:s','Y-m-d\\TH:i'] as $format){
        $dt=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('America/Monterrey'));
        if($dt instanceof DateTimeImmutable){
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        }
    }
    return null;
}

function rp_date_only(string $value): ?string
{
    $value=trim($value);
    if($value==='')return null;
    foreach(['d/m/Y','Y-m-d'] as $format){
        $dt=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('UTC'));
        if($dt instanceof DateTimeImmutable)return $dt->format('Y-m-d\\T00:00:00\\Z');
    }
    return null;
}

function rp_add_value(array &$target, array $fieldIndex, array $aliases, mixed $value, bool $skipBlank=true): void
{
    $field=rp_find_field($fieldIndex,$aliases);
    if($field===null)return;
    if($skipBlank && ($value===null || $value==='' || $value===[]))return;

    $internal=(string)($field['InternalName']??'');
    if($internal==='')return;

    $type=strtolower((string)($field['TypeAsString']??''));
    $value=rp_upper($value);

    if($type==='multichoice'){
        $target[$internal]=is_array($value)?array_values($value):[(string)$value];
        return;
    }
    if($type==='boolean'){
        $target[$internal]=(bool)$value;
        return;
    }
    if(in_array($type,['number','currency'],true)){
        if($value===''||$value===null)return;
        $target[$internal]=(float)$value;
        return;
    }

    // Eventos Parque sustituyo el campo Persona del asistente por
    // AsistenteFunerarioTexto. Si apareciera un campo User heredado, no lo
    // escribimos con texto para evitar un error de SharePoint.
    if(in_array($type,['user','usermulti'],true))return;

    $target[$internal]=$value;
}

/** @return array{status:int,body:string,json:array<string,mixed>} */
function rp_request(string $method,string $url,string $token,?string $body=null,array $headers=[]): array
{
    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible inicializar la conexion con SharePoint.');
    $base=[
        'Authorization: Bearer '.$token,
        'Accept: application/json;odata=nometadata',
    ];
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>45,
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>array_merge($base,$headers),
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    if($body!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$body);
    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if($response===false)throw new RuntimeException('La conexion con SharePoint fallo: '.$error);
    $decoded=json_decode((string)$response,true);
    if($status<200||$status>=300){
        $detail='';
        if(is_array($decoded)){
            $messageNode=$decoded['error']['message']??'';
            if(is_array($messageNode))$detail=(string)($messageNode['value']??'');
            elseif(is_string($messageNode))$detail=$messageNode;
        }
        if($detail==='')$detail=mb_substr(trim((string)$response),0,1800);
        throw new RuntimeException('SharePoint respondio HTTP '.$status.($detail!==''?': '.$detail:'.'));
    }
    return ['status'=>$status,'body'=>(string)$response,'json'=>is_array($decoded)?$decoded:[]];
}

try{
    $root=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
    $bootstrap=$root.'/includes/bootstrap.php';
    if(!is_file($bootstrap))throw new RuntimeException('No se encontro el bootstrap del Portal.');
    require_once $bootstrap;
    portal_require_authentication();

    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
        rp_json(405,['ok'=>false,'message'=>'Metodo no permitido.']);
    }

    $payload=json_decode((string)($_POST['payload']??''),true);
    if(!is_array($payload))rp_json(400,['ok'=>false,'message'=>'La informacion del formulario no es valida.']);

    // Regla de negocio: el Tipo de Placa se deriva del Tipo de Servicio
    // para Inhumacion, Exhumacion y Deposito de Cenizas.
    $tipoServicioNorm=rp_norm((string)($payload['tipoServicio']??''));
    if(in_array($tipoServicioNorm,['inhumacion','exhumacion'],true)){
        $payload['tipoPlaca']='Granito';
    }elseif($tipoServicioNorm==='depositodecenizas'){
        $payload['tipoPlaca']='Nicho';
    }
    $draftId=trim((string)($_POST['draftId']??''));
    $storageCtx=null;
    if($draftId!==''){
        $storageCtx=rs_storage_bootstrap();
        $draftId=rs_safe_id($draftId);
    }

    $required=[
        'fechaHoraInicio','fechaHoraFin','velacion','previsionUsoInmediato',
        'tipoServicio','servicio','asistenteFunerarioTexto','seccion','manzana',
        'numLoteNicho','destape','tipoPlaca','titular','fallecido',
        'parentescoTitular','fechaNacimiento','fechaDefuncion','estatusLiquidacion'
    ];
    foreach($required as $key){
        if(trim((string)($payload[$key]??''))===''){
            rp_json(422,['ok'=>false,'message'=>'Falta el campo obligatorio: '.$key.'.']);
        }
    }

    $tipoPlaca=trim((string)($payload['tipoPlaca']??''));
    $destape=trim((string)($payload['destape']??''));
    if(mb_strtolower($tipoPlaca,'UTF-8')==='nicho' && mb_strtolower($destape,'UTF-8')!=='primero'){
        rp_json(422,['ok'=>false,'message'=>'Tipo de Placa = Nicho solo es valido cuando Destape = Primero.']);
    }
    if(mb_strtolower($tipoPlaca,'UTF-8')==='nicho' && trim((string)($payload['nombreFamilia']??''))===''){
        rp_json(422,['ok'=>false,'message'=>'Nombre de Familia es obligatorio para placa de Nicho.']);
    }
    if((bool)($payload['requiereReubicacion']??false)){
        foreach(['seccionNueva','manzanaNueva','numLoteNichoNuevo','motivoReubicacion'] as $key){
            if(trim((string)($payload[$key]??''))===''){
                rp_json(422,['ok'=>false,'message'=>'Falta el campo de reubicacion: '.$key.'.']);
            }
        }

        $serviceNorm=rp_norm((string)($payload['servicio']??''));
        $prefix=$serviceNorm==='totalservicecomplemento'
            ? 'TSC'
            : ($serviceNorm==='totalservice' ? 'TS' : '');

        $payload['ubicacionNueva']=implode(' - ',array_values(array_filter([
            $prefix,
            trim((string)$payload['seccionNueva']),
            trim((string)$payload['numLoteNichoNuevo']),
            trim((string)$payload['manzanaNueva']),
        ],static fn(string $v):bool=>$v!=='')));
    }else{
        $payload['seccionNueva']='';
        $payload['manzanaNueva']='';
        $payload['numLoteNichoNuevo']='';
        $payload['ubicacionNueva']='';
        $payload['motivoReubicacion']='';
    }

    $config=rs_sharepoint_config();
    $host='meguesajdjp.sharepoint.com';
    $siteUrl='https://'.$host.'/sites/Operaciones';
    $listTitle='Eventos Parque';
    $token=rs_sharepoint_token($config,$host);

    $listEsc=rawurlencode($listTitle);
    $fieldsUrl=$siteUrl."/ _api/web/lists/getbytitle('".$listEsc."')/fields";
    $fieldsUrl=str_replace('/ _api/','/_api/',$fieldsUrl)
        .'?$select=Title,InternalName,TypeAsString,Required,Hidden,ReadOnlyField';
    $fieldRows=rp_request('GET',$fieldsUrl,$token)['json']['value']??[];
    $fieldIndex=rp_fields_by_norm(is_array($fieldRows)?$fieldRows:[]);

    $sp=[];
    // Parque opera oficialmente en producción. Mantener ModoPrueba en No
    // para compatibilidad con la lista de SharePoint existente.
    $modoPrueba=false;

    rp_add_value($sp,$fieldIndex,['ModoPrueba','Modo Prueba'],false,false);
    rp_add_value($sp,$fieldIndex,['FechaHoraInicio','Fecha Hora Inicio','Fecha y Hora Inicio'],rp_local_datetime_to_utc((string)$payload['fechaHoraInicio']));
    rp_add_value($sp,$fieldIndex,['FechaHoraFin','Fecha Hora Fin','Fecha y Hora Fin'],rp_local_datetime_to_utc((string)$payload['fechaHoraFin']));
    rp_add_value($sp,$fieldIndex,['Velacion','Velación'],trim((string)$payload['velacion']));
    rp_add_value($sp,$fieldIndex,['PrevisionUsoInmediato','Prevision Uso Inmediato','Previsión/Uso Inmediato'],trim((string)$payload['previsionUsoInmediato']));
    rp_add_value($sp,$fieldIndex,['TipodeServicio','Tipo de Servicio'],trim((string)$payload['tipoServicio']));
    rp_add_value($sp,$fieldIndex,['Servicio'],trim((string)$payload['servicio']));

    rp_add_value($sp,$fieldIndex,['Seccion','Sección'],trim((string)$payload['seccion']));
    rp_add_value($sp,$fieldIndex,['Manzana'],trim((string)$payload['manzana']));
    rp_add_value($sp,$fieldIndex,['NumLote_x002f_Nicho','NumLote/Nicho','Num Lote/Nicho','Lote/Nicho'],trim((string)$payload['numLoteNicho']));

    $serviceNorm=rp_norm((string)($payload['servicio']??''));
    $prefix=$serviceNorm==='totalservicecomplemento'
        ? 'TSC'
        : ($serviceNorm==='totalservice' ? 'TS' : '');
    $ubicacion=implode(' - ',array_values(array_filter([
        $prefix,
        trim((string)$payload['seccion']),
        trim((string)$payload['numLoteNicho']),
        trim((string)$payload['manzana']),
    ],static fn(string $v):bool=>$v!=='')));
    rp_add_value($sp,$fieldIndex,['Ubicaci_x00f3_n','Ubicacion','Ubicación'],$ubicacion);

    rp_add_value($sp,$fieldIndex,['Destape'],trim((string)$payload['destape']));
    rp_add_value($sp,$fieldIndex,['TipoPlaca','Tipo de Placa'],trim((string)$payload['tipoPlaca']));
    rp_add_value($sp,$fieldIndex,['PlacaParqueTipo'],trim((string)$payload['tipoPlaca']));
    rp_add_value($sp,$fieldIndex,['NombreFamilia','Nombre Familia'],trim((string)($payload['nombreFamilia']??'')));
    $requiereCambioUrna=(bool)($payload['requiereCambioUrna']??false);
    rp_add_value($sp,$fieldIndex,['RequiereCambioUrna','Requiere Cambio Urna'],$requiereCambioUrna,false);

    // Mantener el protocolo actual de placas de Parque.
    // Nicho: solo cuando TipoPlaca=Nicho y Destape=Primero.
    // Urna: cuando TipoPlaca=Urna o cuando RequiereCambioUrna=true.
    $tipoPlacaNorm=rp_norm((string)$payload['tipoPlaca']);
    $destapeNorm=rp_norm((string)$payload['destape']);
    $solicitaNicho=$tipoPlacaNorm==='nicho' && $destapeNorm==='primero';
    $solicitaUrna=$tipoPlacaNorm==='urna' || $requiereCambioUrna;

    if($solicitaNicho){
        rp_add_value($sp,$fieldIndex,['PlacaNichoEstatus'],'Pendiente',false);
        rp_add_value($sp,$fieldIndex,['PlacaNichoArchivoCorreoListo'],'No',false);
        rp_add_value($sp,$fieldIndex,['PlacaNichoPngNombre'],'',false);
        rp_add_value($sp,$fieldIndex,['PlacaNichoError'],'',false);
    }
    if($solicitaUrna){
        rp_add_value($sp,$fieldIndex,['PlacaUrnaEstatus'],'Pendiente',false);
        rp_add_value($sp,$fieldIndex,['PlacaUrnaArchivoCorreoListo'],'No',false);
        rp_add_value($sp,$fieldIndex,['PlacaUrnaPngNombre'],'',false);
        rp_add_value($sp,$fieldIndex,['PlacaUrnaError'],'',false);
        // La urna es adicional: no sustituir TipoPlaca ni PlacaParqueTipo.
    }

    rp_add_value($sp,$fieldIndex,['Titular'],trim((string)$payload['titular']));
    rp_add_value($sp,$fieldIndex,['TitularSubstituto','Fallecido','Fallecido(a)'],trim((string)$payload['fallecido']));
    rp_add_value($sp,$fieldIndex,['ParentescoTitular','Parentesco Titular'],trim((string)$payload['parentescoTitular']));
    $vipSection=str_ends_with(mb_strtoupper(trim((string)$payload['seccion']),'UTF-8'),'V');
    $fraseAllowed=rp_norm((string)$payload['destape'])==='primero' && $vipSection;
    rp_add_value($sp,$fieldIndex,['Frase'],$fraseAllowed?trim((string)($payload['frase']??'')):'');
    rp_add_value($sp,$fieldIndex,['FechaNacimiento','Fecha Nacimiento'],rp_date_only((string)$payload['fechaNacimiento']));
    rp_add_value($sp,$fieldIndex,['FechaDefuncion','Fecha Defuncion','Fecha Defunción'],rp_date_only((string)$payload['fechaDefuncion']));

    rp_add_value($sp,$fieldIndex,['AsistenteFunerarioTexto','Asistente Funerario Texto','Asistente Funerario'],trim((string)$payload['asistenteFunerarioTexto']));
    rp_add_value($sp,$fieldIndex,['Observaciones'],trim((string)($payload['observaciones']??'')));
    rp_add_value($sp,$fieldIndex,['EstatusLiquidacion','Estatus Liquidacion','Estatus Liquidación'],trim((string)$payload['estatusLiquidacion']));
    rp_add_value($sp,$fieldIndex,['Numero_x0020_de_x0020_Contrato','Numero de Contrato','Número de Contrato'],trim((string)($payload['numeroContrato']??'')));

    rp_add_value($sp,$fieldIndex,['RequiereReubicacion','Requiere Reubicacion','Requiere Reubicación'],(bool)($payload['requiereReubicacion']??false),false);
    rp_add_value($sp,$fieldIndex,['SeccionNueva','Seccion Nueva','Sección Nueva'],trim((string)($payload['seccionNueva']??'')));
    rp_add_value($sp,$fieldIndex,['ManzanaNueva','Manzana Nueva'],trim((string)($payload['manzanaNueva']??'')));
    rp_add_value($sp,$fieldIndex,['NumLoteNichoNuevo','Num Lote/Nicho Nuevo','Lote/Nicho Nuevo'],trim((string)($payload['numLoteNichoNuevo']??'')));
    rp_add_value($sp,$fieldIndex,['UbicacionNueva','Ubicacion Nueva','Ubicación Nueva'],trim((string)($payload['ubicacionNueva']??'')));
    rp_add_value($sp,$fieldIndex,['MotivoReubicacion','Motivo Reubicacion','Motivo Reubicación'],trim((string)($payload['motivoReubicacion']??'')));

    $titleField=rp_find_field($fieldIndex,['Title']);
    if($titleField!==null){
        $internal=(string)($titleField['InternalName']??'Title');
        if(!array_key_exists($internal,$sp)){
            $sp[$internal]=rp_upper(trim((string)$payload['fallecido']).' - '.$ubicacion);
        }
    }

    $digest=trim((string)(rp_request('POST',$siteUrl.'/_api/contextinfo',$token,'',[
        'Accept: application/json;odata=nometadata',
        'Content-Type: application/json;odata=nometadata',
    ])['json']['FormDigestValue']??''));
    if($digest==='')throw new RuntimeException('SharePoint no devolvio un FormDigest valido.');

    $createUrl=$siteUrl."/ _api/web/lists/getbytitle('".$listEsc."')/items";
    $createUrl=str_replace('/ _api/','/_api/',$createUrl);
    $created=rp_request('POST',$createUrl,$token,(string)json_encode($sp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),[
        'Content-Type: application/json;odata=nometadata',
        'X-RequestDigest: '.$digest,
    ])['json'];

    $itemId=(int)($created['Id']??$created['ID']??0);
    if($itemId<=0)throw new RuntimeException('SharePoint creo el registro, pero no devolvio un ID utilizable.');

    // Crear directamente el evento oficial de Parque desde Registro de Servicios.
    $calendarResult=[
        'enabled'=>false,
        'created'=>false,
        'error'=>null,
    ];
    try{
        $calendarResult=array_merge(
            $calendarResult,
            rp_calendar_create_event($payload)
        );
    }catch(Throwable $calendarError){
        // El elemento ya existe en SharePoint; no pedir al usuario repetir
        // el registro por un fallo aislado del calendario.
        $calendarResult['enabled']=true;
        $calendarResult['error']=$calendarError->getMessage();
        error_log('Registro Servicios Parque Calendario item '.$itemId.': '.$calendarError->getMessage());
    }


    // Generar las tablas informativas y enviar el correo directamente desde
    // Registro de Servicios, igual que en Capillas.
    $emailResult=[
        'enabled'=>true,
        'sent'=>false,
        'recipients'=>[],
        'attachmentNames'=>[],
        'error'=>null,
    ];
    try{
        $infoAttachments=rp_generate_information_images($payload);
        $operationalAttachments=rp_generate_operational_tables($payload);
        $letterAttachments=rp_generate_letter_attachments($payload);
        $allAttachments=array_merge(
            $infoAttachments,
            $operationalAttachments,
            $letterAttachments
        );
        $emailResult=array_merge(
            $emailResult,
            rp_send_service_email($payload,$allAttachments)
        );
    }catch(Throwable $emailError){
        $emailResult['error']=$emailError->getMessage();
        error_log('Registro Servicios Parque Correo item '.$itemId.': '.$emailError->getMessage());
    }

    if(is_array($storageCtx) && $draftId!==''){
        rs_remove_tree(rs_draft_dir($storageCtx,$draftId));
    }

    rp_json(201,[
        'ok'=>true,
        'itemId'=>$itemId,
        'list'=>$listTitle,
        'message'=>'Servicio Parque registrado correctamente en SharePoint.',
        'calendar'=>$calendarResult,
        'email'=>$emailResult,
        'automationSource'=>'RegistroServicios',
    ]);
}catch(Throwable $e){
    error_log('Registro Servicios Parque: '.$e->getMessage());
    rp_json(500,['ok'=>false,'message'=>$e->getMessage()]);
}
