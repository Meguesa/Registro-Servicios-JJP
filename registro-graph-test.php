<?php

declare(strict_types=1);

/**
 * Diagnostico temporal de Microsoft Graph para Registro de Servicios.
 *
 * Por defecto SOLO valida autenticacion y acceso al calendario.
 * Si se abre con ?crear=1 crea UN evento controlado de prueba:
 * 24/09/2026 de 13:00 a 14:00 hora America/Monterrey.
 *
 * No expone access tokens, client secrets ni credenciales.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/registro-sharepoint.php';
require_once __DIR__ . '/registro-calendario.php';

try {
    $configPath = '/home/juanpab1/portal-config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    }

    $raw = require $configPath;
    if (!is_array($raw)) {
        throw new RuntimeException('La configuracion privada del Portal no es valida.');
    }

    $mailbox = trim((string)($raw['registro_servicios_calendar_mailbox'] ?? ''));
    $calendarId = trim((string)($raw['registro_servicios_calendar_id'] ?? ''));

    if ($mailbox === '' || $calendarId === '') {
        throw new RuntimeException(
            'Falta registro_servicios_calendar_mailbox o registro_servicios_calendar_id.'
        );
    }

    $token = rs_graph_token();

    $calendarUrl = 'https://graph.microsoft.com/v1.0/users/'
        . rawurlencode($mailbox)
        . '/calendars/'
        . rawurlencode($calendarId)
        . '?$select=id,name,owner,canEdit';

    $calendar = rs_graph_request('GET', $calendarUrl, $token)['json'];

    $crear = isset($_GET['crear']) && (string)$_GET['crear'] === '1';

    if (!$crear) {
        echo json_encode([
            'ok' => true,
            'message' => 'Autenticacion Microsoft Graph correcta. No se creo ningun evento.',
            'mailbox' => $mailbox,
            'calendar' => [
                'name' => (string)($calendar['name'] ?? ''),
                'canEdit' => (bool)($calendar['canEdit'] ?? false),
                'owner' => [
                    'name' => (string)($calendar['owner']['name'] ?? ''),
                    'address' => (string)($calendar['owner']['address'] ?? ''),
                ],
            ],
            'calendarEnabled' => (bool)($raw['registro_servicios_calendar_enabled'] ?? false),
            'nextTest' => 'Agrega ?crear=1 a esta URL para crear el evento controlado de prueba.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    if (!(bool)($calendar['canEdit'] ?? false)) {
        throw new RuntimeException('El calendario fue encontrado, pero Microsoft Graph indica canEdit=false.');
    }

    $inicioLocal = '2026-09-24T13:00:00';
    $terminoLocal = '2026-09-24T14:00:00';

    $inicioGraph = rs_calendar_local_graph_value($inicioLocal);
    $terminoGraph = rs_calendar_local_graph_value($terminoLocal);

    $event = [
        'subject' => '[PRUEBA GITHUB] Registro de Servicios - Calendario',
        'body' => [
            'contentType' => 'HTML',
            'content' => '<strong>PRUEBA CONTROLADA - FASE 1</strong><br>'
                . 'Este evento fue creado directamente por Registro de Servicios mediante Microsoft Graph.<br>'
                . 'Hora capturada: 24/09/2026 13:00 a 14:00 America/Monterrey.<br>'
                . 'No proviene de Power Automate.',
        ],
        'start' => [
            'dateTime' => $inicioGraph,
            'timeZone' => 'Central Standard Time (Mexico)',
        ],
        'end' => [
            'dateTime' => $terminoGraph,
            'timeZone' => 'Central Standard Time (Mexico)',
        ],
        'location' => [
            'displayName' => 'PRUEBA - Capillas',
        ],
        'showAs' => 'busy',
        'isReminderOn' => false,
        'allowNewTimeProposals' => false,
    ];

    $eventsUrl = 'https://graph.microsoft.com/v1.0/users/'
        . rawurlencode($mailbox)
        . '/calendars/'
        . rawurlencode($calendarId)
        . '/events';

    $created = rs_graph_request('POST', $eventsUrl, $token, $event)['json'];

    echo json_encode([
        'ok' => true,
        'message' => 'Evento controlado creado correctamente.',
        'calendar' => (string)($calendar['name'] ?? ''),
        'capturedLocalTime' => [
            'start' => '24/09/2026 13:00',
            'end' => '24/09/2026 14:00',
            'timeZone' => 'America/Monterrey',
        ],
        'sentToGraphLocal' => [
            'start' => $inicioGraph,
            'end' => $terminoGraph,
            'timeZone' => 'Central Standard Time (Mexico)',
        ],
        'event' => [
            'id' => (string)($created['id'] ?? ''),
            'subject' => (string)($created['subject'] ?? ''),
            'webLink' => (string)($created['webLink'] ?? ''),
            'startReturned' => $created['start'] ?? null,
            'endReturned' => $created['end'] ?? null,
        ],
        'calendarEnabled' => (bool)($raw['registro_servicios_calendar_enabled'] ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
