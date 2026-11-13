<?php
declare(strict_types=1);

// Regression: bootstrap.php is loaded from rs_storage_bootstrap() (function scope).
// portal_config() must see the shared global config, as it does on portal pages.
$root = sys_get_temp_dir() . '/jjp-session-scope-' . bin2hex(random_bytes(6));
$includes = $root . '/includes';
if (!mkdir($includes, 0700, true)) {
    throw new RuntimeException('Could not create test directory');
}
$bootstrap = <<<'PHP'
<?php
$config = ['session_lifetime_seconds' => 3600];
$provider = new stdClass();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['portal_user'] = ['email' => 'test@example.com'];
$_SESSION['authenticated_at'] = time();
function portal_config(string $key): mixed { global $config; return $config[$key] ?? null; }
function portal_is_authenticated(): bool {
    if (empty($_SESSION['portal_user'])) return false;
    $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
    $lifetime = (int) portal_config('session_lifetime_seconds');
    return $authenticatedAt > 0 && (time() - $authenticatedAt) < $lifetime;
}
function portal_user(): array { return $_SESSION['portal_user'] ?? []; }
PHP;
file_put_contents($includes . '/bootstrap.php', $bootstrap);
$_SERVER['DOCUMENT_ROOT'] = $root;
require_once dirname(__DIR__) . '/includes/registro-storage.php';
try {
    $ctx = rs_storage_bootstrap();
    if (($ctx['email'] ?? '') !== 'test@example.com') {
        throw new RuntimeException('Authenticated session was not recovered');
    }
    if (!portal_is_authenticated()) {
        throw new RuntimeException('Configuration scope was not preserved');
    }
    echo "OK: API session config available in global scope\n";
} finally {
    @unlink($includes . '/bootstrap.php');
    @rmdir($includes);
    @rmdir($root);
}
