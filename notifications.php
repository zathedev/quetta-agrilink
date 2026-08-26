<?php
/** Orchard Ledger notifications: owner-scoped alert ledger with explicit, auditable read controls. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = normalize_text($_POST['notification_action'] ?? '', 30);
        if ($action === 'mark_read') {
            $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$notificationId) { throw new RuntimeException('Choose a valid notification.'); }
            flash('success', mark_notification_read((int) $user['id'], (int) $notificationId) ? 'Notification marked as read.' : 'That notification was already marked as read.');
        } elseif ($action === 'mark_all_read') {
            $count = mark_all_notifications_read((int) $user['id']);
            flash('success', $count > 0 ? $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.' : 'There are no unread notifications.');
        } else {
            throw new RuntimeException('That notification action is not available.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The notification action could not be completed.');
    }
    redirect('notifications.php');
}

$notifications = fetch_all('SELECT * FROM notifications WHERE user_id = :user ORDER BY created_at DESC LIMIT 50', ['user' => $user['id']]);
$unreadCount = count(array_filter($notifications, static fn (array $notification): bool => $notification['read_at'] === null));
workspace_open('Notifications', 'notifications');
?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Operational alerts</h2><p>Alerts record an offer, booking, transport request, or matching new listing that may change your next action.</p></div><div class="notification-register-actions"><span class="status-pill"><?= $unreadCount ?> unread</span><?php if ($unreadCount > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="notification_action" value="mark_all_read"><button class="button button-outline button-compact" type="submit">Mark all read</button></form><?php endif; ?></div></div>
    <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
    <div class="notification-list"><?php if ($notifications === []): ?><div class="listing-empty"><h2>No notifications yet.</h2><p>New platform updates will appear here.</p></div><?php else: foreach ($notifications as $notification): ?><article class="notification-item <?= $notification['read_at'] === null ? 'is-unread' : '' ?>"><span class="notification-dot" aria-hidden="true"></span><div><h2><?= e($notification['title']) ?></h2><p><?= e($notification['body']) ?></p><div class="notification-item-actions"><?php if ($notification['action_url']): ?><a class="button button-quiet" href="<?= e(app_url($notification['action_url'])) ?>">Open related work</a><?php endif; ?><?php if ($notification['read_at'] === null): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="notification_action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="text-action notification-read-action" type="submit">Mark read</button></form><?php endif; ?></div></div><time datetime="<?= e($notification['created_at']) ?>"><?= e(date('j M, H:i', strtotime($notification['created_at']))) ?></time></article><?php endforeach; endif; ?></div>
</section>
<?php workspace_close(); ?>
