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

