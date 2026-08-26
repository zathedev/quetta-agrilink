<?php
/** Account-scoped optional browser-chime preference; alerts remain local in-app records. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('POST');
$user = require_login();
verify_csrf();
try {
    $current = notification_preferences_for_user((int) $user['id']);
    $preferences = save_notification_preferences((int) $user['id'], [
        'marketplace_match_alerts_enabled' => $current['marketplace_match_alerts_enabled'],
        'browser_chime_enabled' => ($_POST['browser_chime_enabled'] ?? '') === '1',
    ]);
    json_response(true, 'Notification sound preference updated.', ['browser_chime_enabled' => $preferences['browser_chime_enabled'] === 1]);
} catch (Throwable $exception) {
    json_response(false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The notification sound preference could not be saved.', [], 422);
}
