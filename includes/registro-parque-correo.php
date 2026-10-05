<?php
declare(strict_types=1);

function rp_private_config(): array
{
    $configPath='/home/juanpab1/portal-config/config.php';
    if(!is_file($configPath))throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    $raw=require $configPath;
    if(!is_array($raw))throw new RuntimeException('La configuracion privada del Portal no es valida.');
    return $raw;
}

function rp_normalize_emails(mixed $value,array $fallback): array
{
    $items=[];
    if(is_array($value))$items=$value;
    elseif(is_string($value))$items=preg_split('/[;,\r\n]+/',$value)?:[];

    $out=[];
    foreach($items as $item){
        $email=mb_strtolower(trim((string)$item),'UTF-8');
        if($email!=='' && filter_var($email,FILTER_VALIDATE_EMAIL) && !in_array($email,$out,true))$out[]=$email;
    }
    if($out!==[])return $out;

    foreach($fallback as $item){
        $email=mb_strtolower(trim((string)$item),'UTF-8');
        if($email!=='' && filter_var($email,FILTER_VALIDATE_EMAIL) && !in_array($email,$out,true))$out[]=$email;
    }
    return $out;
}

function rp_email_recipients(): array
{
    $config=rp_private_config();

    // Parque usa la misma lista general de destinatarios que Capillas.
    return rp_normalize_emails(
        $config['registro_servicios_email_recipients'] ?? null,
        [
            'gabriel.guerra@juanpablo.com.mx',
            'sistemas@juanpablo.com.mx',
            'cobranza@juanpablo.com.mx',
            'cobranza1@juanpablo.com.mx',
            'cobranza2@juanpablo.com.mx',
            'cobranza3@juanpablo.com.mx',
            'cobranza4@juanpablo.com.mx',
            'administracion2@juanpablo.com.mx',
            'ventas.us@juanpablo.com.mx',
            'rh.comercial@juanpablo.com.mx',
            'direccion@juanpablo.com.mx',
            'jose.santana@juanpablo.com.mx',
            'capillas@juanpablo.com.mx',
            'gustavorv@juanpablo.com.mx',
            'rene.perez@juanpablo.com.mx',
            'elizabeth.lopez@juanpablo.com.mx',
            'marketing@juanpablo.com.mx',
            'gerencia.comercial@juanpablo.com.mx',
            'jose.santana@juanpablo.com.mx',
            'it@juanpablo.com.mx',
            'gerencia.operacion@juanpablo.com.mx',
            'diseno@juanpablo.com.mx',
            'parque.descanso@juanpablo.com.mx',
            'capillasaguafria@juanpablo.com.mx',
        ]
    );
}

function rp_plate_email_recipients(): array
{
    return [
        'sistemas@juanpablo.com.mx',
        'gabriel.guerra@juanpablo.com.mx',
        'it@juanpablo.com.mx',
    ];
}

/**
 * Correo independiente para Placa de Urna o Placa de Nicho.
 *
 * @param array{name:string,contentType:string,bytes:string} $attachment
 */
