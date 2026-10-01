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

    return rp_normalize_emails(
        $config['registro_servicios_parque_email_recipients']
            ?? $config['registro_servicios_email_recipients']
            ?? null,
        [
            'gabriel.guerra@juanpablo.com.mx',
            'sistemas@juanpablo.com.mx',
            'gerencia.operacion@juanpablo.com.mx',
            'jose.santana@juanpablo.com.mx',
            'it@juanpablo.com.mx',
        ]
    );
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
function rp_send_service_email(array $payload,array $attachments): array
{
    $sender='sistemas@juanpablo.com.mx';
    $recipients=rp_email_recipients();
    if($recipients===[])throw new RuntimeException('No hay destinatarios configurados para Servicios Parque.');

    $location=rp_email_location($payload);
    $type=trim((string)($payload['tipoServicio']??''));
    $fallecido=trim((string)($payload['fallecido']??''));

    $subjectParts=array_values(array_filter([
        'Nuevo evento de Parque',
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
