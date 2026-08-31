<?php
/** Shared product shell for the public marketplace and authenticated workspaces. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'The practical post-harvest marketplace for Balochistan growers and trade partners.';
$user = current_user();
$notificationSummary = $user === null ? ['count' => 0, 'latest_id' => 0] : unread_notification_summary((int) $user['id']);
$notificationPreferences = $user === null ? ['browser_chime_enabled' => 0] : notification_preferences_for_user((int) $user['id']);
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
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="site-navigation" data-menu-toggle>Navigation</button>
        <div class="header-navigation" id="site-navigation" data-primary-nav>
            <nav class="primary-nav" aria-label="Primary navigation">
                <a class="<?= nav_active('/marketplace') ?>" href="<?= e(app_url('marketplace/index.php')) ?>">Produce</a>
                <a class="<?= nav_active('/storage') ?>" href="<?= e(app_url('storage/index.php')) ?>">Storage</a>
                <a class="<?= nav_active('/transport') ?>" href="<?= e(app_url('transport/index.php')) ?>">Transport</a>
                <a class="<?= nav_active('/market-prices') ?>" href="<?= e(app_url('market-prices.php')) ?>">Market intelligence</a>
                <a class="<?= nav_active('/how-it-works') ?>" href="<?= e(app_url('how-it-works.php')) ?>">How it works</a>
            </nav>
            <div class="header-actions">
                <?php if ($user !== null): ?>
                    <a class="notification-link" href="<?= e(app_url('notifications.php')) ?>" aria-label="Notifications" data-notification-link data-notification-latest-id="<?= (int) $notificationSummary['latest_id'] ?>" data-notification-endpoint="<?= e(app_url('ajax/notifications/unread-summary.php')) ?>">
                        Alerts<span data-notification-count<?= $notificationSummary['count'] > 0 ? '' : ' hidden' ?>><?= $notificationSummary['count'] > 9 ? '9+' : (int) $notificationSummary['count'] ?></span>
                    </a>
                    <button class="notification-chime-toggle" type="button" data-notification-chime data-notification-chime-enabled="<?= (int) $notificationPreferences['browser_chime_enabled'] ?>" data-notification-chime-endpoint="<?= e(app_url('ajax/notifications/preferences.php')) ?>" aria-pressed="false" title="Enable notification sound">Sound off</button>
                    <a class="button button-primary" href="<?= e(app_url(dashboard_path($user['role_slug']))) ?>">Open workspace</a>
                <?php else: ?>
                    <a class="button button-quiet" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
                    <a class="button button-primary" href="<?= e(app_url('auth/register.php')) ?>">Create account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<div id="main-content">
<?php $successNotice = flash('success'); $errorNotice = flash('error'); ?>
<div class="toast-region" data-toast-region aria-live="polite" aria-relevant="additions removals">
    <?php if ($successNotice): ?><div class="flash flash-success toast" role="status" aria-atomic="true" data-toast data-toast-timeout="7000"><span class="toast-symbol" aria-hidden="true">✓</span><div class="toast-message"><strong>Action completed</strong><p><?= e($successNotice) ?></p></div><button class="toast-dismiss" type="button" data-toast-dismiss aria-label="Dismiss notification">×</button><span class="toast-progress" aria-hidden="true"></span></div><?php endif; ?>
    <?php if ($errorNotice): ?><div class="flash flash-error toast" role="alert" aria-atomic="true" data-toast data-toast-timeout="9000"><span class="toast-symbol" aria-hidden="true">!</span><div class="toast-message"><strong>Action needs attention</strong><p><?= e($errorNotice) ?></p></div><button class="toast-dismiss" type="button" data-toast-dismiss aria-label="Dismiss notification">×</button><span class="toast-progress" aria-hidden="true"></span></div><?php endif; ?>
</div>
