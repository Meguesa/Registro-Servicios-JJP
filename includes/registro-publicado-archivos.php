<?php
declare(strict_types=1);

/**
 * Lectura de archivos de Registro de Servicios.
 *
 * Todas las descargas se hacen desde SharePoint usando el token de servidor
 * ya configurado; ningun navegador recibe tokens ni rutas privadas.
 * Este archivo no crea, modifica o elimina elementos en SharePoint.
 */

function pd_files_valid_name(string $name): bool
{
    return $name !== ''
        && mb_strlen($name, 'UTF-8') <= 240
        && !preg_match('~[\/\\\\\x00-\x1F\x7F]~', $name)
        && $name !== '.'
        && $name !== '..';
}

/** @return array<int,array{name:string,size:int}> */
function pd_files_list(string $siteUrl, string $listEsc, int $itemId, string $token): array
{
    $url = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items("
        . $itemId . ")/AttachmentFiles?%24select=FileName%2CLength";
    $decoded = pd_request($url, $token);
    $rows = $decoded['value'] ?? ($decoded['d']['results'] ?? []);
    if (!is_array($rows)) {
        throw new RuntimeException('SharePoint no devolvio la lista de adjuntos.');
    }
    $files = [];
    foreach ($rows as $file) {
        if (!is_array($file)) continue;
        $name = trim((string)($file['FileName'] ?? ''));
        if (!pd_files_valid_name($name)) continue;
        $files[$name] = [
            'name' => $name,
            'size' => max(0, (int)($file['Length'] ?? 0)),
        ];
    }
    return array_values($files);
}

function pd_files_mime(string $name): string
{
    return match (mb_strtolower((string)pathinfo($name, PATHINFO_EXTENSION), 'UTF-8')) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        default => 'application/octet-stream',
    };
}

function pd_files_previewable(string $name): bool
{
    return in_array(pd_files_mime($name), [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
    ], true);
}

function pd_files_is_image(string $name): bool
{
    return str_starts_with(pd_files_mime($name), 'image/');
}

function pd_files_curl_get(string $url, string $token, int $maxBytes = 26214400): string
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('No se pudo iniciar la descarga del documento.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_MAXFILESIZE => $maxBytes,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/octet-stream',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if (!is_string($response) || $http < 200 || $http >= 300) {
        throw new RuntimeException(
            'No se pudo descargar el archivo de SharePoint (HTTP ' . $http
            . ($error !== '' ? ', ' . $error : '') . ').'
        );
    }
    if (strlen($response) > $maxBytes) {
        throw new RuntimeException('El documento supera el limite de descarga permitido.');
    }
    return $response;
}

function pd_files_attachment_bytes(
    string $siteUrl, string $listEsc, int $itemId, string $name, string $token
): string {
    if (!pd_files_valid_name($name)) throw new InvalidArgumentException('Nombre de adjunto invalido.');
    // El nombre se valida previamente contra los adjuntos del propio elemento.
    $escaped = rawurlencode(str_replace("'", "''", $name));
    $url = $siteUrl . "/_api/web/lists/getbytitle('" . $listEsc . "')/items("
        . $itemId . ")/AttachmentFiles/getByFileName('" . $escaped . "')/\$value";
    return pd_files_curl_get($url, $token);
}

/**
 * Los JPG/PDF oficiales de TellMeBye se guardan en la biblioteca Cartulinas,
 * no como adjuntos de la lista. Probamos el titulo local y el interno.
 */
function pd_files_cartulina_bytes(string $siteUrl, string $fileName, string $token): string
{
    if (!pd_files_valid_name($fileName)) {
        throw new InvalidArgumentException('Nombre de esquela almacenada invalido.');
    }
    $errors = [];
    foreach (['Documentos compartidos', 'Shared Documents'] as $library) {
        $relative = '/sites/Operaciones/' . $library
            . '/Automaticaciones/Eventos Capillas/Cartulinas/' . $fileName;
        $url = $siteUrl
            . "/_api/web/GetFileByServerRelativePath(decodedurl='"
            . rawurlencode(str_replace("'", "''", $relative)) . "')/\$value";
        try {
            return pd_files_curl_get($url, $token);
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
        }
    }
    throw new RuntimeException('No se pudo recuperar la cartulina desde SharePoint: ' . implode(' / ', $errors));
}

/** Busca la fotografia adjunta usada para construir la esquela local. */
function pd_files_photo_name(array $files): ?string
{
    foreach ($files as $file) {
        $name = trim((string)($file['name'] ?? ''));
        if (!pd_files_valid_name($name) || !pd_files_is_image($name)) continue;
        if (preg_match('/^(imagen|foto)[-_ ]?esquela(?:[^\/\\\\]*)\.(jpe?g|png|webp)$/iu', $name)) {
            return $name;
        }
    }
    // Nunca usar otra imagen del expediente como retrato del fallecido.
    return null;
}

/** El contenido generado del correo no siempre se persiste: recrearlo sin escrituras. */
function pd_files_local_esquela(
    array $payload, array $files, string $siteUrl, string $listEsc, int $itemId, string $token
): array {
    $photoName = pd_files_photo_name($files);
    // El generador original de la esquela JJP permite una fotografia de respaldo.
    // No ocultar las esquelas de registros antiguos sin retrato guardado.
    // Si existe una fotografia fuente, usar exactamente el adjunto de SharePoint.
    $photoBytes = $photoName !== null
        ? pd_files_attachment_bytes($siteUrl, $listEsc, $itemId, $photoName, $token)
        : null;
    require_once dirname(__DIR__) . '/includes/registro-esquela.php';
    $payload['itemId'] = (string)$itemId;
    return rs_generate_local_esquela($payload, $photoBytes, null);
}

function pd_files_stream(string $fileName, string $bytes, bool $forceDownload = false): never
{
    $mime = pd_files_mime($fileName);
    $download = $forceDownload || !pd_files_previewable($fileName);
    $fallback = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $fileName) ?: 'documento';
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header(
        'Content-Disposition: ' . ($download ? 'attachment' : 'inline')
        . '; filename="' . $fallback . '"; filename*=UTF-8' . "''" . rawurlencode($fileName)
    );
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}
