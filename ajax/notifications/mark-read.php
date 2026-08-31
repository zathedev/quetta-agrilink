<?php
/** Account-scoped single-notification read action used by the header dropdown and full register. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('POST');
$user = require_login();
verify_csrf();
$notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$notificationId) {
    json_response(false, 'A valid notification is required.', [], 422);
}
try {
    $changed = mark_notification_read((int) $user['id'], (int) $notificationId);
    json_response(true, $changed ? 'Notification marked as read.' : 'Notification was already read.', [
        'notification_id' => (int) $notificationId,
        'summary' => unread_notification_summary((int) $user['id']),
    ]);
} catch (Throwable $exception) {
    json_response(false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The notification could not be updated.', [], 422);
}
