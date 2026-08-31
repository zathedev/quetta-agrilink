<?php
/** Shared product shell for the public marketplace and authenticated workspaces. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'The practical post-harvest marketplace for Balochistan growers and trade partners.';
$user = current_user();
$notificationSummary = $user === null ? ['count' => 0, 'latest_id' => 0] : unread_notification_summary((int) $user['id']);
$notificationPreferences = $user === null ? ['browser_chime_enabled' => 0] : notification_preferences_for_user((int) $user['id']);
$headerNotifications = $user === null ? [] : latest_notifications_for_user((int) $user['id'], 5);
$profileInitials = '';
if ($user !== null) {
    $nameParts = preg_split('/\s+/', trim((string) $user['full_name'])) ?: [];
    $initialParts = count($nameParts) > 1 ? [$nameParts[0], $nameParts[count($nameParts) - 1]] : $nameParts;
    $profileInitials = strtoupper(implode('', array_map(static fn (string $part): string => substr($part, 0, 1), $initialParts)));
}
function nav_active(string $needle): string { return str_contains($_SERVER['REQUEST_URI'] ?? '', $needle) ? 'is-active' : ''; }
$stylesheet_url = static function (string $relativePath): string {
    $absolutePath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
    return app_url($relativePath) . '?v=' . rawurlencode($version);
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/local-fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/market-desk.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/public-information-parity.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/operator-transition.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/market-data-import.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/support-desk.css')) ?>">
    <link rel="stylesheet" href="<?= e($stylesheet_url('assets/css/product-system.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
    <div class="site-container header-inner">
        <a class="brand" href="<?= e(app_url()) ?>" aria-label="Quetta AgriLink home">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-wordmark"><strong>Quetta AgriLink</strong><small>Agricultural commerce</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="site-navigation" data-menu-toggle><span class="menu-toggle-icon" aria-hidden="true"></span><span data-menu-label>Menu</span></button>
        <div class="header-navigation" id="site-navigation" data-primary-nav>
            <nav class="primary-nav" aria-label="Primary navigation">
                <a class="<?= nav_active('/marketplace') ?>" href="<?= e(app_url('marketplace/index.php')) ?>">Produce</a>
                <a class="<?= nav_active('/storage') ?>" href="<?= e(app_url('storage/index.php')) ?>">Storage</a>
                <a class="<?= nav_active('/transport') ?>" href="<?= e(app_url('transport/index.php')) ?>">Transport</a>
                <a class="<?= nav_active('/market-prices') ?>" href="<?= e(app_url('market-prices.php')) ?>">Market intelligence</a>
                <a class="<?= nav_active('/how-it-works') ?>" href="<?= e(app_url('how-it-works.php')) ?>">How it works</a>
            </nav>
            <?php if ($user === null): ?>
                <div class="header-actions header-actions-guest">
                    <a class="button button-quiet" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
                    <a class="button button-primary" href="<?= e(app_url('auth/register.php')) ?>">Create account</a>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($user !== null): ?>
            <div class="header-account-actions header-account-role-<?= e($user['role_slug']) ?>">
                <div class="header-menu header-notification-menu" data-header-menu>
                    <button class="header-icon-button notification-link" type="button" aria-label="Open notifications" aria-expanded="false" aria-controls="header-notification-dropdown" data-header-menu-toggle data-notification-link data-notification-latest-id="<?= (int) $notificationSummary['latest_id'] ?>" data-notification-endpoint="<?= e(app_url('ajax/notifications/unread-summary.php')) ?>">
                        <span class="notification-bell-icon" aria-hidden="true"></span>
                        <span class="header-action-label">Notifications</span>
                        <span class="notification-count" data-notification-count<?= $notificationSummary['count'] > 0 ? '' : ' hidden' ?>><?= $notificationSummary['count'] > 9 ? '9+' : (int) $notificationSummary['count'] ?></span>
                    </button>
                    <section class="header-dropdown notification-dropdown" id="header-notification-dropdown" aria-label="Latest notifications" hidden data-header-menu-panel data-notification-dropdown data-notification-latest-endpoint="<?= e(app_url('ajax/notifications/latest.php')) ?>" data-notification-mark-read-endpoint="<?= e(app_url('ajax/notifications/mark-read.php')) ?>" data-notification-mark-all-endpoint="<?= e(app_url('ajax/notifications/mark-all-read.php')) ?>">
                        <header class="header-dropdown-heading">
                            <div><span>Account activity</span><h2>Latest notifications</h2></div>
                            <form method="post" action="<?= e(app_url('notifications.php')) ?>" data-notification-mark-all-form>
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="notification_action" value="mark_all_read">
                                <button type="submit"<?= $notificationSummary['count'] < 1 ? ' hidden' : '' ?>>Mark all read</button>
                            </form>
                        </header>
                        <div class="header-notification-list" data-notification-list>
                            <?php if ($headerNotifications === []): ?>
                                <div class="header-notification-empty"><span class="notification-bell-icon" aria-hidden="true"></span><strong>You’re all caught up</strong><p>New account activity will appear here.</p></div>
                            <?php else: foreach ($headerNotifications as $notification): ?>
                                <article class="header-notification-item<?= $notification['read_at'] === null ? ' is-unread' : '' ?>" data-notification-item data-notification-id="<?= (int) $notification['id'] ?>">
                                    <span class="header-notification-status" aria-hidden="true"></span>
                                    <div class="header-notification-copy">
                                        <?php if ($notification['action_url']): ?><a href="<?= e(app_url($notification['action_url'])) ?>"><?php endif; ?>
                                            <strong><?= e($notification['title']) ?></strong><p><?= e($notification['body']) ?></p>
                                        <?php if ($notification['action_url']): ?></a><?php endif; ?>
                                        <time datetime="<?= e($notification['created_at']) ?>"><?= e(date('j M, H:i', strtotime($notification['created_at']))) ?></time>
                                    </div>
                                    <?php if ($notification['read_at'] === null): ?>
                                        <form method="post" action="<?= e(app_url('notifications.php')) ?>" data-notification-read-form>
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="notification_action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                            <button type="submit" aria-label="Mark <?= e($notification['title']) ?> as read" title="Mark as read"><span aria-hidden="true">✓</span></button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; endif; ?>
                        </div>
                        <footer class="notification-dropdown-footer">
                            <a href="<?= e(app_url('notifications.php')) ?>">View all notifications <span aria-hidden="true">→</span></a>
                            <button class="notification-chime-toggle" type="button" data-notification-chime data-notification-chime-enabled="<?= (int) $notificationPreferences['browser_chime_enabled'] ?>" data-notification-chime-endpoint="<?= e(app_url('ajax/notifications/preferences.php')) ?>" aria-pressed="false" title="Enable notification sound">Sound off</button>
                        </footer>
                    </section>
                </div>
                <div class="header-menu header-profile-menu" data-header-menu>
                    <button class="profile-menu-toggle" type="button" aria-label="Open account menu" aria-expanded="false" aria-controls="header-profile-dropdown" data-header-menu-toggle>
                        <span class="profile-avatar" aria-hidden="true"><?= e($profileInitials) ?></span>
                        <span class="profile-menu-copy"><strong><?= e($user['full_name']) ?></strong><small><?= e($user['role_name']) ?></small></span>
                        <span class="profile-menu-chevron" aria-hidden="true"></span>
                    </button>
                    <section class="header-dropdown profile-dropdown" id="header-profile-dropdown" aria-label="Account menu" hidden data-header-menu-panel>
                        <header class="profile-dropdown-account"><span class="profile-avatar" aria-hidden="true"><?= e($profileInitials) ?></span><div><strong><?= e($user['full_name']) ?></strong><small><?= e($user['email']) ?></small><em><?= e($user['role_name']) ?></em></div></header>
                        <nav aria-label="Account shortcuts">
                            <a href="<?= e(app_url(dashboard_path($user['role_slug']))) ?>"><span class="account-menu-icon dashboard-icon" aria-hidden="true"></span><span><strong>Dashboard</strong><small>Open your role workspace</small></span><span aria-hidden="true">→</span></a>
                            <a href="<?= e(app_url('account/profile.php')) ?>"><span class="account-menu-icon settings-icon" aria-hidden="true"></span><span><strong>Profile</strong><small>Review your business details</small></span><span aria-hidden="true">→</span></a>
                        </nav>
                        <form class="profile-dropdown-logout" method="post" action="<?= e(app_url('auth/logout.php')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <button type="submit"><span class="account-menu-icon logout-icon" aria-hidden="true"></span><span>Log out</span></button>
                        </form>
                    </section>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>
<div id="main-content">
<?php $successNotice = flash('success'); $errorNotice = flash('error'); ?>
<div class="toast-region" data-toast-region aria-live="polite" aria-relevant="additions removals">
    <?php if ($successNotice): ?><div class="flash flash-success toast" role="status" aria-atomic="true" data-toast data-toast-timeout="7000"><span class="toast-symbol" aria-hidden="true">✓</span><div class="toast-message"><strong>Action completed</strong><p><?= e($successNotice) ?></p></div><button class="toast-dismiss" type="button" data-toast-dismiss aria-label="Dismiss notification">×</button><span class="toast-progress" aria-hidden="true"></span></div><?php endif; ?>
    <?php if ($errorNotice): ?><div class="flash flash-error toast" role="alert" aria-atomic="true" data-toast data-toast-timeout="9000"><span class="toast-symbol" aria-hidden="true">!</span><div class="toast-message"><strong>Action needs attention</strong><p><?= e($errorNotice) ?></p></div><button class="toast-dismiss" type="button" data-toast-dismiss aria-label="Dismiss notification">×</button><span class="toast-progress" aria-hidden="true"></span></div><?php endif; ?>
</div>
