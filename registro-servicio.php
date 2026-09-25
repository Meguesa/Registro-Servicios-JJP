<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/registro-storage.php';

function rs_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


function rs_is_preview_mode(): bool
{
    return str_contains(
        (string)($_SERVER['REQUEST_URI'] ?? ''),
        '/registro-servicios-preview/'
    );
}

function rs_email_escape(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Envio controlado del PREVIEW. Nunca se ejecuta fuera de
 * /registro-servicios-preview/.
 *
 * @param array<int,array{name:string,contentType:string,bytes:string}> $attachments
 */
function rs_send_controlled_test_email(array $payload, array $attachments): array
{
    if (!rs_is_preview_mode()) {
        return [
            'enabled' => false,
            'sent' => false,
            'recipients' => [],
            'attachmentNames' => [],
        ];
    }

    $sender = 'sistemas@juanpablo.com.mx';
    $recipients = [
        'sistemas@juanpablo.com.mx',
        'gabriel.guerra@juanpablo.com.mx',
        'it@juanpablo.com.mx',
    ];

    // Orden operativo solicitado para el correo:
    // 1) Informacion_Servicio, 2) Obituario, 3) Informacion_Venta,
    // 4) Placa, 5) Esquela, 6) Carta, 7) documentos adjuntos.
    $rankedAttachments = [];
    foreach (array_values($attachments) as $index => $attachment) {
        $name = trim((string)($attachment['name'] ?? ''));
        $normalizedName = mb_strtolower($name, 'UTF-8');

        $rank = 70;
        if (str_contains($normalizedName, 'informacion_servicio')) {
            $rank = 10;
        } elseif (str_contains($normalizedName, 'obituario')) {
            $rank = 20;
        } elseif (str_contains($normalizedName, 'informacion_venta')) {
            $rank = 30;
        } elseif (str_starts_with($normalizedName, 'placa-') || str_contains($normalizedName, 'placa_')) {
            $rank = 40;
        } elseif (str_contains($normalizedName, 'esquela')) {
            $rank = 50;
        } elseif (str_contains($normalizedName, 'carta_servicio_otorgado')) {
            $rank = 60;
        }

        $rankedAttachments[] = [
            'rank' => $rank,
            'index' => $index,
            'attachment' => $attachment,
        ];
    }

    usort(
        $rankedAttachments,
        static fn(array $a, array $b): int =>
            ($a['rank'] <=> $b['rank']) ?: ($a['index'] <=> $b['index'])
    );

    $graphAttachments = [];
    $attachmentNames = [];
    $seen = [];

    foreach ($rankedAttachments as $ranked) {
        $attachment = is_array($ranked['attachment'] ?? null)
            ? $ranked['attachment']
            : [];

        $name = trim((string)($attachment['name'] ?? ''));
        $bytes = (string)($attachment['bytes'] ?? '');
        $contentType = trim((string)($attachment['contentType'] ?? 'application/octet-stream'));

        if ($name === '' || $bytes === '') continue;

        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $graphAttachments[] = [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $name,
            'contentType' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'contentBytes' => base64_encode($bytes),
        ];
        $attachmentNames[] = $name;
    }

    $formatDateTime = static function (string $value): string {
        $value = trim($value);
        if ($value === '') return '';

        foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'd/m/Y H:i:s', 'd/m/Y H:i'] as $format) {
            $dt = DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone('America/Monterrey')
            );
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('d-m-Y H:i:s');
            }
        }

        return $value;
    };

    $formatDate = static function (string $value): string {
        $value = trim($value);
        if ($value === '') return '';

        foreach (['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            $dt = DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone('America/Monterrey')
            );
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('d-m-Y');
            }
        }

        return $value;
    };

    $numeroServicio = trim((string)($payload['numeroServicio'] ?? ''));
    $fallecido = trim((string)($payload['fallecido'] ?? ''));
    $servicio = trim((string)($payload['servicio'] ?? ''));
    $referencia = trim((string)($payload['referencia'] ?? ''));
    $ubicacion = trim((string)($payload['ubicacion'] ?? ''));
    $sala = trim((string)($payload['sala'] ?? ''));
    $inicio = $formatDateTime((string)($payload['inicio'] ?? ''));
    $termino = $formatDateTime((string)($payload['termino'] ?? ''));
    $llevaExequia = (bool)($payload['llevaExequia'] ?? false);
    $horaExequia = $formatDateTime((string)($payload['horaExequia'] ?? ''));
    $prevision = trim((string)($payload['prevision'] ?? ''));
    $ubicacionRescate = trim((string)($payload['ubicacionRescate'] ?? ''));
    $motivo = trim((string)($payload['motivo'] ?? ''));
    $rescate1 = trim((string)($payload['rescate1'] ?? ''));
    $rescate2 = trim((string)($payload['rescate2'] ?? ''));
    $titular = trim((string)($payload['titular'] ?? ''));
    $fechaNacimiento = $formatDate((string)($payload['fechaNacimiento'] ?? ''));
    $fechaDefuncion = $formatDate((string)($payload['fechaDefuncion'] ?? ''));
    $edad = trim((string)($payload['edad'] ?? ''));
    $vendedor = trim((string)($payload['personalVenta'] ?? ''));

    $personalRescate = implode(
        ' Y ',
        array_values(array_filter(
            [$rescate1, $rescate2],
            static fn(string $value): bool => $value !== ''
        ))
    );

    $evento = '';
    if ($inicio !== '' && $termino !== '') {
        $evento = 'de ' . $inicio . ' a ' . $termino;
    } elseif ($inicio !== '') {
        $evento = 'de ' . $inicio;
    }

    $exequia = $llevaExequia
        ? ($horaExequia !== '' ? $horaExequia : 'PENDIENTE')
        : 'NO APLICA';

    $subject = '[PRUEBA CONTROLADA] Nuevo evento de Capillas: '
        . ($ubicacion !== '' ? $ubicacion : 'Sin ubicación')
        . ($sala !== '' ? ', ' . $sala : '')
        . ($referencia !== '' ? ', ' . $referencia : '');

    $line = static function (string $label, string $value): string {
        return '<div style="margin:0 0 3px 0;"><strong>'
            . rs_email_escape($label)
            . ':</strong> '
            . rs_email_escape($value !== '' ? $value : 'NO CAPTURADO')
            . '</div>';
    };

    $bodyHtml = ''
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.35;color:#111;">'
        . '<div style="margin:0 0 14px 0;padding:10px 12px;border:1px solid #d8b45a;background:#fff8e6;">'
        . '<strong>PRUEBA CONTROLADA - REGISTRO DE SERVICIOS</strong><br>'
        . 'Este correo fue generado desde el módulo Preview. '
        . '<strong>No se agregó ningún evento al calendario.</strong>'
        . '</div>'
        . $line('EVENTO', $evento)
        . $line('EXEQUIA', $exequia)
        . '<br>'
        . $line('UBICACIÓN', $ubicacion)
        . $line('SALA', $sala)
        . $line('PREVISIÓN/USO INMEDIATO', $prevision)
        . $line('UBICACIÓN DE RESCATE', $ubicacionRescate)
        . $line('MOTIVO DE FALLECIMIENTO', $motivo)
        . $line('SERVICIO', $servicio)
        . $line('PERSONAL DE RESCATE', $personalRescate)
        . '<br>'
        . $line('TITULAR', $titular)
        . $line('FALLECIDO(A)', $fallecido)
        . $line('FECHA DE NACIMIENTO', $fechaNacimiento)
        . $line('FECHA DE DEFUNCIÓN', $fechaDefuncion)
        . $line('EDAD', $edad)
        . '<br>'
        . $line('REFERENCIA', $referencia)
        . $line('NÚMERO DE SERVICIO', $numeroServicio)
        . $line('VENDEDOR', $vendedor)
        . '</div>';

    $toRecipients = array_map(
        static fn(string $address): array => [
            'emailAddress' => ['address' => $address],
        ],
        $recipients
    );

    $request = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $bodyHtml,
            ],
            'toRecipients' => $toRecipients,
            'attachments' => $graphAttachments,
        ],
        'saveToSentItems' => true,
    ];

    $token = rs_graph_token();
    $url = 'https://graph.microsoft.com/v1.0/users/'
        . rawurlencode($sender)
        . '/sendMail';

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar el correo de Microsoft Graph.');
    }

    $json = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('No fue posible preparar el correo controlado.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('El envio de correo fallo: ' . $error);
    }

    $decoded = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $detail = is_array($decoded)
            ? trim((string)($decoded['error']['message'] ?? ''))
            : '';
        if ($detail === '') {
            $detail = mb_substr(trim((string)$response), 0, 1600);
        }
        throw new RuntimeException(
            'Microsoft Graph correo respondio HTTP ' . $status
            . ($detail !== '' ? ': ' . $detail : '.')
        );
    }

    return [
        'enabled' => true,
        'sent' => true,
        'sender' => $sender,
        'recipients' => $recipients,
        'attachmentNames' => $attachmentNames,
    ];
}

