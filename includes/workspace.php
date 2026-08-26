<?php
/** Market Desk workspace shell: role-scoped navigation, task-led framing, and account-owned activity controls support clear local operational work. */
declare(strict_types=1);

function workspace_links(string $role): array
{
    $common = [['Dashboard', dashboard_path($role), 'dashboard'], ['Marketplace', 'marketplace/index.php', 'marketplace'], ['Notifications', 'notifications.php', 'notifications'], ['My profile', 'account/profile.php', 'profile']];
    return match ($role) {
        'farmer' => array_merge($common, [['Publish produce', 'farmer/listings.php', 'listings'], ['Offers', 'farmer/offers.php', 'offers'], ['Cold storage', 'storage/index.php', 'storage'], ['Transport', 'transport/index.php', 'transport']]),
        'buyer' => array_merge($common, [['Offers', 'buyer/offers.php', 'offers']]),
        'storage_provider' => array_merge($common, [['Storage marketplace', 'storage/index.php', 'storage']]),
        'transport_provider' => array_merge($common, [['Transport marketplace', 'transport/index.php', 'transport']]),
        'admin' => array_merge($common, [['Market prices', 'market-prices.php', 'prices'], ['Attachments', 'admin/attachments.php', 'attachments'], ['Password recovery', 'admin/password-recovery.php', 'recovery'], ['Contact verification', 'admin/contact-verification.php', 'contact_verification']]),
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

function dashboard_activity_presets_are_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'dashboard_activity_presets']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function workspace_activity_date_range(array $query, ?array $preset = null): array
{
    $parseDate = static function (mixed $value): ?DateTimeImmutable {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    };
    $source = $preset ?? $query;
    $from = $parseDate($source['activity_from'] ?? null);
    $to = $parseDate($source['activity_to'] ?? null);
    if ($from !== null && $to !== null && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    return ['from' => $from, 'to' => $to];
}

function dashboard_activity_presets_for_user(int $userId): array
{
    if ($userId < 1 || !dashboard_activity_presets_are_available()) {
        return [];
    }
    return fetch_all('SELECT id, preset_name, activity_from, activity_to, updated_at FROM dashboard_activity_presets WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC', ['user_id' => $userId]);
}

function dashboard_activity_preset_for_user(int $userId, int $presetId): ?array
{
    if ($userId < 1 || $presetId < 1 || !dashboard_activity_presets_are_available()) {
        return null;
    }
    return fetch_one('SELECT id, preset_name, activity_from, activity_to FROM dashboard_activity_presets WHERE id = :preset_id AND user_id = :user_id LIMIT 1', ['preset_id' => $presetId, 'user_id' => $userId]);
}

function dashboard_activity_preset_label(?DateTimeImmutable $from, ?DateTimeImmutable $to): string
{
    if ($from !== null && $to !== null) {
        return $from->format('j M Y') . ' to ' . $to->format('j M Y');
    }
    if ($from !== null) {
        return 'From ' . $from->format('j M Y');
    }
    return $to !== null ? 'Up to ' . $to->format('j M Y') : 'All activity';
}

function save_dashboard_activity_preset(int $userId, array $input): void
{
    if (!dashboard_activity_presets_are_available()) {
        throw new RuntimeException('Saved dashboard ranges are not ready. Import database/migrations/20260826_add_dashboard_activity_presets.sql, then refresh this page.');
    }
    $name = normalize_text($input['preset_name'] ?? '', 60);
    $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($userId < 1 || $length < 2) {
        throw new RuntimeException('Enter a short name for this saved activity range.');
    }
    $range = workspace_activity_date_range($input);
    if ($range['from'] === null && $range['to'] === null) {
        throw new RuntimeException('Choose at least one date before saving an activity range.');
    }
    $statement = db()->prepare('INSERT INTO dashboard_activity_presets (user_id, preset_name, activity_from, activity_to) VALUES (:user_id, :preset_name, :activity_from, :activity_to) ON DUPLICATE KEY UPDATE activity_from = VALUES(activity_from), activity_to = VALUES(activity_to), updated_at = NOW()');
    $statement->execute(['user_id' => $userId, 'preset_name' => $name, 'activity_from' => $range['from']?->format('Y-m-d'), 'activity_to' => $range['to']?->format('Y-m-d')]);
    audit_log($userId, 'dashboard_activity_preset_saved', 'dashboard_activity_presets', null, ['preset_name' => $name, 'from' => $range['from']?->format('Y-m-d'), 'to' => $range['to']?->format('Y-m-d')]);
}

function delete_dashboard_activity_preset(int $userId, int $presetId): void
{
    if (!dashboard_activity_presets_are_available() || $userId < 1 || $presetId < 1) {
        throw new RuntimeException('That saved activity range is no longer available.');
    }
    $preset = dashboard_activity_preset_for_user($userId, $presetId);
    if ($preset === null || !execute_query('DELETE FROM dashboard_activity_presets WHERE id = :preset_id AND user_id = :user_id', ['preset_id' => $presetId, 'user_id' => $userId])) {
        throw new RuntimeException('That saved activity range is no longer available.');
    }
    audit_log($userId, 'dashboard_activity_preset_deleted', 'dashboard_activity_presets', $presetId, ['preset_name' => $preset['preset_name']]);
}

function workspace_activity_summary(int $userId, string $role, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
{
    if ($userId < 1) {
        return [];
    }
    $conditions = ['actor_user_id = :user_id'];
    $params = ['user_id' => $userId];
    if ($from !== null) {
        $conditions[] = 'created_at >= :activity_from';
        $params['activity_from'] = $from->format('Y-m-d 00:00:00');
    }
    if ($to !== null) {
        $conditions[] = 'created_at < :activity_to_exclusive';
        $params['activity_to_exclusive'] = $to->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    $rows = fetch_all('SELECT action, entity_type, created_at FROM audit_logs WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC, id DESC LIMIT 3', $params);
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
        'dashboard_activity_preset_saved' => 'Activity range saved',
        'dashboard_activity_preset_deleted' => 'Activity range deleted',
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
    if ($active === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        try {
            $action = (string) ($_POST['activity_preset_action'] ?? '');
            if ($action === 'save') {
                save_dashboard_activity_preset((int) $user['id'], $_POST);
                flash('success', 'Your activity date range has been saved for this account.');
            } elseif ($action === 'delete') {
                delete_dashboard_activity_preset((int) $user['id'], (int) ($_POST['activity_preset_id'] ?? 0));
                flash('success', 'The saved activity date range has been removed.');
            } else {
                throw new RuntimeException('Choose a valid saved activity range action.');
            }
        } catch (Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect(dashboard_path($user['role_slug']));
    }
    $focus = match ($user['role_slug']) {
        'farmer' => ['Publish availability', 'Add a current produce record with origin, grade, quantity, and expected price.', 'farmer/listings.php', 'Publish availability'],
        'buyer' => ['Review available produce', 'Compare active supply before you send a new offer or update an existing one.', 'marketplace/index.php', 'Compare produce'],
        'storage_provider' => ['Review booking requests', 'Check dates, produce, and requested quantity before recording a capacity decision.', 'storage/dashboard.php', 'Review booking'],
        'transport_provider' => ['Review transport requests', 'Check pickup, destination, load, and timing before assigning a vehicle.', 'transport/dashboard.php', 'Review delivery request'],
        'admin' => ['Review operational records', 'Keep listings, storage, fleet, attachments, and market prices accurate for all roles.', 'admin/dashboard.php', 'Review records'],
        default => ['Review current work', 'Review the current operational records in your workspace.', 'marketplace/index.php', 'Review marketplace'],
    };
    $presets = $active === 'dashboard' ? dashboard_activity_presets_for_user((int) $user['id']) : [];
    $presetId = filter_var($_GET['activity_preset'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $selectedPreset = $active === 'dashboard' ? dashboard_activity_preset_for_user((int) $user['id'], (int) $presetId) : null;
    $activityRange = $active === 'dashboard' ? workspace_activity_date_range($_GET, $selectedPreset) : ['from' => null, 'to' => null];
    $activitySummary = $active === 'dashboard' ? workspace_activity_summary((int) $user['id'], $user['role_name'], $activityRange['from'], $activityRange['to']) : [];
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    ?>
    <section class="workspace"><aside class="workspace-sidebar"><span class="role-label"><?= e($user['role_name']) ?> workspace</span><h2><?= e($user['full_name']) ?></h2><nav aria-label="Workspace navigation"><?php foreach(workspace_links($user['role_slug']) as [$label,$path,$key]): ?><a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(app_url($path)) ?>"><?= e($label) ?></a><?php endforeach;?></nav><form class="workspace-signout" method="post" action="<?= e(app_url('auth/logout.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit">Sign out securely</button></form></aside><div class="workspace-main"><div class="workspace-topbar"><div><p class="desk-kicker"><?= e($user['role_name']) ?> workspace</p><h1><?= e($title) ?></h1><p>Start with the work that needs your attention. Your records and available actions are scoped to this account.</p></div><div class="workspace-user">Signed in as<br><strong><?= e($user['role_name']) ?></strong></div></div><?php if ($active === 'dashboard'): ?><section class="workspace-focus"><div><span>Next commercial action</span><h2><?= e($focus[0]) ?></h2><p><?= e($focus[1]) ?></p></div><a class="button button-primary" href="<?= e(app_url($focus[2])) ?>"><?= e($focus[3]) ?></a></section><section class="workspace-activity-summary"><div><p class="desk-kicker">Recent account activity</p><h2>What changed most recently</h2></div><form class="activity-date-filter" method="get"><label>Saved range<select name="activity_preset"><option value="">Current manual dates</option><?php foreach ($presets as $preset): ?><option value="<?= (int) $preset['id'] ?>" <?= $selectedPreset !== null && (int) $selectedPreset['id'] === (int) $preset['id'] ? 'selected' : '' ?>><?= e($preset['preset_name']) ?></option><?php endforeach; ?></select></label><label>From<input type="date" name="activity_from" value="<?= e($selectedPreset === null ? ($activityRange['from']?->format('Y-m-d') ?? '') : '') ?>"></label><label>To<input type="date" name="activity_to" value="<?= e($selectedPreset === null ? ($activityRange['to']?->format('Y-m-d') ?? '') : '') ?>"></label><button class="button button-quiet" type="submit">Apply activity range</button><a href="<?= e(app_url(dashboard_path($user['role_slug']))) ?>">Clear dates</a></form><?php if (dashboard_activity_presets_are_available()): ?><form class="activity-date-filter" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="activity_preset_action" value="save"><input type="hidden" name="activity_from" value="<?= e($activityRange['from']?->format('Y-m-d') ?? '') ?>"><input type="hidden" name="activity_to" value="<?= e($activityRange['to']?->format('Y-m-d') ?? '') ?>"><label>Save this range as<input name="preset_name" maxlength="60" placeholder="e.g. Current harvest week" required></label><button class="button button-quiet" type="submit">Save range</button></form><?php if ($presets !== []): ?><div class="activity-preset-list"><?php foreach ($presets as $preset): $presetRange = workspace_activity_date_range(['activity_from' => $preset['activity_from'], 'activity_to' => $preset['activity_to']]); ?><div><a href="<?= e(app_url(dashboard_path($user['role_slug']) . '?activity_preset=' . (int) $preset['id'])) ?>"><strong><?= e($preset['preset_name']) ?></strong><span><?= e(dashboard_activity_preset_label($presetRange['from'], $presetRange['to'])) ?></span></a><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="activity_preset_action" value="delete"><input type="hidden" name="activity_preset_id" value="<?= (int) $preset['id'] ?>"><button type="submit">Remove</button></form></div><?php endforeach; ?></div><?php endif; ?><?php else: ?><p class="muted">Import <code>database/migrations/20260826_add_dashboard_activity_presets.sql</code> to save reusable activity date ranges.</p><?php endif; ?><div class="activity-summary-list"><?php foreach ($activitySummary as $entry): ?><article><strong><?= e($entry['label']) ?></strong><span><?= e($entry['detail']) ?></span></article><?php endforeach; ?></div></section><?php if (!onboarding_is_complete((int) $user['id'])): ?><section class="workspace-onboarding"><div><span>Workspace guide</span><h2>Review attention items, then confirm the next commercial action.</h2><ol><li>Choose the record that needs a response.</li><li>Confirm visible terms before taking action.</li><li>Return to the record when its status changes.</li></ol></div><form method="post" action="<?= e(app_url('ajax/onboarding/complete.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="button button-quiet" type="submit">Continue to workspace</button></form></section><?php endif; ?><section class="workspace-shortcuts"><div class="workspace-section-header"><div><p class="desk-kicker">Trade shortcuts</p><h2>Common commercial actions</h2><p>Open the work you return to most often.</p></div></div><div class="quick-links"><?php foreach (workspace_shortcuts($user['role_slug']) as [$label, $description, $path]): ?><a class="quick-link" href="<?= e(app_url($path)) ?>"><strong><?= e($label) ?></strong><span><?= e($description) ?></span></a><?php endforeach; ?></div></section><?php endif; ?>
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
