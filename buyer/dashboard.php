<?php
/** Buyer dashboard: sourcing attention, open commitments and purchase outcomes. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['buyer']);
$summaryWindow = workspace_summary_window();
$userScope = ['user' => $user['id']];
$periodScope = ['user' => $user['id'], 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];

$offers = fetch_one('SELECT SUM(status="countered") AS countered, SUM(status="pending") AS pending FROM offers WHERE buyer_id=:user', $userScope);
$orders = fetch_one('SELECT COUNT(*) AS count FROM orders WHERE buyer_id=:user AND status NOT IN ("completed","cancelled")', $userScope);
$purchases = fetch_one(
    'SELECT COUNT(*) AS count, COALESCE(SUM(total_amount),0) AS total FROM orders WHERE buyer_id=:user AND status="completed" AND completed_at >= :from AND completed_at < :to',
    $periodScope
);
$favorites = fetch_one('SELECT COUNT(*) AS count FROM favorites WHERE buyer_id=:user', $userScope);
$marketSupply = fetch_one('SELECT COUNT(*) AS count FROM produce_listings WHERE status="active" AND quantity_available > 0');
$supportAttention = support_desk_dashboard_attention($user);

$counteredCount = (int) ($offers['countered'] ?? 0);
$pendingCount = (int) ($offers['pending'] ?? 0);
$activeOrderCount = (int) ($orders['count'] ?? 0);
$focus = $counteredCount > 0
    ? ['Review revised supplier terms', $counteredCount . ' countered offer' . ($counteredCount === 1 ? '' : 's') . ' need a buying decision.', 'buyer/offers.php', 'Review counteroffers']
    : ($activeOrderCount > 0
        ? ['Track active purchase commitments', 'Review order status and recorded fulfilment details before the next handoff.', 'orders.php', 'Review orders']
        : ['Compare available produce', 'Filter current supply by origin, grade, quantity and expected price before making an offer.', 'marketplace/index.php', 'Browse produce']);

workspace_open('Buyer dashboard', 'dashboard', ['focus' => $focus]);
render_status_cards([
    ['label' => 'Counteroffers', 'value' => $counteredCount, 'detail' => 'revised supplier terms to assess', 'scope' => 'Needs action', 'tone' => $counteredCount > 0 ? 'attention' : 'positive'],
    ['label' => 'Offers awaiting reply', 'value' => $pendingCount, 'detail' => 'submitted terms still open', 'scope' => 'Live'],
    ['label' => 'Active orders', 'value' => $activeOrderCount, 'detail' => 'purchase commitments not yet closed', 'scope' => 'Live'],
    ['label' => 'Saved listings', 'value' => (int) ($favorites['count'] ?? 0), 'detail' => 'shortlisted supply for comparison', 'scope' => 'Live'],
    ['label' => 'Available supply', 'value' => (int) ($marketSupply['count'] ?? 0), 'detail' => 'active marketplace options', 'scope' => 'Market'],
    ['label' => 'Completed purchases', 'value' => (int) ($purchases['count'] ?? 0), 'detail' => 'orders closed in this period', 'scope' => 'Period'],
    ['label' => 'Purchase value', 'value' => 'Rs. ' . number_format((float) ($purchases['total'] ?? 0), 0), 'detail' => 'completed order value in this period', 'scope' => 'Period'],
    ['label' => 'Support requests', 'value' => (int) $supportAttention['requester_open'], 'detail' => $supportAttention['available'] ? 'open local support records' : 'support register unavailable', 'scope' => 'Live', 'tone' => (int) $supportAttention['requester_open'] > 0 ? 'attention' : 'positive'],
], $summaryWindow, ['heading' => 'Sourcing and commitment position']);

$recentOffers = fetch_all(
    'SELECT o.quantity,o.offered_price,o.status,o.created_at,pl.title,u.full_name AS farmer_name FROM offers o JOIN produce_listings pl ON pl.id=o.listing_id JOIN users u ON u.id=o.farmer_id WHERE o.buyer_id=:user ORDER BY FIELD(o.status,"countered","pending","accepted","rejected","withdrawn","expired"),o.created_at DESC LIMIT 6',
    $userScope
);
?>
<div class="dashboard-content-grid">
    <section class="workspace-section dashboard-panel dashboard-records" aria-labelledby="buyer-offers-title">
        <div class="workspace-section-header">
            <div><p class="desk-kicker">Sourcing queue</p><h2 id="buyer-offers-title">Recent purchase offers</h2><p>Countered and pending terms appear before closed decisions.</p></div>
            <a class="button button-outline" href="<?= e(app_url('buyer/offers.php')) ?>">View all offers</a>
        </div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Produce</th><th>Farmer</th><th>Quantity</th><th>Your offer</th><th>Status</th></tr></thead>
            <tbody><?php if ($recentOffers === []): ?><tr><td colspan="5">No purchase offers yet. Compare current supply before starting a supplier conversation.</td></tr><?php else: foreach ($recentOffers as $offer): ?><tr><td><?= e($offer['title']) ?></td><td><?= e($offer['farmer_name']) ?></td><td><?= number_format((float) $offer['quantity'], 0) ?> kg</td><td>Rs. <?= number_format((float) $offer['offered_price'], 0) ?>/kg</td><td><span class="status-pill <?= e($offer['status']) ?>"><?= e(ucfirst($offer['status'])) ?></span></td></tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
    <?php render_dashboard_shortcuts('buyer', 'Make the next sourcing decision', 'Move from market comparison to documented supplier terms.'); ?>
</div>

<section class="workspace-section dashboard-readiness" aria-labelledby="buyer-readiness-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Procurement discipline</p><h2 id="buyer-readiness-title">Keep each decision traceable</h2><p>Use current records to move from discovery through commitment.</p></div></div>
    <div class="dashboard-checklist">
        <article><span class="check-state <?= (int) ($favorites['count'] ?? 0) > 0 ? 'is-ready' : 'is-clear' ?>"><?= (int) ($favorites['count'] ?? 0) > 0 ? 'Shortlisted' : 'Open' ?></span><h3>Compare supply</h3><p>Review origin, grade, quantity and expected price before submitting terms.</p><a href="<?= e(app_url('marketplace/index.php')) ?>">Open marketplace</a></article>
        <article><span class="check-state <?= $counteredCount > 0 ? 'needs-action' : 'is-clear' ?>"><?= $counteredCount > 0 ? 'Decision needed' : 'Clear' ?></span><h3>Resolve terms</h3><p><?= $counteredCount > 0 ? 'A supplier has revised one or more offers.' : 'No countered offers currently need a response.' ?></p><a href="<?= e(app_url('buyer/offers.php')) ?>">Review offer register</a></article>
        <article><span class="check-state <?= $activeOrderCount > 0 ? 'is-ready' : 'is-clear' ?>"><?= $activeOrderCount > 0 ? 'In fulfilment' : 'No active order' ?></span><h3>Track commitments</h3><p>Use the order record as the shared reference after commercial terms are accepted.</p><a href="<?= e(app_url('orders.php')) ?>">Open purchase orders</a></article>
    </div>
</section>
<?php workspace_close(); ?>
