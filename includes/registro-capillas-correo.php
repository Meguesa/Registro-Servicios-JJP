<?php
declare(strict_types=1);

function rc_private_config(): array
{
    $path='/home/juanpab1/portal-config/config.php';
    if(!is_file($path))return [];
    $raw=require $path;
    return is_array($raw)?$raw:[];
}

function rc_normalize_emails(mixed $value,array $fallback): array
{
    $items=is_array($value)?$value:(is_string($value)?(preg_split('/[;,\\s]+/',$value)?:[]):[]);
    $out=[];
    foreach($items as $item){
        $email=mb_strtolower(trim((string)$item),'UTF-8');
        if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL))$out[$email]=true;
    }
    if($out===[]){
        foreach($fallback as $item){
            $email=mb_strtolower(trim((string)$item),'UTF-8');
            if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL))$out[$email]=true;
        }
    }
    return array_keys($out);
}

function rc_service_recipients(): array
{
    $config=rc_private_config();
    return rc_normalize_emails(
        $config['registro_servicios_email_recipients']??null,
        [
            'gabriel.guerra@juanpablo.com.mx','sistemas@juanpablo.com.mx',
            'cobranza@juanpablo.com.mx','cobranza1@juanpablo.com.mx',
            'cobranza2@juanpablo.com.mx','cobranza3@juanpablo.com.mx',
            'cobranza4@juanpablo.com.mx','administracion@juanpablo.com.mx',
            'administracion2@juanpablo.com.mx','ventas.us@juanpablo.com.mx',
            'rh.comercial@juanpablo.com.mx','direccion@juanpablo.com.mx',
            'jose.santana@juanpablo.com.mx','capillas@juanpablo.com.mx',
            'gustavorv@juanpablo.com.mx','rene.perez@juanpablo.com.mx',
            'elizabeth.lopez@juanpablo.com.mx','marketing@juanpablo.com.mx',
            'gerencia.comercial@juanpablo.com.mx','it@juanpablo.com.mx',
            'gerencia.operacion@juanpablo.com.mx','diseno@juanpablo.com.mx',
            'angel.delacruz@juanpablo.com.mx','jhonatan.montalvo@juanpablo.com.mx',
        ]
    );
}

function rc_plate_recipients(): array
{
    return ['sistemas@juanpablo.com.mx','gabriel.guerra@juanpablo.com.mx','it@juanpablo.com.mx'];
}

