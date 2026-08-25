<?php
/** Orchard Ledger public shell: warm paper, Quetta Canopy green, precise trade information. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'The practical post-harvest marketplace for Balochistan growers and trade partners.';
$user = current_user();
$unreadNotifications = $user === null ? 0 : unread_notification_count((int) $user['id']);
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
            <a class="<?= nav_active('/marketplace') ?>" href="<?= e(app_url('marketplace/index.php')) ?>">Marketplace</a>
            <a class="<?= nav_active('/storage') ?>" href="<?= e(app_url('storage/index.php')) ?>">Cold storage</a>
            <a class="<?= nav_active('/transport') ?>" href="<?= e(app_url('transport/index.php')) ?>">Transport</a>
            <a class="<?= nav_active('/market-prices') ?>" href="<?= e(app_url('market-prices.php')) ?>">Market prices</a>
        </nav>
        <div class="header-actions">
            <?php if ($user !== null): ?>
                <a class="notification-link" href="<?= e(app_url('notifications.php')) ?>" aria-label="Notifications">
                    Alerts<?php if ($unreadNotifications > 0): ?><span><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span><?php endif; ?>
                </a>
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
