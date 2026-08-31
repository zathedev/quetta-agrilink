<?php
/** Farmer dashboard: current supply, commercial attention and fulfilment readiness. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['farmer']);
$summaryWindow = workspace_summary_window();
$userScope = ['user' => $user['id']];
$periodScope = ['user' => $user['id'], 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];

$supply = fetch_one(
    'SELECT SUM(status="active") AS listings, COALESCE(SUM(CASE WHEN status="active" THEN quantity_available ELSE 0 END),0) AS quantity FROM produce_listings WHERE farmer_id=:user',
    $userScope
);
$offers = fetch_one('SELECT COUNT(*) AS count FROM offers WHERE farmer_id=:user AND status IN ("pending","countered")', $userScope);
$orders = fetch_one('SELECT COUNT(*) AS count FROM orders WHERE farmer_id=:user AND status NOT IN ("completed","cancelled")', $userScope);
$bookings = fetch_one('SELECT COUNT(*) AS count FROM storage_bookings WHERE farmer_id=:user AND status IN ("requested","approved","active")', $userScope);
$requests = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE farmer_id=:user AND status NOT IN ("delivered","cancelled")', $userScope);
$sales = fetch_one(
    'SELECT COUNT(*) AS count, COALESCE(SUM(total_amount),0) AS total FROM orders WHERE farmer_id=:user AND status="completed" AND completed_at >= :from AND completed_at < :to',
    $periodScope
);

$offerCount = (int) ($offers['count'] ?? 0);
$listingCount = (int) ($supply['listings'] ?? 0);
$focus = $offerCount > 0
    ? ['Review buyer offers', $offerCount . ' offer' . ($offerCount === 1 ? '' : 's') . ' need a commercial response or revised terms.', 'farmer/offers.php', 'Review offers']
    : ($listingCount === 0
        ? ['Publish current produce', 'Add harvest availability with grade, quantity and expected price so buyers can compare it.', 'farmer/listings.php', 'Publish produce']
        : ['Keep availability accurate', 'Review visible quantities and harvest terms before the next buyer conversation.', 'farmer/listings.php', 'Review listings']);

workspace_open('Farmer dashboard', 'dashboard', ['focus' => $focus]);
render_status_cards([
    ['label' => 'Active listings', 'value' => $listingCount, 'detail' => 'produce records visible to buyers', 'scope' => 'Live', 'tone' => $listingCount > 0 ? 'positive' : 'attention'],
    ['label' => 'Available produce', 'value' => number_format((float) ($supply['quantity'] ?? 0), 0) . ' kg', 'detail' => 'remaining quantity across active listings', 'scope' => 'Live'],
    ['label' => 'Offers to review', 'value' => $offerCount, 'detail' => 'pending or countered buyer terms', 'scope' => 'Needs action', 'tone' => $offerCount > 0 ? 'attention' : 'positive'],
    ['label' => 'Active orders', 'value' => (int) ($orders['count'] ?? 0), 'detail' => 'confirmed trades not yet closed', 'scope' => 'Live'],
    ['label' => 'Storage bookings', 'value' => (int) ($bookings['count'] ?? 0), 'detail' => 'requested, approved or active', 'scope' => 'Live'],
    ['label' => 'Transport requests', 'value' => (int) ($requests['count'] ?? 0), 'detail' => 'loads not delivered or cancelled', 'scope' => 'Live'],
    ['label' => 'Completed sales', 'value' => (int) ($sales['count'] ?? 0), 'detail' => 'orders closed in this period', 'scope' => 'Period'],
    ['label' => 'Sales value', 'value' => 'Rs. ' . number_format((float) ($sales['total'] ?? 0), 0), 'detail' => 'completed order value in this period', 'scope' => 'Period'],
], $summaryWindow, ['heading' => 'Harvest and trade position']);

$recentOffers = fetch_all(
    'SELECT o.id,o.quantity,o.offered_price,o.status,o.created_at,pl.title,u.full_name AS buyer_name FROM offers o JOIN produce_listings pl ON pl.id=o.listing_id JOIN users u ON u.id=o.buyer_id WHERE o.farmer_id=:user ORDER BY FIELD(o.status,"pending","countered","accepted","rejected","withdrawn","expired"),o.created_at DESC LIMIT 6',
    $userScope
);
?>
<div class="dashboard-content-grid">
    <section class="workspace-section dashboard-panel dashboard-records" aria-labelledby="farmer-offers-title">
        <div class="workspace-section-header">
            <div><p class="desk-kicker">Commercial queue</p><h2 id="farmer-offers-title">Recent buyer offers</h2><p>Open terms appear first so the next response is clear.</p></div>
            <a class="button button-outline" href="<?= e(app_url('farmer/offers.php')) ?>">View all offers</a>
        </div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Listing</th><th>Buyer</th><th>Quantity</th><th>Offer</th><th>Status</th></tr></thead>
            <tbody><?php if ($recentOffers === []): ?><tr><td colspan="5">No buyer offers yet. Keep listing quantities and terms current while buyers compare supply.</td></tr><?php else: foreach ($recentOffers as $offer): ?><tr><td><?= e($offer['title']) ?></td><td><?= e($offer['buyer_name']) ?></td><td><?= number_format((float) $offer['quantity'], 0) ?> kg</td><td>Rs. <?= number_format((float) $offer['offered_price'], 0) ?>/kg</td><td><span class="status-pill <?= e($offer['status']) ?>"><?= e(ucfirst($offer['status'])) ?></span></td></tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
    <?php render_dashboard_shortcuts('farmer', 'Move the harvest forward', 'Keep supply, buyer terms and post-harvest capacity connected.'); ?>
</div>

<section class="workspace-section dashboard-readiness" aria-labelledby="farmer-readiness-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Fulfilment readiness</p><h2 id="farmer-readiness-title">Prepare the next handoff</h2><p>These checks use current records, not projections.</p></div></div>
    <div class="dashboard-checklist">
        <article><span class="check-state <?= $listingCount > 0 ? 'is-ready' : 'needs-action' ?>"><?= $listingCount > 0 ? 'Ready' : 'Needs action' ?></span><h3>Produce availability</h3><p><?= $listingCount > 0 ? 'At least one listing is visible with available quantity.' : 'Publish produce before buyers can assess the harvest.' ?></p><a href="<?= e(app_url('farmer/listings.php')) ?>">Review produce records</a></article>
        <article><span class="check-state <?= $offerCount > 0 ? 'needs-action' : 'is-clear' ?>"><?= $offerCount > 0 ? 'Review' : 'Clear' ?></span><h3>Buyer terms</h3><p><?= $offerCount > 0 ? 'Open buyer terms need a response before trade can progress.' : 'There are no pending or countered offers.' ?></p><a href="<?= e(app_url('farmer/offers.php')) ?>">Open offer register</a></article>
        <article><span class="check-state <?= ((int) ($bookings['count'] ?? 0) + (int) ($requests['count'] ?? 0)) > 0 ? 'is-ready' : 'is-clear' ?>"><?= ((int) ($bookings['count'] ?? 0) + (int) ($requests['count'] ?? 0)) > 0 ? 'In progress' : 'Not booked' ?></span><h3>Storage and delivery</h3><p>Confirm capacity and pickup only when the harvest and commercial terms are ready.</p><a href="<?= e(app_url('storage/index.php')) ?>">Plan post-harvest services</a></article>
    </div>
</section>
<?php workspace_close(); ?>
