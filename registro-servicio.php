<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rs_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $bootstrap = $root . '/includes/bootstrap.php';
    $sharepoint = $root . '/includes/portal-sharepoint.php';
    if (!is_file($bootstrap) || !is_file($sharepoint)) {
        throw new RuntimeException('No se encontraron los componentes compartidos del Portal.');
    }
    require_once $bootstrap;
    require_once $sharepoint;
    portal_require_authentication();

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

    $config = portal_sharepoint_config();
    $host = 'meguesajdjp.sharepoint.com';
    $siteUrl = 'https://' . $host . '/sites/Operaciones';
    $listTitle = 'Eventos Capillas';
    $token = portal_sharepoint_token($config, $host);

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
            $detail = is_array($decoded)
                ? (string) ($decoded['error']['message']['value'] ?? $decoded['error']['message'] ?? '')
                : '';
            throw new RuntimeException('SharePoint respondio HTTP ' . $status . ($detail !== '' ? ': ' . $detail : '.'));
        }
        return ['status' => $status, 'body' => (string) $response, 'json' => is_array($decoded) ? $decoded : []];
    }

    function rs_norm(string $value): string
    {
        $value = trim($value);
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
            $values = is_array($value) ? array_values(array_filter(array_map('strval', $value), static fn($v) => trim($v) !== '')) : [(string) $value];
            $fieldsPayload[$internal] = ['__metadata' => ['type' => 'Collection(Edm.String)'], 'results' => $values];
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
    $fieldRows = rs_request('GET', $fieldsUrl, $token)['json']['value'] ?? [];
    $fieldIndex = rs_fields_by_norm(is_array($fieldRows) ? $fieldRows : []);

    $sp = [];
    rs_add_value($sp, $fieldIndex, ['ModoPrueba', 'Modo Prueba'], true, false);
    rs_add_value($sp, $fieldIndex, ['Numero de Referencia', 'Número de Referencia'], trim((string) ($payload['numeroReferencia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Servicio', 'Tipo de Servicio'], trim((string) ($payload['servicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Ubicación Servicio Capillas', 'Ubicacion Servicio Capillas'], trim((string) ($payload['ubicacion'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Sala'], trim((string) ($payload['sala'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha y Hora Inicio'], rs_local_datetime_to_utc((string) ($payload['inicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha y Hora Termino', 'Fecha y Hora Término'], rs_local_datetime_to_utc((string) ($payload['termino'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Misa', 'Lleva exequia', 'Lleva exequia?'], (bool) ($payload['llevaExequia'] ?? false), false);
    rs_add_value($sp, $fieldIndex, ['Tiempo de Capillas'], trim((string) ($payload['tiempoCapillas'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Hora Misa', 'Fecha y Hora Exequia'], rs_local_datetime_to_utc((string) ($payload['horaExequia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Prevision/Uso Inmediato', 'Previsión/Uso Inmediato'], trim((string) ($payload['prevision'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Tipo de Ataud/Urna', 'Tipo de Ataúd/Urna'], trim((string) ($payload['tipoAtaud'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Numero de Servicio', 'Número de Servicio'], trim((string) ($payload['numeroServicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Codigo', 'Código', 'Codigo de Servicio', 'Código de Servicio'], trim((string) ($payload['codigoServicio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Ataud/Urna', 'Ataúd/Urna'], trim((string) ($payload['codigoAtaud'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Referencia'], trim((string) ($payload['referencia'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Requiere Placa', 'Requiere Placa de Urna', 'Requiere Placa de Urna?'], (bool) ($payload['requierePlaca'] ?? false), false);

    rs_add_value($sp, $fieldIndex, ['Titular Responsable'], trim((string) ($payload['titular'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Nombre de Fallecido (a)', 'Nombre Fallecido', 'Nombre de Fallecido(a)'], trim((string) ($payload['fallecido'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha Nacimiento Fallecido (a)', 'Fecha Nacimiento', 'Fecha de Nacimiento'], rs_date_only((string) ($payload['fechaNacimiento'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha y Hora Defuncion Fallecido (a)', 'Fecha y Hora Defunción Fallecido (a)', 'Fecha y Hora Defuncion'], rs_local_datetime_to_utc((string) ($payload['fechaDefuncion'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Sexo'], trim((string) ($payload['sexo'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Edad'], ($payload['edad'] ?? '') === '' ? null : (float) $payload['edad']);
    rs_add_value($sp, $fieldIndex, ['Ubicacion Post Capillas', 'Ubicación Post Capillas', 'Ubicacion Destino Final', 'Ubicación Destino Final'], trim((string) ($payload['destinoFinal'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Embalsamador'], trim((string) ($payload['embalsamador'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Personal Rescate 1'], trim((string) ($payload['rescate1'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Personal Rescate 2'], trim((string) ($payload['rescate2'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Ubicacion de Rescate', 'Ubicación de Rescate'], trim((string) ($payload['ubicacionRescate'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Motivo de Fallecimiento'], trim((string) ($payload['motivo'] ?? '')));

    rs_add_value($sp, $fieldIndex, ['Referencia Crematorio'], trim((string) ($payload['referenciaCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha y Hora Inicio Crematorio'], rs_local_datetime_to_utc((string) ($payload['inicioCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Personal de Crematorio', 'Personal Crematorio'], trim((string) ($payload['personalCrematorio'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Fecha y Hora Inhumacion', 'Fecha y Hora Inhumación'], rs_local_datetime_to_utc((string) ($payload['fechaHoraInhumacion'] ?? '')));

    rs_add_value($sp, $fieldIndex, ['Fecha Compra'], rs_date_only((string) ($payload['fechaCompra'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Personal Venta'], trim((string) ($payload['personalVenta'] ?? '')));
    rs_add_value($sp, $fieldIndex, ['Precio de Venta', 'Precio Venta'], ($payload['precioVenta'] ?? '') === '' ? null : (float) $payload['precioVenta']);
    rs_add_value($sp, $fieldIndex, ['Servicios Extra', 'Servicios Adicionales'], is_array($payload['serviciosExtra'] ?? null) ? $payload['serviciosExtra'] : []);
    rs_add_value($sp, $fieldIndex, ['Venta Total Servicio'], ($payload['ventaTotal'] ?? '') === '' ? null : (float) $payload['ventaTotal']);

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

    $digestData = rs_request('POST', $siteUrl . '/_api/contextinfo', $token, '', [
        'Accept: application/json;odata=nometadata',
        'Content-Type: application/json;odata=nometadata',
    ])['json'];
    $digest = trim((string) ($digestData['FormDigestValue'] ?? ''));
    if ($digest === '') throw new RuntimeException('SharePoint no devolvio un FormDigest valido.');

    $createUrl = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items";
    $created = rs_request('POST', $createUrl, $token, (string) json_encode($sp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
        'Content-Type: application/json;odata=nometadata',
        'X-RequestDigest: ' . $digest,
    ])['json'];

    $itemId = (int) ($created['Id'] ?? $created['ID'] ?? 0);
    if ($itemId <= 0) throw new RuntimeException('SharePoint creo el registro, pero no devolvio un ID utilizable.');

    /** @return array<int,array{name:string,tmp:string,size:int,type:string}> */
    function rs_uploaded_files(): array
    {
        $out = [];
        foreach (['esquelaProcesadaFile', 'certificadoDefuncion', 'ordenInhumacionCremacion'] as $key) {
            if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) continue;
            $file = $_FILES[$key];
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No fue posible recibir el archivo ' . $key . ' (codigo ' . $error . ').');
            $tmp = (string) ($file['tmp_name'] ?? '');
            $name = trim((string) ($file['name'] ?? $key));
            $size = (int) ($file['size'] ?? 0);
            if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('El archivo ' . $name . ' no es valido.');
            if ($size <= 0 || $size > 15 * 1024 * 1024) throw new RuntimeException('El archivo ' . $name . ' debe ser menor a 15 MB.');
            $out[] = ['name' => $name, 'tmp' => $tmp, 'size' => $size, 'type' => (string) ($file['type'] ?? 'application/octet-stream')];
        }
        return $out;
    }

    $uploadedNames = [];
    foreach (rs_uploaded_files() as $file) {
        $bytes = file_get_contents($file['tmp']);
        if ($bytes === false) throw new RuntimeException('No fue posible leer el archivo ' . $file['name'] . '.');
        $safeName = preg_replace('/[^A-Za-z0-9._() -]+/u', '_', $file['name']) ?: 'archivo';
        $safeName = str_replace("'", "''", $safeName);
        $attachmentUrl = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items(" . $itemId . ")/AttachmentFiles/add(FileName='" . rawurlencode($safeName) . "')";
        rs_request('POST', $attachmentUrl, $token, $bytes, [
            'Content-Type: application/octet-stream',
            'X-RequestDigest: ' . $digest,
        ]);
        $uploadedNames[] = $safeName;
    }

    rs_json(201, [
        'ok' => true,
        'itemId' => $itemId,
        'message' => 'Servicio registrado correctamente en SharePoint.',
        'attachments' => $uploadedNames,
    ]);
} catch (Throwable $error) {
    error_log('Registro Servicios SharePoint: ' . $error->getMessage());
    rs_json(500, ['ok' => false, 'message' => $error->getMessage()]);
}
