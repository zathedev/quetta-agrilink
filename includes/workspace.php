<?php
/** Market Desk workspace shell: role-scoped navigation and task-led framing make the local PHP work areas easier to scan. */
declare(strict_types=1);

function workspace_links(string $role): array
{
    $common = [['Dashboard', dashboard_path($role), 'dashboard'], ['Marketplace', 'marketplace/index.php', 'marketplace'], ['Notifications', 'notifications.php', 'notifications'], ['My profile', 'account/profile.php', 'profile']];
    return match ($role) {
        'farmer' => array_merge($common, [['Publish produce', 'farmer/listings.php', 'listings'], ['Offers', 'farmer/offers.php', 'offers'], ['Cold storage', 'storage/index.php', 'storage'], ['Transport', 'transport/index.php', 'transport']]),
        'buyer' => array_merge($common, [['Offers', 'buyer/offers.php', 'offers']]),
        'storage_provider' => array_merge($common, [['Storage marketplace', 'storage/index.php', 'storage']]),
        'transport_provider' => array_merge($common, [['Transport marketplace', 'transport/index.php', 'transport']]),
        'admin' => array_merge($common, [['Market prices', 'market-prices.php', 'prices'], ['Attachments', 'admin/attachments.php', 'attachments'], ['Password recovery', 'admin/password-recovery.php', 'recovery']]),
        default => $common,
    };
}

function workspace_shortcuts(string $role): array
{
    return match ($role) {
        'farmer' => [['Publish produce', 'Add current harvest with clear terms.', 'farmer/listings.php'], ['Review offers', 'Respond to buyer interest and terms.', 'farmer/offers.php'], ['Plan storage', 'Check suitable capacity before harvest moves.', 'storage/index.php']],
        'buyer' => [['Find produce', 'Compare current supply before making an offer.', 'marketplace/index.php'], ['Review offers', 'Track offers and their current outcome.', 'buyer/offers.php'], ['Check prices', 'Use market context for the next buying decision.', 'market-prices.php']],
        'storage_provider' => [['Review bookings', 'Respond to capacity requests in your register.', 'storage/dashboard.php'], ['Check capacity', 'Review available rooms and compatible produce.', 'storage/index.php'], ['Open marketplace', 'See harvest supply that may need capacity.', 'marketplace/index.php']],
        'transport_provider' => [['Review requests', 'Assess pickup, route, and load requirements.', 'transport/dashboard.php'], ['Check fleet', 'Review available vehicle capacity and coverage.', 'transport/index.php'], ['Open marketplace', 'See supply that may need a delivery plan.', 'marketplace/index.php']],
        'admin' => [['Review market records', 'Keep listings, price records, and account data current.', 'admin/dashboard.php'], ['Manage attachments', 'Review protected supporting records and audits.', 'admin/attachments.php'], ['Review recovery', 'Issue an offline reset link after verification.', 'admin/password-recovery.php']],
        default => [['Open marketplace', 'Review available trade-ready supply.', 'marketplace/index.php']],
    };
}

function workspace_activity_summary(int $userId, string $role): array
{
    if ($userId < 1) {
        return [];
    }
    $rows = fetch_all('SELECT action, entity_type, created_at FROM audit_logs WHERE actor_user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 3', ['user_id' => $userId]);
    $labels = [
        'account_registered' => 'Account created',
        'login' => 'Signed in',
        'profile_updated' => 'Profile details updated',
        'onboarding_completed' => 'Getting-started guide completed',
        'local_password_recovery_requested' => 'Local recovery requested',
        'local_password_reset_completed' => 'Password reset completed',
        'offer_created' => 'Offer submitted',
        'storage_booking_created' => 'Storage request submitted',
        'transport_request_created' => 'Transport request submitted',
        'listing_published' => 'Produce availability published',
        'listing_quantity_amended' => 'Listing quantity amended',
        'attachment_downloaded' => 'Protected attachment downloaded',
    ];
    $summary = [];
    foreach ($rows as $row) {
        $summary[] = [
            'label' => $labels[$row['action']] ?? ucfirst(str_replace('_', ' ', (string) $row['action'])),
            'detail' => ucfirst(str_replace('_', ' ', (string) $row['entity_type'])) . ' · ' . date('j M, H:i', strtotime((string) $row['created_at'])),
        ];
    }
    if ($summary === []) {
        $summary[] = ['label' => 'Your ' . strtolower($role) . ' workspace is ready', 'detail' => 'Start with the next task above to create the first account activity.'];
    }
    return $summary;
}

