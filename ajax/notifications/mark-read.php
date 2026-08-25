<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_method('POST'); verify_csrf(); $user = require_login();
$notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$notificationId) json_response(false, 'A valid notification is required.', [], 422);
execute_query('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :user', ['id'=>$notificationId,'user'=>$user['id']]);
json_response(true, 'Notification marked as read.');
