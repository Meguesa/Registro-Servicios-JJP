<?php

declare(strict_types=1);

/**
 * Cliente SharePoint AISLADO para Registro de Servicios.
 *
 * IMPORTANTE:
 * - No modifica includes/portal-sharepoint.php.
 * - No cambia la autenticacion ni los permisos usados por Solicitud de Venta,
 *   Dashboard, Reportes u otras herramientas.
 * - Permite definir credenciales dedicadas registro_servicios_* en config.php.
 * - Mientras no existan, conserva compatibilidad usando el backend certificado
 *   de Solicitud de Venta, sin modificarlo.
 *
 * @return array{tenantId:string,clientId:string,pfxPath:string,pfxPassword:string,source:string}
 */
function rs_sharepoint_config(): array
{
    $configPath = '/home/juanpab1/portal-config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    }

    $raw = require $configPath;
    if (!is_array($raw)) {
        throw new RuntimeException('La configuracion privada del Portal no es valida.');
    }

    $dedicatedClientId = trim((string)($raw['registro_servicios_client_id'] ?? ''));
    $dedicatedPfxPath = trim((string)($raw['registro_servicios_sharepoint_pfx_path'] ?? ''));
    // SharePoint REST sigue usando el backend certificado existente mientras
    // Registro de Servicios no tenga SU PROPIO certificado de SharePoint.
    // Tener solo client_id/client_secret para Graph NO cambia SharePoint.
    $usingDedicated = $dedicatedClientId !== '' && $dedicatedPfxPath !== '';

    $config = [
        'tenantId' => trim((string)(
            $raw['registro_servicios_tenant_id']
            ?? $raw['solicitud_backend_tenant_id']
            ?? $raw['tenant_id']
            ?? ''
        )),
        'clientId' => trim((string)(
            ($usingDedicated ? $raw['registro_servicios_client_id'] : null)
            ?? $raw['solicitud_backend_client_id']
            ?? ''
        )),
        'pfxPath' => trim((string)(
            ($usingDedicated ? $raw['registro_servicios_sharepoint_pfx_path'] : null)
            ?? $raw['solicitud_sharepoint_pfx_path']
            ?? ''
        )),
        'pfxPassword' => (string)(
            ($usingDedicated ? ($raw['registro_servicios_sharepoint_pfx_password'] ?? '') : null)
            ?? $raw['solicitud_sharepoint_pfx_password']
            ?? ''
        ),
        'source' => $usingDedicated ? 'registro_servicios' : 'solicitud_backend_fallback',
    ];

    foreach (['tenantId', 'clientId', 'pfxPath'] as $key) {
        if ($config[$key] === '') {
            throw new RuntimeException(
                'Falta configurar ' . $key . ' para Registro de Servicios. ' .
                'Puede definirse con claves registro_servicios_* en config.php.'
            );
        }
    }

    if (!is_file($config['pfxPath']) || !is_readable($config['pfxPath'])) {
        throw new RuntimeException('El certificado PFX configurado para Registro de Servicios no esta disponible.');
    }

    return $config;
}

function rs_sharepoint_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * Genera token app-only para SharePoint Online mediante certificado.
 * SharePoint REST requiere autenticacion por certificado para este escenario.
 */
