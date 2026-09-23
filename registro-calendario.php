<?php

declare(strict_types=1);

/**
 * FASE 1 - Calendario interno de Registro de Servicios.
 *
 * Este modulo es completamente aislado del resto del Portal.
 * No modifica Solicitud de Venta, Reportes, Dashboard ni Power Automate.
 *
 * Requiere en /home/juanpab1/portal-config/config.php:
 *
 * 'registro_servicios_calendar_enabled' => true,
 * 'registro_servicios_calendar_mailbox' => 'sistemas@juanpablo.com.mx',
 * 'registro_servicios_calendar_id' => 'AAMk...Eventos Capillas...',
 *
 * Y una app DEDICADA en registro_servicios_* con permiso
 * Microsoft Graph > Calendars.ReadWrite > Application.
 */

function rs_calendar_private_config(): array
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
        'enabled' => (bool)($raw['registro_servicios_calendar_enabled'] ?? false),
        'mailbox' => trim((string)($raw['registro_servicios_calendar_mailbox'] ?? 'sistemas@juanpablo.com.mx')),
        'calendarId' => trim((string)($raw['registro_servicios_calendar_id'] ?? '')),
    ];
}

function rs_calendar_parse_local(string $value): DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        throw new RuntimeException('Falta una fecha/hora requerida para crear el evento.');
    }

    foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'd/m/Y H:i'] as $format) {
        $dt = DateTimeImmutable::createFromFormat(
            $format,
            $value,
            new DateTimeZone('America/Monterrey')
        );
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    throw new RuntimeException('Fecha/hora no valida para calendario: ' . $value);
}

function rs_calendar_local_graph_value(string $value): string
{
    return rs_calendar_parse_local($value)->format('Y-m-d\\TH:i:s');
}

function rs_calendar_to_utc(string $value): string
{
    return rs_calendar_parse_local($value)
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\\TH:i:s');
}

function rs_calendar_date_display(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';

    foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'd/m/Y H:i', 'Y-m-d'] as $format) {
        $dt = DateTimeImmutable::createFromFormat(
            $format,
            $value,
            new DateTimeZone('America/Monterrey')
        );
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format(str_contains($format, 'H:i') ? 'd-m-Y H:i' : 'd-m-Y');
        }
    }
    return $value;
}

function rs_calendar_escape(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array{status:int,json:array<string,mixed>,body:string} */
function rs_graph_request(string $method, string $url, string $token, ?array $jsonBody = null): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la conexion con Microsoft Graph.');
    }

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
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            (string)json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('La conexion con Microsoft Graph fallo: ' . $error);
    }

    $decoded = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $detail = '';
        if (is_array($decoded)) {
            $detail = trim((string)($decoded['error']['message'] ?? ''));
        }
        if ($detail === '') {
            $detail = mb_substr(trim((string)$response), 0, 1500);
        }
        throw new RuntimeException(
            'Microsoft Graph respondio HTTP ' . $status .
            ($detail !== '' ? ': ' . $detail : '.')
        );
    }

    return [
        'status' => $status,
        'json' => is_array($decoded) ? $decoded : [],
        'body' => (string)$response,
    ];
}

/**
 * Crea el evento en "Eventos Capillas".
 *
 * Las horas recibidas del formulario se interpretan SIEMPRE como
 * America/Monterrey. Para Microsoft Graph se conserva la hora local
 * y se etiqueta con la zona de Outlook "Central Standard Time (Mexico)".
 * Asi Outlook conserva tambien la zona horaria original del evento.
 *
 * @return array{enabled:bool,created:bool,eventId?:string,webLink?:string}
 */
