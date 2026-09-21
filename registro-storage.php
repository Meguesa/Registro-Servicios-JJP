<?php
declare(strict_types=1);

function rs_storage_bootstrap(): array
{
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $bootstrap = $root . '/includes/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('No se encontro el bootstrap del Portal.');
    }
    require_once $bootstrap;
    portal_require_authentication();
    $user = portal_user();
    $email = strtolower(trim((string)($user['email'] ?? '')));
    if ($email === '') throw new RuntimeException('No fue posible identificar el correo del usuario.');
    $home = dirname($root);
    $base = $home . '/registro-servicios-data';
    if (!is_dir($base) && !mkdir($base, 0770, true) && !is_dir($base)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento privado de Registro de Servicios.');
    }
    $userDir = $base . '/u-' . hash('sha256', $email);
    if (!is_dir($userDir) && !mkdir($userDir, 0770, true) && !is_dir($userDir)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento del usuario.');
    }
    return ['root'=>$root,'base'=>$base,'userDir'=>$userDir,'user'=>$user,'email'=>$email];
}

function rs_draft_id(): string
{
    return 'RS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function rs_safe_id(string $id): string
{
    $id = strtoupper(trim($id));
    if ($id === '' || !preg_match('/^RS-[A-Z0-9-]{6,40}$/', $id)) {
        throw new InvalidArgumentException('Identificador de borrador no valido.');
    }
    return $id;
}

function rs_draft_dir(array $ctx, string $id): string
{
    return $ctx['userDir'] . '/drafts/' . rs_safe_id($id);
}

function rs_draft_json_path(array $ctx, string $id): string
{
    return rs_draft_dir($ctx, $id) . '/draft.json';
}

function rs_read_draft(array $ctx, string $id): ?array
{
    $path = rs_draft_json_path($ctx, $id);
    if (!is_file($path)) return null;
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function rs_write_json_atomic(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No fue posible crear el directorio de almacenamiento.');
    }
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) throw new RuntimeException('No fue posible serializar el borrador.');
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('No fue posible escribir el borrador.');
    }
    @chmod($tmp, 0660);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('No fue posible guardar el borrador.');
    }
}

function rs_remove_tree(string $path): void
{
    if (!is_dir($path)) return;
    $items = scandir($path);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . '/' . $item;
        if (is_dir($full)) rs_remove_tree($full);
        else @unlink($full);
    }
    @rmdir($path);
}

function rs_store_uploads(array $ctx, string $id, array $existing = []): array
{
    $dir = rs_draft_dir($ctx, $id) . '/files';
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No fue posible preparar los archivos del borrador.');
    }
    $map = [
        'esquelaProcesadaFile' => 'esquela',
        'certificadoDefuncion' => 'certificado',
        'ordenInhumacionCremacion' => 'orden',
    ];
    $result = $existing;
    foreach ($map as $input => $key) {
        if (!isset($_FILES[$input]) || !is_array($_FILES[$input])) continue;
        $file = $_FILES[$input];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No fue posible recibir ' . $key . '.');
        $tmp = (string)($file['tmp_name'] ?? '');
        $name = trim((string)($file['name'] ?? $key));
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Archivo de borrador no valido.');
        if ($size <= 0 || $size > 15 * 1024 * 1024) throw new RuntimeException('Cada archivo debe ser menor a 15 MB.');
        $safe = preg_replace('/[^A-Za-z0-9._() -]+/u', '_', $name) ?: $key;
        $target = $dir . '/' . $key . '-' . $safe;
        foreach (glob($dir . '/' . $key . '-*') ?: [] as $old) @unlink($old);
        if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('No fue posible guardar ' . $name . '.');
        @chmod($target, 0660);
        $result[$key] = [
            'name'=>$name,
            'path'=>$target,
            'size'=>$size,
            'type'=>(string)($file['type'] ?? 'application/octet-stream'),
        ];
    }
    return $result;
}

function rs_publications_path(array $ctx): string
{
    return $ctx['userDir'] . '/published.json';
}

function rs_read_publications(array $ctx): array
{
    $path = rs_publications_path($ctx);
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? array_values($data) : [];
}

function rs_add_publication(array $ctx, array $row): void
{
    $rows = rs_read_publications($ctx);
    $rows[] = $row;
    if (count($rows) > 500) $rows = array_slice($rows, -500);
    rs_write_json_atomic(rs_publications_path($ctx), $rows);
}