function rs_sharepoint_token(array $config, string $host): string
{
    $bytes = file_get_contents($config['pfxPath']);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('No fue posible leer el PFX de Registro de Servicios.');
    }

    $certs = [];
    if (!openssl_pkcs12_read($bytes, $certs, $config['pfxPassword'])) {
        throw new RuntimeException('No fue posible abrir el PFX de Registro de Servicios.');
    }

    $privateKey = $certs['pkey'] ?? null;
    $certificate = (string)($certs['cert'] ?? '');
    if ($privateKey === null || $certificate === '') {
        throw new RuntimeException('El PFX de Registro de Servicios no contiene credenciales utilizables.');
    }

    $der = preg_replace(
        '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
        '',
        $certificate
    );
    $derBytes = is_string($der) ? base64_decode($der, true) : false;
    if ($derBytes === false) {
        throw new RuntimeException('No fue posible convertir el certificado de Registro de Servicios.');
    }

    $thumbprint = rs_sharepoint_base64url(hash('sha1', $derBytes, true));
    $tokenUrl = 'https://login.microsoftonline.com/'
        . rawurlencode($config['tenantId'])
        . '/oauth2/v2.0/token';

    $now = time();
    $header = rs_sharepoint_base64url((string)json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT',
        'x5t' => $thumbprint,
    ], JSON_UNESCAPED_SLASHES));

    $claims = rs_sharepoint_base64url((string)json_encode([
        'aud' => $tokenUrl,
        'iss' => $config['clientId'],
        'sub' => $config['clientId'],
        'jti' => bin2hex(random_bytes(16)),
        'nbf' => $now - 30,
        'iat' => $now,
        'exp' => $now + 300,
    ], JSON_UNESCAPED_SLASHES));

    $unsigned = $header . '.' . $claims;
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No fue posible firmar el assertion de Registro de Servicios.');
    }

    $assertion = $unsigned . '.' . rs_sharepoint_base64url($signature);
    $body = http_build_query([
        'client_id' => $config['clientId'],
        'scope' => 'https://' . strtolower($host) . '/.default',
        'grant_type' => 'client_credentials',
        'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        'client_assertion' => $assertion,
    ], '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init($tokenUrl);
    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la autenticacion de Registro de Servicios.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('La autenticacion con Microsoft Entra fallo: ' . $error);
    }

    $data = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $detail = is_array($data)
            ? (string)($data['error_description'] ?? $data['error'] ?? '')
            : '';
        throw new RuntimeException(
            'Microsoft Entra respondio HTTP ' . $status .
            ($detail !== '' ? ': ' . $detail : '.')
        );
    }

    $token = trim((string)($data['access_token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Microsoft Entra no devolvio token de SharePoint para Registro de Servicios.');
    }

    return $token;
}


/**
 * Genera token app-only para Microsoft Graph usando el mismo certificado
 * configurado EXCLUSIVAMENTE para Registro de Servicios.
 *
 * IMPORTANTE:
 * - Para calendario NO se permite usar implicitamente el backend de Solicitud
 *   de Venta. La app debe estar configurada con registro_servicios_client_id.
 * - La app dedicada debe tener Calendars.ReadWrite (Application) con admin consent.
 */
function rs_graph_token(array $config = []): string
{
    // Graph usa exclusivamente la app dedicada "Registro Servicios JJP"
    // mediante client secret. No reutiliza credenciales de Solicitud de Venta.
    $configPath = '/home/juanpab1/portal-config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    }

    $raw = require $configPath;
    if (!is_array($raw)) {
        throw new RuntimeException('La configuracion privada del Portal no es valida.');
    }

    $tenantId = trim((string)($raw['registro_servicios_tenant_id'] ?? ''));
    $clientId = trim((string)($raw['registro_servicios_client_id'] ?? ''));
    $clientSecret = trim((string)($raw['registro_servicios_client_secret'] ?? ''));

    if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
        throw new RuntimeException(
            'Faltan registro_servicios_tenant_id, registro_servicios_client_id o registro_servicios_client_secret.'
        );
    }

    $tokenUrl = 'https://login.microsoftonline.com/'
        . rawurlencode($tenantId)
        . '/oauth2/v2.0/token';

    $body = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials',
    ], '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init($tokenUrl);
    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la autenticacion con Microsoft Graph.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('La autenticacion con Microsoft Graph fallo: ' . $error);
    }

    $data = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300) {
        $detail = is_array($data)
            ? (string)($data['error_description'] ?? $data['error'] ?? '')
            : '';
        throw new RuntimeException(
            'Microsoft Graph/Entra respondio HTTP ' . $status .
            ($detail !== '' ? ': ' . $detail : '.')
        );
    }

    $token = trim((string)($data['access_token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Microsoft Entra no devolvio token de Microsoft Graph.');
    }

    return $token;
}
