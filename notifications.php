<?php
/** Orchard Ledger notifications: owner-scoped local alert ledger, delivery preferences, filtering, and explicit read controls. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_login();
$availableTypes = fetch_all('SELECT DISTINCT type FROM notifications WHERE user_id = :user_id ORDER BY type', ['user_id' => $user['id']]);
$typeValues = array_column($availableTypes, 'type');
$selectedType = normalize_text($_GET['type'] ?? '', 80);
if (!in_array($selectedType, $typeValues, true)) { $selectedType = ''; }
$selectedState = in_array($_GET['state'] ?? 'all', ['all', 'unread', 'read'], true) ? $_GET['state'] : 'all';
$filterQuery = static function (string $type, string $state): string { return http_build_query(array_filter(['type' => $type !== '' ? $type : null, 'state' => $state !== 'all' ? $state : null], static fn (mixed $value): bool => $value !== null)); };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $returnType = normalize_text($_POST['notification_filter_type'] ?? '', 80);
    if (!in_array($returnType, $typeValues, true)) { $returnType = ''; }
    $returnState = in_array($_POST['notification_filter_state'] ?? 'all', ['all', 'unread', 'read'], true) ? $_POST['notification_filter_state'] : 'all';
    try {
        $action = normalize_text($_POST['notification_action'] ?? '', 30);
        if ($action === 'save_preferences') {
            save_notification_preferences((int) $user['id'], $_POST);
            flash('success', 'Your local notification preferences have been saved.');
        } elseif ($action === 'mark_read') {
            $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$notificationId) { throw new RuntimeException('Choose a valid notification.'); }
            flash('success', mark_notification_read((int) $user['id'], (int) $notificationId) ? 'Notification marked as read.' : 'That notification was already marked as read.');
        } elseif ($action === 'mark_all_read') {
            $count = mark_all_notifications_read((int) $user['id']);
            flash('success', $count > 0 ? $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.' : 'There are no unread notifications.');
        } else { throw new RuntimeException('That notification action is not available.'); }
    } catch (Throwable $exception) { flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The notification action could not be completed.'); }
    $query = $filterQuery($returnType, $returnState);
    redirect('notifications.php' . ($query !== '' ? '?' . $query : ''));
}

$preferences = notification_preferences_for_user((int) $user['id']);
$where = ['user_id = :user_id']; $params = ['user_id' => $user['id']];
if ($selectedType !== '') { $where[] = 'type = :type'; $params['type'] = $selectedType; }
if ($selectedState === 'unread') { $where[] = 'read_at IS NULL'; }
if ($selectedState === 'read') { $where[] = 'read_at IS NOT NULL'; }
$notifications = fetch_all('SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 50', $params);
$unreadCount = count(array_filter($notifications, static fn (array $notification): bool => $notification['read_at'] === null));
workspace_open('Notifications', 'notifications');
?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Operational alerts</h2><p>Filter your account’s alerts by operational category or read state, then record what has been reviewed.</p></div><div class="notification-register-actions"><span class="status-pill"><?= $unreadCount ?> unread shown</span><?php if ($unreadCount > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="notification_action" value="mark_all_read"><input type="hidden" name="notification_filter_type" value="<?= e($selectedType) ?>"><input type="hidden" name="notification_filter_state" value="<?= e($selectedState) ?>"><button class="button button-outline button-compact" type="submit">Mark all read</button></form><?php endif; ?></div></div>
    <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($message = flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
    <?php if (notification_preferences_are_available()): ?><section class="notification-preferences"><div><p class="desk-kicker">Local delivery preferences</p><h3>Choose how this account receives local alerts</h3><p>Alerts remain in this in-app register. Quetta AgriLink does not send email or SMS from this XAMPP setup.</p></div><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="notification_action" value="save_preferences"><input type="hidden" name="notification_filter_type" value="<?= e($selectedType) ?>"><input type="hidden" name="notification_filter_state" value="<?= e($selectedState) ?>"><label><input type="checkbox" name="marketplace_match_alerts_enabled" value="1" <?= $preferences['marketplace_match_alerts_enabled'] === 1 ? 'checked' : '' ?>> Notify this account when a default marketplace filter matches a new listing.</label><label><input type="checkbox" name="browser_chime_enabled" value="1" <?= $preferences['browser_chime_enabled'] === 1 ? 'checked' : '' ?>> Play the optional browser chime for a newly received alert after I enable it.</label><button class="button button-quiet" type="submit">Save local preferences</button></form></section><?php else: ?><p class="muted">Import <code>database/migrations/20260826_add_user_notification_preferences.sql</code> to manage this account’s local delivery preferences.</p><?php endif; ?>
    <form class="notification-filter" method="get"><div class="form-field"><label for="notification-type">Alert type</label><select id="notification-type" name="type"><option value="">All alert types</option><?php foreach ($availableTypes as $type): ?><option value="<?= e($type['type']) ?>" <?= $selectedType === $type['type'] ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type['type']))) ?></option><?php endforeach; ?></select></div><div class="form-field"><label for="notification-state">Read state</label><select id="notification-state" name="state"><option value="all" <?= $selectedState === 'all' ? 'selected' : '' ?>>All alerts</option><option value="unread" <?= $selectedState === 'unread' ? 'selected' : '' ?>>Unread only</option><option value="read" <?= $selectedState === 'read' ? 'selected' : '' ?>>Read only</option></select></div><div class="form-actions"><button class="button button-primary" type="submit">Apply filter</button><a class="button button-quiet" href="<?= e(app_url('notifications.php')) ?>">Clear</a></div></form>
    <div class="notification-list"><?php if ($notifications === []): ?><div class="listing-empty"><h2>No account alerts match this filter.</h2><p>Clear the filter or return after another platform event is recorded.</p></div><?php else: foreach ($notifications as $notification): ?><article class="notification-item <?= $notification['read_at'] === null ? 'is-unread' : '' ?>"><span class="notification-dot" aria-hidden="true"></span><div><h2><?= e($notification['title']) ?></h2><p><?= e($notification['body']) ?></p><div class="notification-item-actions"><?php if ($notification['action_url']): ?><a class="button button-quiet" href="<?= e(app_url($notification['action_url'])) ?>">Open related work</a><?php endif; ?><?php if ($notification['read_at'] === null): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="notification_action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="notification_filter_type" value="<?= e($selectedType) ?>"><input type="hidden" name="notification_filter_state" value="<?= e($selectedState) ?>"><button class="text-action notification-read-action" type="submit">Mark read</button></form><?php endif; ?></div></div><time datetime="<?= e($notification['created_at']) ?>"><?= e(date('j M, H:i', strtotime($notification['created_at']))) ?></time></article><?php endforeach; endif; ?></div>
</section>
<?php workspace_close(); ?>