function rc_escape(string $value): string
{
    return htmlspecialchars(mb_strtoupper(trim($value),'UTF-8'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function rc_send_graph_mail(string $subject,string $body,array $recipients,array $attachments=[],bool $important=false): array
{
    $sender='sistemas@juanpablo.com.mx';
    $graphAttachments=[];
    $names=[];
    foreach($attachments as $attachment){
        if(!is_array($attachment))continue;
        $name=trim((string)($attachment['name']??''));
        $bytes=(string)($attachment['bytes']??'');
        if($name===''||$bytes==='')continue;
        $contentType=trim((string)($attachment['contentType']??'application/octet-stream'));
        $graphAttachments[]=[
            '@odata.type'=>'#microsoft.graph.fileAttachment',
            'name'=>$name,
            'contentType'=>$contentType!==''?$contentType:'application/octet-stream',
            'contentBytes'=>base64_encode($bytes),
        ];
        $names[]=$name;
    }

    $message=[
        'subject'=>$subject,
        'body'=>['contentType'=>'HTML','content'=>$body],
        'toRecipients'=>array_map(
            static fn(string $address):array=>['emailAddress'=>['address'=>$address]],
            $recipients
        ),
        'attachments'=>$graphAttachments,
    ];
    if($important)$message['importance']='high';

    $request=['message'=>$message,'saveToSentItems'=>true];
    $token=rs_graph_token();
    $url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($sender).'/sendMail';
    $curl=curl_init($url);
    if($curl===false)throw new RuntimeException('No fue posible inicializar el correo de Capillas.');

    $json=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(!is_string($json))throw new RuntimeException('No fue posible preparar el correo de Capillas.');

    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$json,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
    ]);
    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if($response===false)throw new RuntimeException('El correo de Capillas fallo: '.$error);
    if($status<200||$status>=300){
        $decoded=json_decode((string)$response,true);
        $detail=is_array($decoded)?trim((string)($decoded['error']['message']??'')):'';
        if($detail==='')$detail=mb_substr(trim((string)$response),0,1200);
        throw new RuntimeException('Microsoft Graph correo Capillas respondio HTTP '.$status.($detail!==''?': '.$detail:'.'));
    }

    return ['enabled'=>true,'sent'=>true,'sender'=>$sender,'recipients'=>$recipients,'attachmentNames'=>$names,'error'=>null];
}

function rc_send_service_email(array $payload,array $attachments): array
{
    $fmt=static function(string $value,bool $dateOnly=false):string{
        $value=trim($value);
        if($value==='')return '';
        foreach(['d/m/Y H:i','d/m/Y H:i:s','Y-m-d\\TH:i:s','Y-m-d\\TH:i','Y-m-d','d/m/Y'] as $format){
            $dt=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('America/Monterrey'));
            if($dt instanceof DateTimeImmutable)return $dt->format($dateOnly?'d-m-Y':'d-m-Y H:i:s');
        }
        return $value;
    };

    $ubicacion=trim((string)($payload['ubicacion']??''));
    $sala=trim((string)($payload['sala']??''));
    $referencia=trim((string)($payload['referencia']??''));
    $subject='Nuevo evento de Capillas: '.($ubicacion!==''?$ubicacion:'Sin ubicación')
        .($sala!==''?', '.$sala:'').($referencia!==''?', '.$referencia:'');

    $row=static function(string $label,string $value):string{
        return '<tr><td style="padding:3px 18px 3px 0;white-space:nowrap;vertical-align:top;"><strong>'
            .rc_escape($label).':</strong></td><td style="padding:3px 0;vertical-align:top;">'
            .rc_escape($value!==''?$value:'NO CAPTURADO').'</td></tr>';
    };

    $inicio=$fmt((string)($payload['inicio']??''));
    $termino=$fmt((string)($payload['termino']??''));
    $evento=$inicio!==''&&$termino!==''?'de '.$inicio.' a '.$termino:($inicio!==''?'de '.$inicio:'');
    $exequia=!empty($payload['llevaExequia'])?$fmt((string)($payload['horaExequia']??'')):'NO APLICA';
    if(!empty($payload['llevaExequia'])&&$exequia==='')$exequia='PENDIENTE';

    $personalRescate=implode(' Y ',array_values(array_filter([
        trim((string)($payload['rescate1']??'')),trim((string)($payload['rescate2']??''))
    ],static fn(string $v):bool=>$v!=='')));

    $body='<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.3;color:#111;"><table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:auto;">'
        .$row('EVENTO',$evento).$row('EXEQUIA',$exequia)
        .'<tr><td colspan="2" style="height:10px;"></td></tr>'
        .$row('UBICACIÓN',$ubicacion).$row('SALA',$sala)
        .$row('PREVISIÓN/USO INMEDIATO',trim((string)($payload['prevision']??'')))
        .$row('UBICACIÓN DE RESCATE',trim((string)($payload['ubicacionRescate']??'')))
        .$row('MOTIVO DE FALLECIMIENTO',trim((string)($payload['motivo']??'')))
        .$row('SERVICIO',trim((string)($payload['servicio']??'')))
        .$row('PERSONAL DE RESCATE',$personalRescate)
        .'<tr><td colspan="2" style="height:10px;"></td></tr>'
        .$row('TITULAR',trim((string)($payload['titular']??'')))
        .$row('FALLECIDO(A)',trim((string)($payload['fallecido']??'')))
        .$row('FECHA DE NACIMIENTO',$fmt((string)($payload['fechaNacimiento']??''),true))
        .$row('FECHA DE DEFUNCIÓN',$fmt((string)($payload['fechaDefuncion']??''),true))
        .$row('EDAD',trim((string)($payload['edad']??'')))
        .'<tr><td colspan="2" style="height:10px;"></td></tr>'
        .$row('REFERENCIA',$referencia)
        .$row('NÚMERO DE SERVICIO',trim((string)($payload['numeroReferencia']??'')))
        .$row('VENDEDOR',trim((string)($payload['personalVenta']??'')))
        .'</table></div>';

    return rc_send_graph_mail($subject,$body,rc_service_recipients(),$attachments,false);
}

function rc_send_plate_email(array $payload,array $attachment): array
{
    $numero=trim((string)($payload['numeroServicio']??$payload['numeroReferencia']??''));
    $fallecido=trim((string)($payload['fallecido']??''));
    $fmt=static function(string $value):string{
        $value=trim($value);
        if($value==='')return '';
        foreach(['d/m/Y H:i','d/m/Y','Y-m-d\\TH:i:s','Y-m-d\\TH:i','Y-m-d'] as $format){
            $dt=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('America/Monterrey'));
            if($dt instanceof DateTimeImmutable)return $dt->format(str_contains($format,'H:i')?'d-m-Y H:i:s':'Y-m-d');
        }
        return $value;
    };
    $before=trim((string)($payload['termino']??$payload['inicioCrematorio']??''));
    $body='<div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.45;color:#111;">'
        .'<p><strong>Referencia:</strong> '.rc_escape($numero).'</p><br>'
        .'<p><strong>Nombre de Fallecido:</strong> '.rc_escape($fallecido).'</p><br>'
        .'<p><strong>Fecha Nacimiento:</strong> '.rc_escape($fmt((string)($payload['fechaNacimiento']??''))).'<br>'
        .'<strong>Fecha Defuncion:</strong> '.rc_escape($fmt((string)($payload['fechaDefuncion']??''))).'</p><br>'
        .'<p style="color:#c00000;font-style:italic;"><strong>***Tenerla lista para antes de:</strong> '.rc_escape($fmt($before)).'<strong>***</strong></p></div>';
    return rc_send_graph_mail('Solicitud de Placa Urna Servicio: '.($numero!==''?$numero:'Sin numero'),$body,rc_plate_recipients(),[$attachment],true);
}
