<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/registro-storage.php';

try {
    $ctx = rs_storage_bootstrap();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $id = rs_safe_id((string)($_GET['id'] ?? ''));
        $draft = rs_read_draft($ctx, $id);
        if ($draft === null) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'message'=>'No se encontro el borrador.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok'=>true,'draft'=>$draft], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['ok'=>false,'message'=>'Metodo no permitido.']);
        exit;
    }

    $raw = (string)($_POST['payload'] ?? '');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>'El contenido del borrador no es valido.']);
        exit;
    }

    $idRaw = trim((string)($_POST['draftId'] ?? ''));
    $id = $idRaw !== '' ? rs_safe_id($idRaw) : rs_draft_id();
    $existing = rs_read_draft($ctx, $id) ?? [];
    $files = rs_store_uploads($ctx, $id, is_array($existing['files'] ?? null) ? $existing['files'] : []);

    $now = gmdate('c');
    $draft = [
        'id'=>$id,
        'status'=>'BORRADOR',
        'createdAt'=>(string)($existing['createdAt'] ?? $now),
        'updatedAt'=>$now,
        'user'=>[
            'name'=>(string)($ctx['user']['name'] ?? 'Usuario'),
            'email'=>$ctx['email'],
        ],
        'payload'=>$payload,
        'files'=>$files,
    ];
    rs_write_json_atomic(rs_draft_json_path($ctx, $id), $draft);

    echo json_encode([
        'ok'=>true,
        'draftId'=>$id,
        'message'=>'Borrador guardado correctamente.',
        'files'=>array_map(static fn($f)=>is_array($f)?($f['name']??''):'', $files),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Registro Servicios borrador: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
