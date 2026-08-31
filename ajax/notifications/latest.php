<?php
/** Latest account-owned notifications for the authenticated navbar dropdown. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('GET');
$user = require_login();
$items = array_map(static function (array $notification): array {
    return [
        'id' => (int) $notification['id'],
        'type' => (string) $notification['type'],
        'title' => (string) $notification['title'],
        'body' => (string) $notification['body'],
        'action_url' => $notification['action_url'] ? app_url((string) $notification['action_url']) : null,
        'is_unread' => $notification['read_at'] === null,
        'created_at' => (string) $notification['created_at'],
        'created_label' => date('j M, H:i', strtotime((string) $notification['created_at'])),
    ];
}, latest_notifications_for_user((int) $user['id'], 5));

json_response(true, 'Latest notifications loaded.', [
    'items' => $items,
    'summary' => unread_notification_summary((int) $user['id']),
]);
