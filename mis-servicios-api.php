<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/registro-storage.php';

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
    usort($published, static fn($a,$b)=>strcmp((string)($b['publishedAt']??''), (string)($a['publishedAt']??'')));

    echo json_encode([
        'ok'=>true,
        'drafts'=>$drafts,
        'published'=>$published,
        'counts'=>['drafts'=>count($drafts),'published'=>count($published)],
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
