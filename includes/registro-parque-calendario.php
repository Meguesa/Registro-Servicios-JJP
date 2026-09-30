<?php
declare(strict_types=1);

function rp_calendar_private_config(): array
{
    $configPath = '/home/juanpab1/portal-config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    }

    $raw = require $configPath;
    if (!is_array($raw)) {
        throw new RuntimeException('La configuracion privada del Portal no es valida.');
    }

    return [
        'enabled' => (bool)($raw['registro_servicios_parque_calendar_enabled'] ?? true),
        'mailbox' => trim((string)($raw['registro_servicios_parque_calendar_mailbox'] ?? 'sistemas@juanpablo.com.mx')),
        'calendarId' => trim((string)($raw['registro_servicios_parque_calendar_id'] ?? '')),
        'calendarName' => trim((string)($raw['registro_servicios_parque_calendar_name'] ?? 'Eventos Parque')),
    ];
}

function rp_calendar_parse_local(string $value): DateTimeImmutable
{
    $value = trim($value);
    foreach (['d/m/Y H:i','Y-m-d H:i','Y-m-d\\TH:i:s','Y-m-d\\TH:i'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('America/Monterrey'));
        if ($dt instanceof DateTimeImmutable) return $dt;
    }
    throw new RuntimeException('Fecha/hora no valida para calendario: ' . $value);
}

function rp_calendar_norm(string $value): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
    if (is_string($ascii) && $ascii !== '') $value = $ascii;
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function rp_graph_request(string $method, string $url, string $token, ?array $jsonBody = null): array
{
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('No fue posible inicializar Microsoft Graph.');

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($jsonBody !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, (string)json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) throw new RuntimeException('Microsoft Graph no respondio: ' . $error);
    $decoded = json_decode((string)$response, true);

    if ($status < 200 || $status >= 300) {
        $detail = is_array($decoded) ? trim((string)($decoded['error']['message'] ?? '')) : '';
        if ($detail === '') $detail = mb_substr(trim((string)$response), 0, 1200);
        throw new RuntimeException('Microsoft Graph respondio HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '.'));
    }

    return is_array($decoded) ? $decoded : [];
}

function rp_calendar_resolve_id(array $config, string $token): string
{
    if ($config['calendarId'] !== '') return $config['calendarId'];

    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($config['mailbox']) . '/calendars?$select=id,name&$top=100';
    $data = rp_graph_request('GET', $url, $token);
    $target = rp_calendar_norm($config['calendarName']);

    foreach (($data['value'] ?? []) as $calendar) {
        if (!is_array($calendar)) continue;
        if (rp_calendar_norm((string)($calendar['name'] ?? '')) === $target) {
            $id = trim((string)($calendar['id'] ?? ''));
            if ($id !== '') return $id;
        }
    }

    throw new RuntimeException('No se encontro el calendario de Parque "' . $config['calendarName'] . '".');
}

function rp_calendar_create_event(array $payload): array
{
    $config = rp_calendar_private_config();
    if (!$config['enabled']) {
        return ['enabled'=>false,'created'=>false,'reason'=>'Calendario de Parque deshabilitado.'];
    }

    if ($config['mailbox'] === '') {
        throw new RuntimeException('Falta configurar el mailbox del calendario de Parque.');
    }

    $inicio = rp_calendar_parse_local((string)($payload['fechaHoraInicio'] ?? ''));
    $fin = rp_calendar_parse_local((string)($payload['fechaHoraFin'] ?? ''));

    $seccion = trim((string)($payload['seccion'] ?? ''));
    $manzana = trim((string)($payload['manzana'] ?? ''));
    $lote = trim((string)($payload['numLoteNicho'] ?? ''));
    $fallecido = trim((string)($payload['fallecido'] ?? ''));
    $tipoServicio = trim((string)($payload['tipoServicio'] ?? ''));

    $serviceNorm = rp_calendar_norm((string)($payload['servicio'] ?? ''));
    $prefix = $serviceNorm === 'totalservicecomplemento'
        ? 'TSC'
        : ($serviceNorm === 'totalservice' ? 'TS' : '');
    $ubicacion = implode(' - ', array_values(array_filter([
        $prefix,
        $seccion,
        $lote,
        $manzana,
    ], static fn(string $v): bool => $v !== '')));

    $subject = implode(' - ', array_values(array_filter([
        'PARQUE',
        $tipoServicio,
        $fallecido,
        $ubicacion,
    ], static fn(string $v): bool => $v !== '')));

    if (!empty($payload['_previewMode'])) {
        $subject = '(PRUEBA) ' . $subject;
    }

    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rows = [
        ['VELACION', (string)($payload['velacion'] ?? '')],
        ['PREVISION/USO INMEDIATO', (string)($payload['previsionUsoInmediato'] ?? '')],
        ['TIPO DE SERVICIO', $tipoServicio],
        ['SERVICIO', (string)($payload['servicio'] ?? '')],
        ['ASISTENTE FUNERARIO', (string)($payload['asistenteFunerarioTexto'] ?? '')],
        ['SECCION', $seccion],
        ['MANZANA', $manzana],
        ['LOTE/NICHO', $lote],
        ['DESTAPE', (string)($payload['destape'] ?? '')],
        ['TIPO DE PLACA', (string)($payload['tipoPlaca'] ?? '')],
        ['NOMBRE DE FAMILIA', (string)($payload['nombreFamilia'] ?? '')],
        ['TITULAR', (string)($payload['titular'] ?? '')],
        ['FALLECIDO(A)', $fallecido],
        ['PARENTESCO', (string)($payload['parentescoTitular'] ?? '')],
        ['ESTATUS DE LIQUIDACION', (string)($payload['estatusLiquidacion'] ?? '')],
        ['NUMERO DE CONTRATO', (string)($payload['numeroContrato'] ?? '')],
        ['OBSERVACIONES', (string)($payload['observaciones'] ?? '')],
    ];

    $body = [];
    foreach ($rows as [$label,$value]) {
        $body[] = '<strong>' . $esc($label) . ':</strong> ' . $esc(trim($value) !== '' ? trim($value) : 'NO CAPTURADO');
    }

    $event = [
        'subject' => $subject !== '' ? $subject : 'Servicio Parque',
        'body' => ['contentType'=>'HTML','content'=>implode('<br>', $body)],
        'start' => ['dateTime'=>$inicio->format('Y-m-d\\TH:i:s'),'timeZone'=>'Central Standard Time (Mexico)'],
        'end' => ['dateTime'=>$fin->format('Y-m-d\\TH:i:s'),'timeZone'=>'Central Standard Time (Mexico)'],
        'location' => ['displayName'=>$ubicacion !== '' ? $ubicacion : 'Parque Jardines de Juan Pablo'],
        'showAs' => 'busy',
        'isReminderOn' => true,
        'reminderMinutesBeforeStart' => 30,
        'allowNewTimeProposals' => false,
    ];

    $token = rs_graph_token();
    $calendarId = rp_calendar_resolve_id($config, $token);
    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($config['mailbox'])
        . '/calendars/' . rawurlencode($calendarId) . '/events';

    $created = rp_graph_request('POST', $url, $token, $event);

    return [
        'enabled'=>true,
        'created'=>true,
        'eventId'=>trim((string)($created['id'] ?? '')),
        'webLink'=>trim((string)($created['webLink'] ?? '')),
        'calendarName'=>$config['calendarName'],
    ];
}
