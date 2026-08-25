<?php
/** Orchard Ledger workspace shell: direct operational navigation, strict role context, and readable workflow data. */
declare(strict_types=1);

function workspace_links(string $role): array
{
    $common = [['Dashboard', dashboard_path($role), 'dashboard'], ['Marketplace', 'marketplace/index.php', 'marketplace'], ['Notifications', 'notifications.php', 'notifications']];
    return match ($role) {
        'farmer' => array_merge($common, [['Offers', 'farmer/offers.php', 'offers'], ['Cold storage', 'storage/index.php', 'storage'], ['Transport', 'transport/index.php', 'transport']]),
        'buyer' => array_merge($common, [['Offers', 'buyer/offers.php', 'offers']]),
        'storage_provider' => array_merge($common, [['Storage marketplace', 'storage/index.php', 'storage']]),
        'transport_provider' => array_merge($common, [['Transport marketplace', 'transport/index.php', 'transport']]),
        'admin' => array_merge($common, [['Market prices', 'market-prices.php', 'prices']]),
        default => $common,
    };
}

function workspace_open(string $title, string $active): array
{
    $user = require_login();
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    ?>
    <section class="workspace"><aside class="workspace-sidebar"><span class="role-label"><?= e($user['role_name']) ?> workspace</span><h2><?= e($user['full_name']) ?></h2><nav aria-label="Workspace navigation"><?php foreach(workspace_links($user['role_slug']) as [$label,$path,$key]): ?><a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(app_url($path)) ?>"><?= e($label) ?></a><?php endforeach;?></nav><form class="workspace-signout" method="post" action="<?= e(app_url('auth/logout.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit">Sign out securely</button></form></aside><div class="workspace-main"><div class="workspace-topbar"><div><h1><?= e($title) ?></h1><p>Operational information is scoped to your account and updated from the platform database.</p></div><div class="workspace-user">Signed in as<br><strong><?= e($user['role_name']) ?></strong></div></div>
    <?php
    return $user;
}

function workspace_close(): void
{
    echo '</div></section>';
    require __DIR__ . '/footer.php';
}

function render_status_cards(array $cards): void
{
    echo '<div class="status-grid">';
    foreach ($cards as $card) {
        echo '<article class="status-card"><span>' . e($card['label']) . '</span><strong>' . e((string) $card['value']) . '</strong><small>' . e($card['detail']) . '</small></article>';
    }
    echo '</div>';
}
