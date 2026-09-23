<?php

declare(strict_types=1);

/**
 * Diagnostico temporal de Microsoft Graph para Registro de Servicios.
 * NO crea, modifica ni elimina eventos.
 * NO expone access tokens, client secrets ni credenciales.
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

    $url = 'https://graph.microsoft.com/v1.0/users/'
        . rawurlencode($mailbox)
        . '/calendars/'
        . rawurlencode($calendarId)
        . '?$select=id,name,owner,canEdit';

    $calendar = rs_graph_request('GET', $url, $token)['json'];

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
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
