<?php
/** Market Desk public shell: short task navigation keeps the local PHP app approachable before users know its workflows. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'The practical post-harvest marketplace for Balochistan growers and trade partners.';
$user = current_user();
$notificationSummary = $user === null ? ['count' => 0, 'latest_id' => 0] : unread_notification_summary((int) $user['id']);
function nav_active(string $needle): string { return str_contains($_SERVER['REQUEST_URI'] ?? '', $needle) ? 'is-active' : ''; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Serif:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/market-desk.css')) ?>">
</head>
<body>
<div class="site-notice"><div class="site-container">Serving Quetta first. Built for Balochistan’s post-harvest trade.</div></div>
<header class="site-header">
    <div class="site-container header-inner">
        <a class="brand" href="<?= e(app_url()) ?>" aria-label="Quetta AgriLink home">
            <img src="/manus-storage/quetta-agrilink-mark_a4b760ba.png" alt="" width="42" height="42">
            <span><strong>Quetta Agri</strong><b>Link</b></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-menu-toggle>Menu</button>
        <nav class="primary-nav" data-primary-nav aria-label="Primary navigation">
            <a class="<?= nav_active('/marketplace') ?>" href="<?= e(app_url('marketplace/index.php')) ?>">Find produce</a>
            <a class="<?= nav_active('/storage') ?>" href="<?= e(app_url('storage/index.php')) ?>">Storage</a>
            <a class="<?= nav_active('/transport') ?>" href="<?= e(app_url('transport/index.php')) ?>">Transport</a>
            <a class="<?= nav_active('/market-prices') ?>" href="<?= e(app_url('market-prices.php')) ?>">Prices</a>
        </nav>
        <div class="header-actions">
            <?php if ($user !== null): ?>
                <a class="notification-link" href="<?= e(app_url('notifications.php')) ?>" aria-label="Notifications" data-notification-link data-notification-latest-id="<?= (int) $notificationSummary['latest_id'] ?>" data-notification-endpoint="<?= e(app_url('ajax/notifications/unread-summary.php')) ?>">
                    Alerts<span data-notification-count<?= $notificationSummary['count'] > 0 ? '' : ' hidden' ?>><?= $notificationSummary['count'] > 9 ? '9+' : (int) $notificationSummary['count'] ?></span>
                </a>
                <button class="notification-chime-toggle" type="button" data-notification-chime aria-pressed="false" title="Enable notification sound">Sound off</button>
                <a class="button button-primary" href="<?= e(app_url(dashboard_path($user['role_slug']))) ?>">My workspace</a>
            <?php else: ?>
                <a class="button button-quiet" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a>
                <a class="button button-primary" href="<?= e(app_url('auth/register.php')) ?>">Create account</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main>
<?php if ($notice = flash('success')): ?><div class="site-container flash flash-success"><?= e($notice) ?></div><?php endif; ?>
<?php if ($notice = flash('error')): ?><div class="site-container flash flash-error"><?= e($notice) ?></div><?php endif; ?>
