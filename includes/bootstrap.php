<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('quetta_agrilink_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_URL . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (isset($_SESSION['last_activity_at']) && (time() - (int) $_SESSION['last_activity_at']) > SESSION_IDLE_MINUTES * 60) {
    $_SESSION = [];
    session_destroy();
    session_start();
    $_SESSION['_flash']['error'] = 'Your session expired. Please sign in again.';
}
$_SESSION['last_activity_at'] = time();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/commerce.php';

set_exception_handler(static function (Throwable $exception): void {
    error_log('Unhandled application error: ' . $exception->getMessage());
    if (is_ajax_request()) {
        json_response(false, 'The server could not complete this request.', [], 500);
    }
    http_response_code(500);
    $errorReference = strtoupper(substr(hash('sha256', $exception->getMessage() . microtime(true)), 0, 10));
    require dirname(__DIR__) . '/500.php';
    exit;
});