try {
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $bootstrap = $root . '/includes/bootstrap.php';
    $registroSharePoint = __DIR__ . '/registro-sharepoint.php';
    $registroCalendario = __DIR__ . '/registro-calendario.php';
    $registroPlaca = __DIR__ . '/registro-placa.php';
    $registroCarta = __DIR__ . '/registro-carta.php';
    $registroImagenes = __DIR__ . '/registro-imagenes.php';
    if (!is_file($bootstrap) || !is_file($registroSharePoint) || !is_file($registroCalendario) || !is_file($registroPlaca) || !is_file($registroCarta) || !is_file($registroImagenes)) {
        throw new RuntimeException('No se encontraron los componentes necesarios de Registro de Servicios.');
    }
    require_once $bootstrap;
    require_once $registroSharePoint;
    require_once $registroCalendario;
    require_once $registroPlaca;
    require_once $registroCarta;
    require_once $registroImagenes;
    portal_require_authentication();
    $storageCtx = rs_storage_bootstrap();
    $draftIdRaw = trim((string) ($_POST['draftId'] ?? ''));
    $draftId = $draftIdRaw !== '' ? rs_safe_id($draftIdRaw) : '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        rs_json(405, ['ok' => false, 'message' => 'Metodo no permitido.']);
    }

    $payloadRaw = (string) ($_POST['payload'] ?? '');
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        rs_json(400, ['ok' => false, 'message' => 'La informacion del formulario no es valida.']);
    }

    $required = ['numeroReferencia', 'servicio', 'ubicacion', 'prevision', 'numeroServicio', 'titular', 'fallecido'];
    foreach ($required as $key) {
        if (trim((string) ($payload[$key] ?? '')) === '') {
            rs_json(422, ['ok' => false, 'message' => 'Falta el campo obligatorio: ' . $key . '.']);
        }
    }

    // Configuracion AISLADA de Registro de Servicios.
    // No utiliza portal-sharepoint.php ni modifica la autenticacion de otras herramientas.
    $config = rs_sharepoint_config();

    // Eventos Capillas vive en el sitio Operaciones. No debe heredarse el
    // siteId configurado para otros modulos del Portal (p. ej. Solicitud de Venta),
    // porque eso provoca HTTP 404 al buscar la lista correcta en otro sitio.
    $host = 'meguesajdjp.sharepoint.com';
    $siteUrl = 'https://' . $host . '/sites/Operaciones';
    $listTitle = 'Eventos Capillas';
    $token = rs_sharepoint_token($config, $host);

    /** @return array{status:int,body:string,json:array<string,mixed>} */
    function rs_request(string $method, string $url, string $token, ?string $body = null, array $headers = []): array
    {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('No fue posible inicializar la conexion con SharePoint.');
        $baseHeaders = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json;odata=nometadata',
        ];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($baseHeaders, $headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false) throw new RuntimeException('La conexion con SharePoint fallo: ' . $error);
        $decoded = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300) {
        
            $detail = '';
        
            if (is_array($decoded)) {
        
                $messageNode = $decoded['error']['message'] ?? '';
        
                if (is_array($messageNode)) {
                    $detail = (string) ($messageNode['value'] ?? '');
                } elseif (is_string($messageNode)) {
                    $detail = $messageNode;
                }
            }
        
            // Si SharePoint no entregó el error en el formato esperado,
            // mostrar la respuesta original.
            if ($detail === '') {
        
                $raw = trim((string) $response);
        
                if ($raw !== '') {
                    $detail = mb_substr($raw, 0, 1800);
                }
            }
        
            throw new RuntimeException(
                'SharePoint respondio HTTP ' .
                $status .
                ($detail !== '' ? ': ' . $detail : '.')
            );
        }
        return ['status' => $status, 'body' => (string) $response, 'json' => is_array($decoded) ? $decoded : []];
    }

    function rs_norm(string $value): string
    {
        $value = trim($value);

        // SharePoint codifica espacios y caracteres especiales en InternalName
        // con secuencias como _x0020_. Decodificarlas permite comparar de forma
        // confiable tanto Title como InternalName.
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
    function rs_fields_by_norm(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $title = trim((string) ($row['Title'] ?? ''));
            $internal = trim((string) ($row['InternalName'] ?? ''));
            foreach ([$title, $internal] as $candidate) {
                $key = rs_norm($candidate);
                if ($key !== '' && !isset($out[$key])) $out[$key] = $row;
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    function rs_find_field(array $fields, array $aliases): ?array
    {
        foreach ($aliases as $alias) {
            $key = rs_norm((string) $alias);
            if ($key !== '' && isset($fields[$key])) return $fields[$key];
        }
        return null;
    }

    function rs_local_datetime_to_utc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        $formats = ['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'd/m/Y H:i'];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('America/Monterrey'));
            if ($dt instanceof DateTimeImmutable) {
                return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
            }
        }
        return null;
    }

    function rs_date_only(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));
            if ($dt instanceof DateTimeImmutable) return $dt->format('Y-m-d\\T00:00:00\\Z');
        }
        return null;
    }

    function rs_add_value(array &$fieldsPayload, array $fieldIndex, array $aliases, mixed $value, bool $skipBlank = true): void
    {
        $field = rs_find_field($fieldIndex, $aliases);
        if ($field === null) return;
        if ($skipBlank && ($value === null || $value === '' || $value === [])) return;

        $internal = (string) ($field['InternalName'] ?? '');
        if ($internal === '') return;
        $type = strtolower((string) ($field['TypeAsString'] ?? ''));

        if ($type === 'multichoice') {
            $values = is_array($value)
                ? array_values(array_filter(array_map('strval', $value), static fn($v) => trim($v) !== ''))
                : [(string) $value];

            // Con application/json;odata=nometadata SharePoint espera
            // directamente un arreglo JSON para columnas MultiChoice.
            // Enviar {__metadata,results} provoca InvalidClientQueryException:
            // "StartObject" inesperado; se esperaba "StartArray".
            $fieldsPayload[$internal] = $values;
            return;
        }
        if (in_array($type, ['boolean'], true)) {
            $fieldsPayload[$internal] = (bool) $value;
            return;
        }
        if (in_array($type, ['number', 'currency'], true)) {
            if ($value === '' || $value === null) return;
            $fieldsPayload[$internal] = (float) $value;
            return;
        }
        $fieldsPayload[$internal] = $value;
    }

    $listEsc = rawurlencode($listTitle);
    $fieldsUrl = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/fields"
        . '?$select=Title,InternalName,TypeAsString,Required,Hidden,ReadOnlyField';
    try {
        $fieldRows = rs_request('GET', $fieldsUrl, $token)['json']['value'] ?? [];
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (strpos($message, 'HTTP 403') !== false) {
            throw new RuntimeException(
                'Etapa CONSULTAR COLUMNAS: la aplicacion usada por Registro de Servicios no tiene permiso sobre el sitio Operaciones. ' .
                'Debe autorizarse la app ' . ($config['clientId'] ?? 'desconocida') . ' para https://meguesajdjp.sharepoint.com/sites/Operaciones. ' .
                $message,
                0,
                $e
            );
        }
        if (strpos($message, 'HTTP 404') !== false) {
            throw new RuntimeException(
                'Etapa CONSULTAR COLUMNAS: no se encontro la lista Eventos Capillas en el sitio Operaciones. ' . $message,
                0,
                $e
            );
        }
        throw new RuntimeException('Etapa CONSULTAR COLUMNAS: ' . $message, 0, $e);
    }
    $fieldIndex = rs_fields_by_norm(is_array($fieldRows) ? $fieldRows : []);

    $sp = [];
    rs_add_value($sp, $fieldIndex, ['ModoPrueba', 'Modo Prueba'], true, false);
    rs_add_value($sp, $fieldIndex, ['field_1', 'Numero de Referencia', 'Número de Referencia'], trim((string) ($payload['numeroReferencia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_32', 'Servicio', 'Tipo de Servicio'], trim((string) ($payload['servicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_39', 'Ubicación Servicio Capillas', 'Ubicacion Servicio Capillas'], trim((string) ($payload['ubicacion'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_40', 'Sala'], trim((string) ($payload['sala'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_36', 'Fecha y Hora Inicio'], rs_local_datetime_to_utc((string) ($payload['inicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_37', 'Fecha y Hora Termino', 'Fecha y Hora Término'], rs_local_datetime_to_utc((string) ($payload['termino'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Misa', 'Lleva exequia', 'Lleva exequia?'], (bool) ($payload['llevaExequia'] ?? false), false);
    rs_add_value($sp, $fieldIndex, ['field_45', 'Tiempo de Capillas'], trim((string) ($payload['tiempoCapillas'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_44', 'Hora Misa', 'Fecha y Hora Exequia'], rs_local_datetime_to_utc((string) ($payload['horaExequia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_9', 'Prevision/Uso Inmediato', 'Previsión/Uso Inmediato'], trim((string) ($payload['prevision'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_49', 'Tipo de Ataud/Urna', 'Tipo de Ataúd/Urna'], trim((string) ($payload['tipoAtaud'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_48', 'Numero de Servicio', 'Número de Servicio'], trim((string) ($payload['numeroServicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_46', 'Codigo', 'Código', 'Codigo de Servicio', 'Código de Servicio'], trim((string) ($payload['codigoServicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_47', 'Ataud/Urna', 'Ataúd/Urna'], trim((string) ($payload['codigoAtaud'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Referencia'], trim((string) ($payload['referencia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['RequierePlacadeUrna_x003f_', 'Requiere Placa', 'Requiere Placa de Urna', 'Requiere Placa de Urna?'], (bool) ($payload['requierePlaca'] ?? false), false);

    rs_add_value($sp, $fieldIndex, [
        'field_7',
        'Titular Responsable',
        'Titular/Responsable',
        'Titular / Responsable',
        'Titular',
        'Responsable'
    ], trim((string) ($payload['titular'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_2', 'Nombre de Fallecido (a)', 'Nombre Fallecido', 'Nombre de Fallecido(a)'], trim((string) ($payload['fallecido'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_4', 'Fecha Nacimiento Fallecido (a)', 'Fecha Nacimiento', 'Fecha de Nacimiento'], rs_date_only((string) ($payload['fechaNacimiento'] ?? '')));
    rs_add_value($sp, $fieldIndex, [
        'FechaDefuncion',
        'Fecha y Hora Defuncion Fallecido (a)',
        'Fecha y Hora Defunción Fallecido (a)',
        'Fecha y Hora Defuncion',
        'Fecha y Hora Defunción',
        'Fecha Defuncion',
        'Fecha Defunción'
    ], rs_local_datetime_to_utc((string) ($payload['fechaDefuncion'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Sexo'], trim((string) ($payload['sexo'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_3', 'Edad'], ($payload['edad'] ?? '') === '' ? null : (float) $payload['edad']);
    rs_add_value($sp, $fieldIndex, ['field_52', 'Ubicacion Post Capillas', 'Ubicación Post Capillas', 'Ubicacion Destino Final', 'Ubicación Destino Final'], trim((string) ($payload['destinoFinal'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_69', 'Embalsamador'], trim((string) ($payload['embalsamador'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_28', 'Personal Rescate 1'], trim((string) ($payload['rescate1'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_29', 'Personal Rescate 2'], trim((string) ($payload['rescate2'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_31', 'Ubicacion de Rescate', 'Ubicación de Rescate'], trim((string) ($payload['ubicacionRescate'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_30', 'Motivo de Fallecimiento'], trim((string) ($payload['motivo'] ?? '')));

    rs_add_value($sp, $fieldIndex, ['field_64', 'Referencia Crematorio'], trim((string) ($payload['referenciaCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['FechayHoraInicioCrematorio', 'Fecha y Hora Inicio Crematorio'], rs_local_datetime_to_utc((string) ($payload['inicioCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_68', 'Personal de Crematorio', 'Personal Crematorio'], trim((string) ($payload['personalCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['FechayHoraInhumaci_x00f3_n', 'Fecha y Hora Inhumacion', 'Fecha y Hora Inhumación'], rs_local_datetime_to_utc((string) ($payload['fechaHoraInhumacion'] ?? '')));

    rs_add_value($sp, $fieldIndex, ['field_10', 'Fecha Compra'], rs_date_only((string) ($payload['fechaCompra'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_11', 'Personal Venta'], trim((string) ($payload['personalVenta'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['field_12', 'Precio de Venta', 'Precio Venta'], ($payload['precioVenta'] ?? '') === '' ? null : (float) $payload['precioVenta']);

    // "Servicios Extra" es obligatorio en Eventos Capillas.
    // No obligar al usuario a elegir un concepto inexistente: si no hay extras,
    // guardar "No Aplica" de forma automatica.
    $serviciosExtra = is_array($payload['serviciosExtra'] ?? null)
        ? array_values(array_filter(
            array_map(static fn($v): string => trim((string)$v), $payload['serviciosExtra']),
            static fn(string $v): bool => $v !== ''
        ))
        : [];
    if ($serviciosExtra === []) {
        $serviciosExtra = ['No Aplica'];
    }
    rs_add_value($sp, $fieldIndex, ['ServiciosExtra', 'Servicios Extra', 'Servicios Adicionales'], $serviciosExtra, false);
    rs_add_value($sp, $fieldIndex, ['field_27', 'Venta Total Servicio'], ($payload['ventaTotal'] ?? '') === '' ? null : (float) $payload['ventaTotal']);

    $extraAliases = [
        'Misa y Coro' => ['Misa y Coro'],
        'Flores' => ['Flores'],
        'Cobro por Enfermedad' => ['Cobro por Enfermedad'],
        'Horas Extras' => ['Horas Extras'],
        'Cambio de Ataud' => ['Cambio de Ataud', 'Cambio de Ataúd'],
        'Cambio a Cremacion' => ['Cambio a Cremacion', 'Cambio a Cremación'],
        'Cambio de Capilla' => ['Cambio de Capilla'],
        'Cambio de Urna' => ['Cambio de Urna'],
        'Traslado' => ['Traslado'],
        'Resguardo' => ['Resguardo'],
        'Retiro de Marcapasos' => ['Retiro de Marcapasos'],
        'Sobrepeso' => ['Sobrepeso'],
        'Destape' => ['Destape'],
        'Impuestos' => ['Impuestos'],
    ];
    $extraAmounts = is_array($payload['extraAmounts'] ?? null) ? $payload['extraAmounts'] : [];
    foreach ($extraAliases as $label => $aliases) {
        $value = $extraAmounts[$label] ?? null;
        rs_add_value($sp, $fieldIndex, $aliases, ($value === '' || $value === null) ? null : (float) $value);
    }

    $titleField = rs_find_field($fieldIndex, ['Title']);
    if ($titleField !== null) {
        $internal = (string) ($titleField['InternalName'] ?? 'Title');
        if (!array_key_exists($internal, $sp)) {
            $sp[$internal] = trim((string) ($payload['referencia'] ?? '')) ?: trim((string) ($payload['numeroReferencia'] ?? ''));
        }
    }

    try {
        $digestData = rs_request('POST', $siteUrl . '/_api/contextinfo', $token, '', [
        'Accept: application/json;odata=nometadata',
        'Content-Type: application/json;odata=nometadata',
    ])['json'];
    } catch (Throwable $e) {
    
        $sentFields = implode(', ', array_keys($sp));
    
        throw new RuntimeException(
            'Etapa CREAR ELEMENTO: ' .
            $e->getMessage() .
            ($sentFields !== ''
                ? ' | Columnas enviadas: ' . $sentFields
                : ''),
            0,
            $e
        );
    }
    $digest = trim((string) ($digestData['FormDigestValue'] ?? ''));
    if ($digest === '') throw new RuntimeException('SharePoint no devolvio un FormDigest valido.');

    $createUrl = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items";
    try {
        $created = rs_request('POST', $createUrl, $token, (string) json_encode($sp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
        'Content-Type: application/json;odata=nometadata',
        'X-RequestDigest: ' . $digest,
    ])['json'];
    } catch (Throwable $e) {
        throw new RuntimeException('Etapa CREAR ELEMENTO: ' . $e->getMessage(), 0, $e);
    }

    $itemId = (int) ($created['Id'] ?? $created['ID'] ?? 0);
    if ($itemId <= 0) throw new RuntimeException('SharePoint creo el registro, pero no devolvio un ID utilizable.');

    // Archivos que formaran parte del correo controlado de Preview.
    $emailAttachments = [];

    // FASE 3: placa de urna generada internamente.
    // Resultado final: SOLO PNG, con nombre historico Placa-<ID>.png.
    // Se guarda en la misma carpeta utilizada actualmente:
    // Operaciones > Documentos > Automaticaciones > Eventos Capillas > Placas.
    $plateResult = [
        'required' => false,
        'created' => false,
        'savedToSharePoint' => false,
        'fileName' => null,
        'error' => null,
    ];

    $serviceNorm = rs_norm((string) ($payload['servicio'] ?? ''));
    $plateRequired = (bool) ($payload['requierePlaca'] ?? false)
        || str_contains($serviceNorm, 'cremacion')
        || str_contains($serviceNorm, 'cremaciondirecta');

    if ($plateRequired) {
        $plateResult['required'] = true;

        try {
            $platePayload = $payload;
            $platePayload['itemId'] = (string) $itemId;
            $plate = rs_generate_urna_plate($platePayload);

            $plateBytes = (string) ($plate['png'] ?? '');
            $plateFileName = trim((string) ($plate['pngName'] ?? ''));
            if ($plateBytes === '' || $plateFileName === '') {
                throw new RuntimeException('La generacion de placa no devolvio un PNG utilizable.');
            }

            $plateResult['created'] = true;
            $plateResult['fileName'] = $plateFileName;
            $emailAttachments[] = [
                'name' => $plateFileName,
                'contentType' => 'image/png',
                'bytes' => $plateBytes,
            ];

            $platesFolder = '/sites/Operaciones/Documentos compartidos/Automaticaciones/Eventos Capillas/Placas';
            $folderArg = rawurlencode("'" . $platesFolder . "'");
            $fileArg = rawurlencode("'" . $plateFileName . "'");
            $plateUploadUrl = $siteUrl
                . '/_api/web/GetFolderByServerRelativePath(decodedurl=@f)'
                . '/Files/AddUsingPath(decodedurl=@n,overwrite=true)'
                . '?@f=' . $folderArg
                . '&@n=' . $fileArg;

            rs_request('POST', $plateUploadUrl, $token, $plateBytes, [
                'Content-Type: image/png',
                'X-RequestDigest: ' . $digest,
            ]);

            $plateResult['savedToSharePoint'] = true;
        } catch (Throwable $plateError) {
            // La placa no debe provocar que el usuario duplique el servicio.
            // El registro ya existe en SharePoint; se informa el error para seguimiento.
            $plateResult['error'] = $plateError->getMessage();
            error_log('Registro Servicios Placa item ' . $itemId . ': ' . $plateError->getMessage());
        }
    }

    // FASE 4: carta / constancia de servicio otorgado.
    // Se genera con la informacion real capturada y se adjunta al elemento de
    // SharePoint como PDF. Todavia NO se envia por correo; ese paso se integrara
    // despues de validar el comportamiento con registros reales.
    $letterResult = [
        'required' => true,
        'created' => false,
        'attachedToItem' => false,
        'fileName' => null,
        'error' => null,
    ];

    try {
        $letterPayload = $payload;
        $letterPayload['itemId'] = (string) $itemId;

        $letter = rs_generate_service_letter($letterPayload);
        $letterBytes = (string) ($letter['pdf'] ?? '');
        $letterFileName = trim((string) ($letter['pdfName'] ?? ''));

        if ($letterBytes === '' || $letterFileName === '') {
            throw new RuntimeException('La generacion de la carta no devolvio un PDF utilizable.');
        }

        $letterResult['created'] = true;
        $letterResult['fileName'] = $letterFileName;
        $emailAttachments[] = [
            'name' => $letterFileName,
            'contentType' => 'application/pdf',
            'bytes' => $letterBytes,
        ];

        $safeLetterName = preg_replace('/[^A-Za-z0-9._() -]+/u', '_', $letterFileName) ?: ('Carta_Servicio_Otorgado_' . $itemId . '.pdf');
        $safeLetterName = str_replace("'", "''", $safeLetterName);

        $letterAttachmentUrl = $siteUrl
            . "/_api/web/lists/getbytitle('" . $listEsc . "')/items(" . $itemId . ")"
            . "/AttachmentFiles/add(FileName='" . rawurlencode($safeLetterName) . "')";

        rs_request('POST', $letterAttachmentUrl, $token, $letterBytes, [
            'Content-Type: application/pdf',
            'X-RequestDigest: ' . $digest,
        ]);

        $letterResult['attachedToItem'] = true;
    } catch (Throwable $letterError) {
        // Igual que la placa: el servicio ya fue creado. Un fallo al generar
        // la carta no debe provocar que el usuario duplique el registro.
        $letterResult['error'] = $letterError->getMessage();
        error_log('Registro Servicios Carta item ' . $itemId . ': ' . $letterError->getMessage());
    }

    // FASE 2: las tres imagenes informativas se generan con los datos reales
    // para adjuntarlas al correo controlado.
    try {
        $infoImages = rs_generate_service_information_images($payload);
        foreach ([
            ['nameKey' => 'serviceName', 'bytesKey' => 'servicePng'],
            ['nameKey' => 'obitName', 'bytesKey' => 'obitPng'],
            ['nameKey' => 'saleName', 'bytesKey' => 'salePng'],
        ] as $imagePart) {
            $imageName = trim((string)($infoImages[$imagePart['nameKey']] ?? ''));
            $imageBytes = (string)($infoImages[$imagePart['bytesKey']] ?? '');
            if ($imageName !== '' && $imageBytes !== '') {
                $emailAttachments[] = [
                    'name' => $imageName,
                    'contentType' => 'image/png',
                    'bytes' => $imageBytes,
                ];
            }
        }
    } catch (Throwable $imageError) {
        error_log('Registro Servicios Imagenes item ' . $itemId . ': ' . $imageError->getMessage());
    }

    /** @return array<int,array{key:string,name:string,tmp:string,size:int,type:string}> */
    function rs_uploaded_files(): array
    {
        $out = [];
        foreach ([
            'esquelaProcesadaFile' => 'esquela',
            'certificadoDefuncion' => 'certificado',
            'ordenInhumacionCremacion' => 'orden'
        ] as $inputKey => $logicalKey) {
            if (!isset($_FILES[$inputKey]) || !is_array($_FILES[$inputKey])) continue;
            $file = $_FILES[$inputKey];
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No fue posible recibir el archivo ' . $inputKey . ' (codigo ' . $error . ').');
            $tmp = (string) ($file['tmp_name'] ?? '');
            $name = trim((string) ($file['name'] ?? $inputKey));
            $size = (int) ($file['size'] ?? 0);
            if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('El archivo ' . $name . ' no es valido.');
            if ($size <= 0 || $size > 15 * 1024 * 1024) throw new RuntimeException('El archivo ' . $name . ' debe ser menor a 15 MB.');
            $out[] = ['key'=>$logicalKey, 'name' => $name, 'tmp' => $tmp, 'size' => $size, 'type' => (string) ($file['type'] ?? 'application/octet-stream')];
        }
        return $out;
    }

    $filesToAttach = rs_uploaded_files();
    $providedKeys = array_fill_keys(array_map(static fn(array $f): string => $f['key'], $filesToAttach), true);

    if ($draftId !== '') {
        $draft = rs_read_draft($storageCtx, $draftId);
        $storedFiles = is_array($draft['files'] ?? null) ? $draft['files'] : [];
        foreach ($storedFiles as $logicalKey => $stored) {
            if (isset($providedKeys[$logicalKey]) || !is_array($stored)) continue;
            $path = (string) ($stored['path'] ?? '');
            if ($path === '' || !is_file($path) || !is_readable($path)) continue;
            $filesToAttach[] = [
                'key'=>(string)$logicalKey,
                'name'=>(string)($stored['name'] ?? basename($path)),
                'tmp'=>$path,
                'size'=>(int)($stored['size'] ?? filesize($path) ?: 0),
                'type'=>(string)($stored['type'] ?? 'application/octet-stream'),
            ];
        }
    }

    $uploadedNames = [];
    foreach ($filesToAttach as $file) {
        $bytes = file_get_contents($file['tmp']);
        if ($bytes === false) throw new RuntimeException('No fue posible leer el archivo ' . $file['name'] . '.');
        $emailAttachments[] = [
            'name' => (string)$file['name'],
            'contentType' => (string)($file['type'] ?? 'application/octet-stream'),
            'bytes' => $bytes,
        ];
        $safeName = preg_replace('/[^A-Za-z0-9._() -]+/u', '_', $file['name']) ?: 'archivo';
        $safeName = str_replace("'", "''", $safeName);
        $attachmentUrl = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items(" . $itemId . ")/AttachmentFiles/add(FileName='" . rawurlencode($safeName) . "')";
        try {
            rs_request('POST', $attachmentUrl, $token, $bytes, [
            'Content-Type: application/octet-stream',
            'X-RequestDigest: ' . $digest,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Etapa ADJUNTAR ARCHIVO ' . $safeName . ': ' . $e->getMessage(), 0, $e);
        }
        $uploadedNames[] = $safeName;
    }

    // PRUEBA CONTROLADA: en Preview NO crear eventos de calendario,
    // aunque la bandera privada del calendario se encuentre habilitada.
    if (rs_is_preview_mode()) {
        $calendarResult = [
            'enabled' => false,
            'created' => false,
            'skipped' => true,
            'reason' => 'Preview controlado: calendario deshabilitado para esta prueba.',
        ];
    } else {
        try {
            $calendarResult = rs_calendar_create_event($payload, $config);
        } catch (Throwable $calendarError) {
            throw new RuntimeException(
                'Etapa CREAR EVENTO CALENDARIO: ' . $calendarError->getMessage(),
                0,
                $calendarError
            );
        }
    }

    // Correo CONTROLADO del Preview. Un fallo de correo no debe provocar
    // duplicidad del servicio ya creado en SharePoint.
    $emailResult = [
        'enabled' => rs_is_preview_mode(),
        'sent' => false,
        'recipients' => [],
        'attachmentNames' => [],
        'error' => null,
    ];
    if (rs_is_preview_mode()) {
        try {
            $emailResult = array_merge(
                $emailResult,
                rs_send_controlled_test_email($payload, $emailAttachments)
            );
        } catch (Throwable $emailError) {
            $emailResult['error'] = $emailError->getMessage();
            error_log('Registro Servicios Correo item ' . $itemId . ': ' . $emailError->getMessage());
        }
    }

    rs_add_publication($storageCtx, [
        'itemId'=>(string)$itemId,
        'status'=>'PUBLICADO',
        'numeroReferencia'=>trim((string)($payload['numeroReferencia'] ?? '')),
        'referencia'=>trim((string)($payload['referencia'] ?? '')),
        'fallecido'=>trim((string)($payload['fallecido'] ?? '')),
        'servicio'=>trim((string)($payload['servicio'] ?? '')),
        'ubicacion'=>trim((string)($payload['ubicacion'] ?? '')),
        'fechaServicio'=>trim((string)($payload['inicio'] ?? '')),
        'publishedAt'=>gmdate('c'),
    ]);
    if ($draftId !== '') {
        rs_remove_tree(rs_draft_dir($storageCtx, $draftId));
    }

    rs_json(201, [
        'ok' => true,
        'itemId' => $itemId,
        'message' => 'Servicio registrado correctamente en SharePoint.',
        'attachments' => $uploadedNames,
        'calendar' => $calendarResult ?? ['enabled' => false, 'created' => false],
        'plate' => $plateResult,
        'letter' => $letterResult,
        'email' => $emailResult ?? ['enabled' => false, 'sent' => false],
    ]);
} catch (Throwable $error) {
    error_log('Registro Servicios SharePoint: ' . $error->getMessage());
    rs_json(500, ['ok' => false, 'message' => $error->getMessage()]);
}