function rp_send_plate_email(array $payload,array $attachment,string $kind): array
{
    $name=trim((string)($attachment['name']??''));
    $bytes=(string)($attachment['bytes']??'');
    $contentType=trim((string)($attachment['contentType']??'image/png'));

    if($name==='' || $bytes===''){
        return [
            'enabled'=>true,
            'sent'=>false,
            'recipients'=>[],
            'attachmentNames'=>[],
            'error'=>'No se recibio una placa valida para enviar.',
        ];
    }

    $kindNorm=rp_email_plate_norm($kind);
    if(!in_array($kindNorm,['urna','nicho'],true)){
        return [
            'enabled'=>false,
            'sent'=>false,
            'recipients'=>[],
            'attachmentNames'=>[],
            'error'=>null,
        ];
    }

    $sender='sistemas@juanpablo.com.mx';
    $recipients=rp_plate_email_recipients();
    $location=rp_email_location($payload);
    $fallecido=trim((string)($payload['fallecido']??''));
    $familia=trim((string)($payload['nombreFamilia']??''));
    $fechaNacimiento=trim((string)($payload['fechaNacimiento']??''));
    $fechaDefuncion=trim((string)($payload['fechaDefuncion']??''));
    $fechaServicio=trim((string)($payload['fechaHoraInicio']??''));

    $label=$kindNorm==='nicho'?'Nicho':'Urna';
    $subject='Solicitud de Placa '.$label.' Parque: '.($location!==''?$location:'Sin ubicacion');

    $fmt=static function(string $value):string{
        $value=trim($value);
        if($value==='')return '';
        foreach(['Y-m-d\\TH:i:s','Y-m-d\\TH:i','Y-m-d','d/m/Y H:i:s','d/m/Y H:i','d/m/Y'] as $format){
            $dt=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('America/Monterrey'));
            if($dt instanceof DateTimeImmutable){
                return $dt->format(in_array($format,['Y-m-d','d/m/Y'],true)?'d/m/Y':'d/m/Y H:i');
            }
        }
        return $value;
    };

    $body=''
      .'<div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.45;color:#111;">'
      .'<p><strong>Tipo de placa:</strong> '.rp_email_escape($label).'</p>'
      .'<p><strong>Ubicacion:</strong> '.rp_email_escape($location).'</p>'
      .($kindNorm==='nicho'
          ? '<p><strong>Nombre de familia:</strong> '.rp_email_escape($familia).'</p>'
          : '<p><strong>Nombre de fallecido:</strong> '.rp_email_escape($fallecido).'</p>'
        )
      .'<p><strong>Fecha nacimiento:</strong> '.rp_email_escape($fmt($fechaNacimiento)).'<br>'
      .'<strong>Fecha defuncion:</strong> '.rp_email_escape($fmt($fechaDefuncion)).'</p>'
      .'<p style="color:#c00000;font-style:italic;"><strong>***Tenerla lista para antes de:</strong> '
      .rp_email_escape($fmt($fechaServicio))
      .'<strong>***</strong></p>'
      .'</div>';

    $request=[
        'message'=>[
            'subject'=>$subject,
            'importance'=>'high',
            'body'=>['contentType'=>'HTML','content'=>$body],
            'toRecipients'=>array_map(
                static fn(string $address):array=>['emailAddress'=>['address'=>$address]],
                $recipients
            ),
            'attachments'=>[[
                '@odata.type'=>'#microsoft.graph.fileAttachment',
                'name'=>$name,
                'contentType'=>$contentType!==''?$contentType:'image/png',
                'contentBytes'=>base64_encode($bytes),
            ]],
        ],
        'saveToSentItems'=>true,
    ];

    $token=rs_graph_token();
    $url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($sender).'/sendMail';
    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible inicializar el correo de placa de Parque.');

    $json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json))throw new RuntimeException('No fue posible preparar el correo de placa de Parque.');

    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>60,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$json,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
    ]);

    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if($response===false)throw new RuntimeException('El envio del correo de placa de Parque fallo: '.$error);
    if($status<200||$status>=300){
        $decoded=json_decode((string)$response,true);
        $detail=is_array($decoded)?trim((string)($decoded['error']['message']??'')):'';
        if($detail==='')$detail=mb_substr(trim((string)$response),0,1200);
        throw new RuntimeException(
            'Microsoft Graph correo placa Parque respondio HTTP '.$status.($detail!==''?': '.$detail:'.')
        );
    }

    return [
        'enabled'=>true,
        'sent'=>true,
        'sender'=>$sender,
        'recipients'=>$recipients,
        'attachmentNames'=>[$name],
        'kind'=>$kindNorm,
        'error'=>null,
    ];
}

function rp_email_plate_norm(string $value): string
{
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
    if(is_string($ascii) && $ascii!=='')$value=$ascii;
    return preg_replace('/[^a-z0-9]+/','',strtolower(trim($value)))??'';
}

