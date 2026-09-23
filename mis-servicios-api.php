<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/registro-storage.php';
require_once __DIR__ . '/registro-sharepoint.php';


function rs_sync_publications_with_sharepoint(array $ctx, array $rows): array
{
    if ($rows === []) return ['rows'=>[], 'removed'=>0];

    $config = rs_sharepoint_config();
    $host = 'meguesajdjp.sharepoint.com';
    $siteUrl = 'https://' . $host . '/sites/Operaciones';
    $token = rs_sharepoint_token($config, $host);

    $url = $siteUrl . "/_api/web/lists/getbytitle('Eventos%20Capillas')/items?$select=Id&$top=5000";
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('No fue posible iniciar la sincronizacion con SharePoint.');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json;odata=nometadata',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) throw new RuntimeException('SharePoint no respondio: ' . $error);
    $decoded = json_decode((string)$response, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        throw new RuntimeException('SharePoint respondio HTTP ' . $status . ' al sincronizar publicaciones.');
    }

    $existing = [];
    foreach (($decoded['value'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $id = (string)($item['Id'] ?? $item['ID'] ?? '');
        if ($id !== '') $existing[$id] = true;
    }

    $filtered = [];
    $removed = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $itemId = trim((string)($row['itemId'] ?? ''));
        if ($itemId === '' || isset($existing[$itemId])) $filtered[] = $row;
        else $removed++;
    }

    if ($removed > 0) rs_write_publications($ctx, $filtered);
    return ['rows'=>$filtered, 'removed'=>$removed];
}

try {
    $ctx = rs_storage_bootstrap();
    $draftRoot = $ctx['userDir'] . '/drafts';
    $drafts = [];
    if (is_dir($draftRoot)) {
        foreach (scandir($draftRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $data = rs_read_draft($ctx, $entry);
            if (!is_array($data)) continue;
            $p = is_array($data['payload'] ?? null) ? $data['payload'] : [];
            $drafts[] = [
                'id'=>(string)($data['id'] ?? $entry),
                'status'=>'BORRADOR',
                'numeroReferencia'=>(string)($p['numeroReferencia'] ?? ''),
                'fallecido'=>(string)($p['fallecido'] ?? ''),
                'servicio'=>(string)($p['servicio'] ?? ''),
                'ubicacion'=>(string)($p['ubicacion'] ?? ''),
                'fechaServicio'=>(string)($p['inicio'] ?? ''),
                'updatedAt'=>(string)($data['updatedAt'] ?? ''),
            ];
        }
    }
    usort($drafts, static fn($a,$b)=>strcmp((string)$b['updatedAt'], (string)$a['updatedAt']));

    $published = rs_read_publications($ctx);
    $sync = ['ok'=>false, 'removed'=>0];
    try {
        $syncResult = rs_sync_publications_with_sharepoint($ctx, $published);
        $published = $syncResult['rows'];
        $sync = ['ok'=>true, 'removed'=>(int)$syncResult['removed']];
    } catch (Throwable $syncError) {
        error_log('Registro Servicios sync publicaciones: ' . $syncError->getMessage());
        $sync['message'] = $syncError->getMessage();
    }

    usort($published, static fn($a,$b)=>strcmp((string)($b['publishedAt']??''), (string)($a['publishedAt']??'')));

    echo json_encode([
        'ok'=>true,
        'drafts'=>$drafts,
        'published'=>$published,
        'counts'=>['drafts'=>count($drafts),'published'=>count($published)],
        'sync'=>$sync,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Registro Servicios lista: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'message'=>'No fue posible cargar los servicios.',
        'debug'=>[
            'type'=>get_class($e),
            'message'=>$e->getMessage(),
            'file'=>$e->getFile(),
            'line'=>$e->getLine(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
