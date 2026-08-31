<?php
/** Administrator dashboard: platform health, governance attention and market operations. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['admin']);
$summaryWindow = workspace_summary_window();
$periodScope = ['from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];

$platform = fetch_one('SELECT SUM(status="active") AS active_users,COUNT(*) AS total_users FROM users');
$newAccounts = fetch_one('SELECT COUNT(*) AS count FROM users WHERE created_at >= :from AND created_at < :to', $periodScope);
$listings = fetch_one('SELECT COUNT(*) AS count FROM produce_listings WHERE status="active" AND quantity_available > 0');
$orders = fetch_one(
    'SELECT COUNT(*) AS count,COALESCE(SUM(total_amount),0) AS value FROM orders WHERE created_at >= :from AND created_at < :to',
    $periodScope
);
$completedOrders = fetch_one('SELECT COUNT(*) AS count FROM orders WHERE status="completed" AND completed_at >= :from AND completed_at < :to', $periodScope);
$capacity = fetch_one('SELECT COALESCE(SUM(total_capacity_kg),0) AS total,COALESCE(SUM(available_capacity_kg),0) AS available,COUNT(*) AS facilities FROM storage_facilities WHERE status="active"');
$supportAttention = support_desk_dashboard_attention($user);

$totalCapacity = (float) ($capacity['total'] ?? 0);
$utilization = $totalCapacity > 0 ? (int) round((1 - ((float) ($capacity['available'] ?? 0) / $totalCapacity)) * 100) : 0;
$supportQueue = (int) $supportAttention['queue_open'];
$focus = $supportQueue > 0
    ? ['Review the support queue', $supportQueue . ' routed request' . ($supportQueue === 1 ? '' : 's') . ' need accountable follow-through.', 'support.php', 'Open support queue']
    : ['Review platform operations', 'Check account, listing, service and market-data records for accuracy and ownership.', 'admin/management.php', 'Open operations register'];

workspace_open('Administrator dashboard', 'dashboard', ['focus' => $focus]);
render_status_cards([
    ['label' => 'Active accounts', 'value' => (int) ($platform['active_users'] ?? 0), 'detail' => 'enabled participant and operator accounts', 'scope' => 'Live', 'tone' => 'positive'],
    ['label' => 'New accounts', 'value' => (int) ($newAccounts['count'] ?? 0), 'detail' => 'accounts created in this period', 'scope' => 'Period'],
    ['label' => 'Active listings', 'value' => (int) ($listings['count'] ?? 0), 'detail' => 'marketplace records with available quantity', 'scope' => 'Live'],
    ['label' => 'Orders created', 'value' => (int) ($orders['count'] ?? 0), 'detail' => 'orders opened in this period', 'scope' => 'Period'],
    ['label' => 'Completed orders', 'value' => (int) ($completedOrders['count'] ?? 0), 'detail' => 'orders completed in this period', 'scope' => 'Period'],
    ['label' => 'Order value', 'value' => 'Rs. ' . number_format((float) ($orders['value'] ?? 0), 0), 'detail' => 'recorded order value in this period', 'scope' => 'Period'],
    ['label' => 'Storage utilization', 'value' => $utilization . '%', 'detail' => number_format($totalCapacity, 0) . ' kg active listed capacity', 'scope' => 'Live'],
    ['label' => 'Support queue', 'value' => $supportQueue, 'detail' => $supportAttention['available'] ? 'open routed requests' : 'support register unavailable', 'scope' => 'Needs action', 'tone' => $supportQueue > 0 ? 'attention' : 'positive'],
], $summaryWindow, ['heading' => 'Platform operating position']);

$recentAccounts = fetch_all('SELECT u.full_name,u.email,u.status,r.name AS role_name,u.created_at FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.created_at DESC LIMIT 8');
$roleCounts = fetch_all('SELECT r.name,COUNT(u.id) AS count FROM roles r LEFT JOIN users u ON u.role_id=r.id AND u.status="active" GROUP BY r.id ORDER BY r.id');
$topProducts = fetch_all('SELECT pc.name,COALESCE(pl.listings,0) AS listings,COALESCE(oi.sales,0) AS sales FROM produce_categories pc LEFT JOIN (SELECT category_id,COUNT(*) AS listings FROM produce_listings WHERE status="active" GROUP BY category_id) pl ON pl.category_id=pc.id LEFT JOIN (SELECT category_id,SUM(line_total) AS sales FROM order_items GROUP BY category_id) oi ON oi.category_id=pc.id ORDER BY sales DESC,listings DESC LIMIT 6');
$monthly = fetch_all('SELECT DATE_FORMAT(created_at,"%Y-%m") AS month,COUNT(*) AS orders,COALESCE(SUM(total_amount),0) AS value FROM orders WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(created_at,"%Y-%m") ORDER BY month');
$transportActivity = fetch_all('SELECT status,COUNT(*) AS count,COALESCE(SUM(COALESCE(final_price,estimated_price)),0) AS value FROM transport_requests GROUP BY status ORDER BY FIELD(status,"requested","accepted","driver_assigned","pickup_scheduled","picked_up","in_transit","delivered","cancelled")');
?>
<div class="dashboard-content-grid">
    <section class="workspace-section dashboard-panel dashboard-records" aria-labelledby="recent-accounts-title">
        <div class="workspace-section-header">
            <div><p class="desk-kicker">Governance register</p><h2 id="recent-accounts-title">Recent accounts</h2><p>Confirm each new account’s role and current access state.</p></div>
            <a class="button button-outline" href="<?= e(app_url('admin/operator-accounts.php')) ?>">Manage operators</a>
        </div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Account</th><th>Role</th><th>Email</th><th>Status</th><th>Created</th></tr></thead>
            <tbody><?php if ($recentAccounts === []): ?><tr><td colspan="5">No account records are available.</td></tr><?php else: foreach ($recentAccounts as $account): ?><tr><td><?= e($account['full_name']) ?></td><td><?= e($account['role_name']) ?></td><td><?= e($account['email']) ?></td><td><span class="status-pill <?= e($account['status']) ?>"><?= e(ucfirst($account['status'])) ?></span></td><td><?= e(date('j M Y', strtotime($account['created_at']))) ?></td></tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
    <?php render_dashboard_shortcuts('admin', 'Operate and govern', 'Open the registers that need routine administrator ownership.'); ?>
</div>

<section class="workspace-section dashboard-analytics" aria-labelledby="platform-analysis-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Platform analysis</p><h2 id="platform-analysis-title">Participation and trade signals</h2><p>Recorded platform totals provide operational context; they are not forecasts.</p></div></div>
    <div class="dashboard-analytics-grid">
        <article class="dashboard-analytics-panel"><h3>Active accounts by role</h3><p class="dashboard-panel-note">Current enabled accounts</p><?php foreach ($roleCounts as $role): ?><div class="analytics-row"><span><?= e($role['name']) ?></span><strong><?= (int) $role['count'] ?></strong></div><?php endforeach; ?></article>
        <article class="dashboard-analytics-panel"><h3>Produce activity</h3><p class="dashboard-panel-note">Active listings and recorded all-time sales</p><?php if ($topProducts === []): ?><p class="muted">No produce activity yet.</p><?php else: foreach ($topProducts as $product): ?><div class="analytics-row"><span><?= e($product['name']) ?> · <?= (int) $product['listings'] ?> active</span><strong>Rs. <?= number_format((float) $product['sales'], 0) ?></strong></div><?php endforeach; endif; ?></article>
        <article class="dashboard-analytics-panel"><h3>Orders over six months</h3><p class="dashboard-panel-note">Created orders and recorded value</p><?php if ($monthly === []): ?><p class="muted">No order time series yet.</p><?php else: foreach ($monthly as $point): ?><div class="analytics-row"><span><?= e(date('M Y', strtotime($point['month'] . '-01'))) ?></span><strong><?= (int) $point['orders'] ?> · Rs. <?= number_format((float) $point['value'], 0) ?></strong></div><?php endforeach; endif; ?></article>
    </div>
</section>

<section class="workspace-section dashboard-panel" aria-labelledby="transport-activity-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Logistics register</p><h2 id="transport-activity-title">Transport activity</h2><p>Current request volume and recorded value by dispatch milestone.</p></div><a class="button button-outline" href="<?= e(app_url('admin/management.php')) ?>">Open operations</a></div>
    <div class="dashboard-activity-strip"><?php if ($transportActivity === []): ?><p class="muted">No transport activity yet.</p><?php else: foreach ($transportActivity as $activity): ?><article><span><?= e(order_status_label($activity['status'])) ?></span><strong><?= (int) $activity['count'] ?></strong><small>Rs. <?= number_format((float) $activity['value'], 0) ?></small></article><?php endforeach; endif; ?></div>
</section>
<?php workspace_close(); ?>