function rp_email_escape(string $value): string
{
    return htmlspecialchars(mb_strtoupper(trim($value),'UTF-8'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function rp_email_location(array $payload): string
{
    $seccion=trim((string)($payload['seccion']??''));
    $manzana=trim((string)($payload['manzana']??''));
    $lote=trim((string)($payload['numLoteNicho']??''));
    $serviceRaw=(string)($payload['servicio']??'');
    $serviceAscii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$serviceRaw);
    if(!is_string($serviceAscii))$serviceAscii=$serviceRaw;
    $serviceNorm=preg_replace('/[^a-z0-9]+/','',strtolower($serviceAscii))??'';
    $prefix=$serviceNorm==='totalservicecomplemento'?'TSC':($serviceNorm==='totalservice'?'TS':'');

    return implode(' - ',array_values(array_filter([
        $prefix,
        $seccion,
        $lote,
        $manzana,
    ],static fn(string $v):bool=>$v!=='')));
}

/**
 * @param array<int,array{name:string,contentType:string,bytes:string}> $attachments
 */
function rp_send_service_email(array $payload,array $attachments,bool $isModified=false): array
{
    $sender='sistemas@juanpablo.com.mx';
    $recipients=rp_email_recipients();
    if($recipients===[])throw new RuntimeException('No hay destinatarios configurados para Servicios Parque.');

    $location=rp_email_location($payload);
    $type=trim((string)($payload['tipoServicio']??''));
    $fallecido=trim((string)($payload['fallecido']??''));

    $subjectParts=array_values(array_filter([
        ($isModified?'MODIFICADO - ':'').'Nuevo evento de Parque',
        $type,
        $location,
    ],static fn(string $v):bool=>$v!==''));
    $subject=implode(': ',$subjectParts);

    $line=static function(string $label,string $value):string{
        return '<div style="margin:0 0 4px 0;"><strong>'.rp_email_escape($label).':</strong> '
            .rp_email_escape($value!==''?$value:'NO CAPTURADO').'</div>';
    };

    $body=''
      .'<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.4;color:#111;">'
      .$line('TIPO DE SERVICIO',$type)
      .$line('SERVICIO',trim((string)($payload['servicio']??'')))
      .$line('PREVISION/USO INMEDIATO',trim((string)($payload['previsionUsoInmediato']??'')))
      .$line('EVENTO','de '.trim((string)($payload['fechaHoraInicio']??'')).' a '.trim((string)($payload['fechaHoraFin']??'')))
      .'<br>'
      .$line('UBICACION',$location)
      .$line('DESTAPE',trim((string)($payload['destape']??'')))
      .$line('TIPO DE PLACA',trim((string)($payload['tipoPlaca']??'')))
      .$line('NOMBRE DE FAMILIA',trim((string)($payload['nombreFamilia']??'')))
      .'<br>'
      .$line('TITULAR',trim((string)($payload['titular']??'')))
      .$line('PARENTESCO',trim((string)($payload['parentescoTitular']??'')))
      .$line('FALLECIDO(A)',$fallecido)
      .$line('FECHA DE NACIMIENTO',trim((string)($payload['fechaNacimiento']??'')))
      .$line('FECHA DE DEFUNCION',trim((string)($payload['fechaDefuncion']??'')))
      .$line('NUMERO DE CONTRATO',trim((string)($payload['numeroContrato']??'')))
      .$line('ASISTENTE FUNERARIO',trim((string)($payload['asistenteFunerarioTexto']??'')))
      .$line('ESTATUS DE LIQUIDACION',trim((string)($payload['estatusLiquidacion']??'')))
      .'</div>';

    $graphAttachments=[];
    $names=[];
    foreach($attachments as $attachment){
        $name=trim((string)($attachment['name']??''));
        $bytes=(string)($attachment['bytes']??'');
        if($name===''||$bytes==='')continue;
        $graphAttachments[]=[
            '@odata.type'=>'#microsoft.graph.fileAttachment',
            'name'=>$name,
            'contentType'=>trim((string)($attachment['contentType']??'application/octet-stream')),
            'contentBytes'=>base64_encode($bytes),
        ];
        $names[]=$name;
    }

    $request=[
        'message'=>[
            'subject'=>$subject,
            'body'=>['contentType'=>'HTML','content'=>$body],
            'toRecipients'=>array_map(static fn(string $address):array=>['emailAddress'=>['address'=>$address]],$recipients),
            'attachments'=>$graphAttachments,
        ],
        'saveToSentItems'=>true,
    ];

    $token=rs_graph_token();
    $url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($sender).'/sendMail';

    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible inicializar el correo de Servicios Parque.');
    $json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json))throw new RuntimeException('No fue posible preparar el correo de Servicios Parque.');

    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>60,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$json,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
    ]);

    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if($response===false)throw new RuntimeException('El correo de Servicios Parque fallo: '.$error);
    if($status<200||$status>=300){
        $decoded=json_decode((string)$response,true);
        $detail=is_array($decoded)?trim((string)($decoded['error']['message']??'')):'';
        if($detail==='')$detail=mb_substr(trim((string)$response),0,1200);
        throw new RuntimeException('Microsoft Graph correo Parque respondio HTTP '.$status.($detail!==''?': '.$detail:'.'));
    }

    return [
        'enabled'=>true,
        'sent'=>true,
        'sender'=>$sender,
        'recipients'=>$recipients,
        'attachmentNames'=>$names,
        'error'=>null,
    ];
}
