<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('saeta_portal');
    // Derive cookie path from app_url so subdirectory deployments work
    // (production /portal AND local http://host/beta.saetavinotinto.com/portal).
    $appUrlPath = parse_url((string) $config['app_url'], PHP_URL_PATH);
    $sessionCookiePath = is_string($appUrlPath) && $appUrlPath !== '' ? $appUrlPath : '/portal';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $sessionCookiePath,
        'secure' => strpos((string) $config['app_url'], 'https://') === 0,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!empty($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 7200) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = connect_database($config);
    ensure_schema($pdo);
} catch (Throwable $exception) {
    http_response_code(503);
    error_log($exception->getMessage());
    exit('El portal está temporalmente en mantenimiento. Intenta nuevamente más tarde.');
}