function rs_calendar_create_event(array $payload, array $sharePointConfig): array
{
    $calendar = rs_calendar_private_config();
    if (!$calendar['enabled']) {
        return ['enabled' => false, 'created' => false];
    }

    if (($sharePointConfig['source'] ?? '') !== 'registro_servicios') {
        throw new RuntimeException(
            'Calendario Fase 1 requiere credenciales dedicadas registro_servicios_*; ' .
            'no se utilizara la app de Solicitud de Venta.'
        );
    }

    if ($calendar['mailbox'] === '' || $calendar['calendarId'] === '') {
        throw new RuntimeException(
            'Falta configurar registro_servicios_calendar_mailbox o registro_servicios_calendar_id.'
        );
    }

    $inicio = rs_calendar_local_graph_value((string)($payload['inicio'] ?? ''));
    $termino = rs_calendar_local_graph_value((string)($payload['termino'] ?? ''));

    $subjectParts = array_filter([
        trim((string)($payload['ubicacion'] ?? '')),
        trim((string)($payload['sala'] ?? '')),
        trim((string)($payload['referencia'] ?? '')),
    ], static fn(string $v): bool => $v !== '');
    $subject = implode(', ', $subjectParts);
    if ($subject === '') {
        $subject = 'Evento de Capillas';
    }

    $lines = [];
    $lines[] = '<strong>EVENTO:</strong> de ' . rs_calendar_escape(rs_calendar_date_display((string)($payload['inicio'] ?? '')))
        . ' a ' . rs_calendar_escape(rs_calendar_date_display((string)($payload['termino'] ?? '')));
    $lines[] = '<strong>EXEQUIA:</strong> ' . rs_calendar_escape(rs_calendar_date_display((string)($payload['horaExequia'] ?? '')));
    $lines[] = '';
    $lines[] = '<strong>UBICACION:</strong> ' . rs_calendar_escape((string)($payload['ubicacion'] ?? ''));
    $lines[] = '<strong>SALA:</strong> ' . rs_calendar_escape((string)($payload['sala'] ?? ''));
    $lines[] = '<strong>PREVISION/USO INMEDIATO:</strong> ' . rs_calendar_escape((string)($payload['prevision'] ?? ''));
    $lines[] = '<strong>UBICACION DE RESCATE:</strong> ' . rs_calendar_escape((string)($payload['ubicacionRescate'] ?? ''));
    $lines[] = '<strong>MOTIVO DE FALLECIMIENTO:</strong> ' . rs_calendar_escape((string)($payload['motivo'] ?? ''));
    $lines[] = '<strong>SERVICIO:</strong> ' . rs_calendar_escape((string)($payload['servicio'] ?? ''));

    $rescate = array_values(array_filter([
        trim((string)($payload['rescate1'] ?? '')),
        trim((string)($payload['rescate2'] ?? '')),
    ], static fn(string $v): bool => $v !== ''));
    $lines[] = '<strong>PERSONAL DE RESCATE:</strong> ' . rs_calendar_escape(implode(' y ', $rescate));
    $lines[] = '';
    $lines[] = '<strong>TITULAR:</strong> ' . rs_calendar_escape((string)($payload['titular'] ?? ''));
    $lines[] = '<strong>FALLECIDO(A):</strong> ' . rs_calendar_escape((string)($payload['fallecido'] ?? ''));
    $lines[] = '<strong>FECHA DE NACIMIENTO:</strong> ' . rs_calendar_escape(rs_calendar_date_display((string)($payload['fechaNacimiento'] ?? '')));
    $lines[] = '<strong>FECHA DE DEFUNCION:</strong> ' . rs_calendar_escape(rs_calendar_date_display((string)($payload['fechaDefuncion'] ?? '')));
    $lines[] = '<strong>EDAD:</strong> ' . rs_calendar_escape((string)($payload['edad'] ?? ''));
    $lines[] = '';
    $lines[] = '<strong>REFERENCIA:</strong> ' . rs_calendar_escape((string)($payload['referencia'] ?? ''));
    $lines[] = '<strong>NUMERO DE SERVICIO:</strong> ' . rs_calendar_escape((string)($payload['numeroReferencia'] ?? ''));
    $lines[] = '<strong>VENDEDOR:</strong> ' . rs_calendar_escape((string)($payload['personalVenta'] ?? ''));

    $bodyHtml = implode('<br>', $lines);

    $event = [
        'subject' => $subject,
        'body' => [
            'contentType' => 'HTML',
            'content' => $bodyHtml,
        ],
        // Se conserva la hora local y la zona original para que tanto la vista
        // rapida como el editor de Outlook muestren la misma hora.
        'start' => [
            'dateTime' => $inicio,
            'timeZone' => 'Central Standard Time (Mexico)',
        ],
        'end' => [
            'dateTime' => $termino,
            'timeZone' => 'Central Standard Time (Mexico)',
        ],
        'location' => [
            'displayName' => trim((string)($payload['ubicacion'] ?? '')),
        ],
        'showAs' => 'busy',
        'isReminderOn' => true,
        'reminderMinutesBeforeStart' => 30,
        'allowNewTimeProposals' => false,
    ];

    $graphToken = rs_graph_token($sharePointConfig);
    $url = 'https://graph.microsoft.com/v1.0/users/'
        . rawurlencode($calendar['mailbox'])
        . '/calendars/'
        . rawurlencode($calendar['calendarId'])
        . '/events';

    $created = rs_graph_request('POST', $url, $graphToken, $event)['json'];

    return [
        'enabled' => true,
        'created' => true,
        'eventId' => trim((string)($created['id'] ?? '')),
        'webLink' => trim((string)($created['webLink'] ?? '')),
    ];
}
