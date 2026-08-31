<?php
/** Account-scoped bulk read action for the authenticated navbar dropdown. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('POST');
$user = require_login();
verify_csrf();
$count = mark_all_notifications_read((int) $user['id']);
json_response(true, $count > 0 ? $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.' : 'There are no unread notifications.', [
    'count' => $count,
    'summary' => unread_notification_summary((int) $user['id']),
]);
