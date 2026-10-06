<?php

declare(strict_types=1);

/**
 * Integracion AISLADA con GitHub Actions para TellMeBye.
 *
 * Configuracion privada esperada en /home/juanpab1/portal-config/config.php:
 *
 * 'registro_servicios_tellmebye_enabled' => true,
 * 'registro_servicios_tellmebye_github_token' => 'github_pat_...',
 * 'registro_servicios_tellmebye_preview_mode' => 'preview' // o 'publicar'
 *
 * El token debe tener permiso Actions: Read and write sobre
 * Meguesa/jjp-tellmebye-bot.
 */

function rs_tellmebye_config(): array
{
    $configPath = '/home/juanpab1/portal-config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion privada del Portal.');
    }

    $raw = require $configPath;
    if (!is_array($raw)) {
        throw new RuntimeException('La configuracion privada del Portal no es valida.');
    }

    $enabled = filter_var(
        $raw['registro_servicios_tellmebye_enabled'] ?? false,
        FILTER_VALIDATE_BOOL
    );

    $token = trim((string)(
        $raw['registro_servicios_tellmebye_github_token']
        ?? $raw['github_actions_token']
        ?? $raw['github_token']
        ?? ''
    ));

    $previewMode = strtolower(trim((string)(
        $raw['registro_servicios_tellmebye_preview_mode']
        ?? 'preview'
    )));
    if (!in_array($previewMode, ['preview', 'publicar'], true)) {
        $previewMode = 'preview';
    }

    return [
        'enabled' => $enabled,
        'token' => $token,
        'owner' => 'Meguesa',
        'repo' => 'jjp-tellmebye-bot',
        'workflow' => 'tellmebye.yml',
        'ref' => 'main',
        'previewMode' => $previewMode,
    ];
}

/**
 * Sube la fotografia fuente de la esquela a la biblioteca temporal que consume
 * jjp-tellmebye-bot. Se usa SharePoint REST con el mismo token/certificado que
 * ya utiliza Registro de Servicios, evitando depender de los adjuntos de lista.
 */
function rs_tellmebye_upload_temp_image(
    int $itemId,
    array $file,
    string $siteUrl,
    string $sharePointToken,
    string $requestDigest
): string {
    if ($itemId <= 0) return '';

    $tmp = trim((string)($file['tmp'] ?? ''));
    if ($tmp === '' || !is_file($tmp) || !is_readable($tmp)) return '';

    $originalName = basename(trim((string)($file['name'] ?? 'Imagen_Esquela.jpg')));
    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';

    $base = (string)pathinfo($originalName, PATHINFO_FILENAME);
    $base = preg_replace('/[^0-9A-Za-z _.-]+/u', '_', $base) ?: 'Imagen_Esquela';
    $base = trim(preg_replace('/\s+/', ' ', $base) ?? $base);
    if ($base === '') $base = 'Imagen_Esquela';

    $fileName = $itemId . '-' . $base . '.' . $ext;
    $bytes = file_get_contents($tmp);
    if (!is_string($bytes) || $bytes === '') return '';

    $sitePath = (string)(parse_url($siteUrl, PHP_URL_PATH) ?: '/sites/Operaciones');
    $folders = [
        rtrim($sitePath, '/') . '/Documentos compartidos/Automaticaciones/Eventos Capillas/Esquelas',
        rtrim($sitePath, '/') . '/Shared Documents/Automaticaciones/Eventos Capillas/Esquelas',
    ];

    $errors = [];
    foreach ($folders as $folder) {
        $folderEsc = str_replace("'", "''", $folder);
        $fileEsc = str_replace("'", "''", $fileName);
        $url = rtrim($siteUrl, '/')
            . "/_api/web/GetFolderByServerRelativeUrl('"
            . rawurlencode($folderEsc)
            . "')/Files/add(url='"
            . rawurlencode($fileEsc)
            . "',overwrite=true)";

        $curl = curl_init($url);
        if ($curl === false) continue;

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $bytes,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $sharePointToken,
                'Accept: application/json;odata=nometadata',
                'Content-Type: application/octet-stream',
                'X-RequestDigest: ' . $requestDigest,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response !== false && $status >= 200 && $status < 300) {
            return $fileName;
        }

        $errors[] = $folder . ': HTTP ' . $status . ($error !== '' ? ' ' . $error : '');
    }

    throw new RuntimeException(
        'No fue posible preparar la imagen temporal para TellMeBye. '
        . implode(' | ', $errors)
    );
}

function rs_trigger_tellmebye(int $itemId, bool $isPreview, string $esquelaFileName = ''): array
{
    if ($itemId <= 0) {
        throw new RuntimeException('TellMeBye requiere un itemId valido.');
    }

    $config = rs_tellmebye_config();

    if (!$config['enabled']) {
        return [
            'enabled' => false,
            'triggered' => false,
            'mode' => null,
            'error' => null,
            'reason' => 'TellMeBye no esta habilitado en la configuracion privada.',
        ];
    }

    if ($config['token'] === '') {
        return [
            'enabled' => true,
            'triggered' => false,
            'mode' => null,
            'error' => 'Falta registro_servicios_tellmebye_github_token en config.php.',
        ];
    }

    $mode = $isPreview ? $config['previewMode'] : 'publicar';

    $url = 'https://api.github.com/repos/'
        . rawurlencode($config['owner'])
        . '/'
        . rawurlencode($config['repo'])
        . '/actions/workflows/'
        . rawurlencode($config['workflow'])
        . '/dispatches';

    $request = [
        'ref' => $config['ref'],
        'inputs' => [
            'itemId' => (string)$itemId,
            'modo' => $mode,
            // Registro de Servicios coloca la fotografia fuente en la
            // biblioteca temporal de Esquelas y envia su nombre al bot.
            'esquelaFileName' => trim($esquelaFileName),
        ],
    ];

    $json = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('No fue posible preparar el disparo de TellMeBye.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No fue posible inicializar la llamada a GitHub Actions.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['token'],
            'Accept: application/vnd.github+json',
            'Content-Type: application/json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: JDJP-Registro-Servicios',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('GitHub Actions no respondio: ' . $error);
    }

    if ($status !== 204) {
        $decoded = json_decode((string)$response, true);
        $detail = is_array($decoded)
            ? trim((string)($decoded['message'] ?? ''))
            : trim((string)$response);

        throw new RuntimeException(
            'GitHub Actions respondio HTTP ' . $status
            . ($detail !== '' ? ': ' . mb_substr($detail, 0, 1200) : '.')
        );
    }

    return [
        'enabled' => true,
        'triggered' => true,
        'mode' => $mode,
        'repository' => $config['owner'] . '/' . $config['repo'],
        'workflow' => $config['workflow'],
        'error' => null,
    ];
}
