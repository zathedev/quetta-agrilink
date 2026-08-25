<?php
/** Orchard Ledger alert summary: authenticated polling only, with no message bodies exposed in the header request. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('GET');
$user = require_login();
$summary = unread_notification_summary((int) $user['id']);
json_response(true, 'Notification summary updated.', $summary);