function workspace_open(string $title, string $active): array
{
    $user = require_login();
    $focus = match ($user['role_slug']) {
        'farmer' => ['Publish availability', 'Add a current produce record with origin, grade, quantity, and expected price.', 'farmer/listings.php', 'Publish availability'],
        'buyer' => ['Review available produce', 'Compare active supply before you send a new offer or update an existing one.', 'marketplace/index.php', 'Compare produce'],
        'storage_provider' => ['Review booking requests', 'Check dates, produce, and requested quantity before recording a capacity decision.', 'storage/dashboard.php', 'Review booking'],
        'transport_provider' => ['Review transport requests', 'Check pickup, destination, load, and timing before assigning a vehicle.', 'transport/dashboard.php', 'Review delivery request'],
        'admin' => ['Review operational records', 'Keep listings, storage, fleet, attachments, and market prices accurate for all roles.', 'admin/dashboard.php', 'Review records'],
        default => ['Review current work', 'Review the current operational records in your workspace.', 'marketplace/index.php', 'Review marketplace'],
    };
    $activitySummary = $active === 'dashboard' ? workspace_activity_summary((int) $user['id'], $user['role_name']) : [];
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    ?>
    <section class="workspace"><aside class="workspace-sidebar"><span class="role-label"><?= e($user['role_name']) ?> workspace</span><h2><?= e($user['full_name']) ?></h2><nav aria-label="Workspace navigation"><?php foreach(workspace_links($user['role_slug']) as [$label,$path,$key]): ?><a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(app_url($path)) ?>"><?= e($label) ?></a><?php endforeach;?></nav><form class="workspace-signout" method="post" action="<?= e(app_url('auth/logout.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit">Sign out securely</button></form></aside><div class="workspace-main"><div class="workspace-topbar"><div><p class="desk-kicker"><?= e($user['role_name']) ?> workspace</p><h1><?= e($title) ?></h1><p>Start with the work that needs your attention. Your records and available actions are scoped to this account.</p></div><div class="workspace-user">Signed in as<br><strong><?= e($user['role_name']) ?></strong></div></div><?php if ($active === 'dashboard'): ?><section class="workspace-focus"><div><span>Next commercial action</span><h2><?= e($focus[0]) ?></h2><p><?= e($focus[1]) ?></p></div><a class="button button-primary" href="<?= e(app_url($focus[2])) ?>"><?= e($focus[3]) ?></a></section><section class="workspace-activity-summary"><div><p class="desk-kicker">Recent account activity</p><h2>What changed most recently</h2></div><div class="activity-summary-list"><?php foreach ($activitySummary as $entry): ?><article><strong><?= e($entry['label']) ?></strong><span><?= e($entry['detail']) ?></span></article><?php endforeach; ?></div></section><?php if (!onboarding_is_complete((int) $user['id'])): ?><section class="workspace-onboarding"><div><span>Workspace guide</span><h2>Review attention items, then confirm the next commercial action.</h2><ol><li>Choose the record that needs a response.</li><li>Confirm visible terms before taking action.</li><li>Return to the record when its status changes.</li></ol></div><form method="post" action="<?= e(app_url('ajax/onboarding/complete.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="button button-quiet" type="submit">Continue to workspace</button></form></section><?php endif; ?><section class="workspace-shortcuts"><div class="workspace-section-header"><div><p class="desk-kicker">Trade shortcuts</p><h2>Common commercial actions</h2><p>Open the work you return to most often.</p></div></div><div class="quick-links"><?php foreach (workspace_shortcuts($user['role_slug']) as [$label, $description, $path]): ?><a class="quick-link" href="<?= e(app_url($path)) ?>"><strong><?= e($label) ?></strong><span><?= e($description) ?></span></a><?php endforeach; ?></div></section><?php endif; ?>
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
