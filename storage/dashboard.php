<?php
/** Storage-provider dashboard: capacity, booking decisions and active commitments. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['storage_provider']);
$provider = fetch_one('SELECT id,business_name,verified_at FROM storage_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]);
$providerId = (int) ($provider['id'] ?? 0);
$summaryWindow = workspace_summary_window();
$providerScope = ['provider' => $providerId];
$periodScope = ['provider' => $providerId, 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];

$capacity = ['facilities' => 0, 'active_facilities' => 0, 'total' => 0, 'available' => 0];
$bookings = ['pending' => 0, 'active' => 0];
$completed = ['count' => 0, 'revenue' => 0];
$recent = [];
$supportAttention = support_desk_dashboard_attention($user);

if ($providerId > 0) {
    $capacity = fetch_one(
        'SELECT COUNT(*) AS facilities,SUM(status="active") AS active_facilities,COALESCE(SUM(CASE WHEN status="active" THEN total_capacity_kg ELSE 0 END),0) AS total,COALESCE(SUM(CASE WHEN status="active" THEN available_capacity_kg ELSE 0 END),0) AS available FROM storage_facilities WHERE provider_id=:provider',
        $providerScope
    ) ?? $capacity;
    $bookings = fetch_one(
        'SELECT SUM(sb.status="requested") AS pending,SUM(sb.status IN ("approved","active")) AS active FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider',
        $providerScope
    ) ?? $bookings;
    $completed = fetch_one(
        'SELECT COUNT(*) AS count,COALESCE(SUM(sb.estimated_cost),0) AS revenue FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider AND sb.status="completed" AND sb.created_at >= :from AND sb.created_at < :to',
        $periodScope
    ) ?? $completed;
    $recent = fetch_all(
        'SELECT sb.id,sb.reference_code,sb.quantity_kg,sb.start_date,sb.end_date,sb.status,u.full_name,sf.name FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id JOIN users u ON u.id=sb.farmer_id WHERE sf.provider_id=:provider ORDER BY FIELD(sb.status,"requested","approved","active","completed","rejected","cancelled"),sb.created_at DESC LIMIT 8',
        $providerScope
    );
}

$pendingCount = (int) ($bookings['pending'] ?? 0);
$activeFacilityCount = (int) ($capacity['active_facilities'] ?? 0);
$totalCapacity = (float) ($capacity['total'] ?? 0);
$availableCapacity = (float) ($capacity['available'] ?? 0);
$occupiedCapacity = max(0, $totalCapacity - $availableCapacity);
$utilization = $totalCapacity > 0 ? (int) round(($occupiedCapacity / $totalCapacity) * 100) : 0;
$focus = $providerId === 0
    ? ['Complete the storage profile', 'Add verified organisation details and a real facility before accepting booking work.', 'storage/facilities.php', 'Set up facilities']
    : ($pendingCount > 0
        ? ['Review incoming booking requests', $pendingCount . ' request' . ($pendingCount === 1 ? '' : 's') . ' need a capacity decision.', 'storage/dashboard.php#storage-booking-queue', 'Review requests']
        : ($activeFacilityCount === 0
            ? ['Publish verified capacity', 'Activate at least one complete facility record before farmers can request storage.', 'storage/facilities.php', 'Review facilities']
            : ['Keep capacity current', 'Confirm room availability, rates and compatible produce before the next request arrives.', 'storage/facilities.php', 'Review capacity']));

workspace_open('Storage provider dashboard', 'dashboard', ['focus' => $focus]);
render_status_cards([
    ['label' => 'Active facilities', 'value' => $activeFacilityCount, 'detail' => 'facilities currently visible for booking', 'scope' => 'Live', 'tone' => $activeFacilityCount > 0 ? 'positive' : 'attention'],
    ['label' => 'Available capacity', 'value' => number_format($availableCapacity, 0) . ' kg', 'detail' => 'current bookable capacity', 'scope' => 'Live'],
    ['label' => 'Occupied capacity', 'value' => number_format($occupiedCapacity, 0) . ' kg', 'detail' => $utilization . '% of active listed capacity', 'scope' => 'Live'],
    ['label' => 'Requests to review', 'value' => $pendingCount, 'detail' => 'bookings awaiting a decision', 'scope' => 'Needs action', 'tone' => $pendingCount > 0 ? 'attention' : 'positive'],
    ['label' => 'Active bookings', 'value' => (int) ($bookings['active'] ?? 0), 'detail' => 'approved or currently in storage', 'scope' => 'Live'],
    ['label' => 'Completed bookings', 'value' => (int) ($completed['count'] ?? 0), 'detail' => 'bookings closed in this period', 'scope' => 'Period'],
    ['label' => 'Booking revenue', 'value' => 'Rs. ' . number_format((float) ($completed['revenue'] ?? 0), 0), 'detail' => 'completed booking estimates', 'scope' => 'Period'],
    ['label' => 'Support attention', 'value' => (int) $supportAttention['queue_open'], 'detail' => $supportAttention['available'] ? 'routed local requests' : 'support register unavailable', 'scope' => 'Live', 'tone' => (int) $supportAttention['queue_open'] > 0 ? 'attention' : 'positive'],
], $summaryWindow, ['heading' => 'Capacity and booking position']);
?>
<div class="dashboard-content-grid">
    <section class="workspace-section dashboard-panel dashboard-records" id="storage-booking-queue" aria-labelledby="storage-bookings-title">
        <div class="workspace-section-header">
            <div><p class="desk-kicker">Capacity queue</p><h2 id="storage-bookings-title">Incoming booking requests</h2><p>Requests needing a decision appear before active and completed bookings.</p></div>
            <a class="button button-outline" href="<?= e(app_url('storage/facilities.php')) ?>">Manage facilities</a>
        </div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Reference</th><th>Facility</th><th>Farmer</th><th>Dates / quantity</th><th>Status</th><th>Next action</th></tr></thead>
            <tbody><?php if ($recent === []): ?><tr><td colspan="6"><?= $providerId === 0 ? 'Complete the provider and facility setup to begin receiving storage requests.' : 'No storage requests have arrived.' ?></td></tr><?php else: foreach ($recent as $booking): ?><tr>
                <td><?= e($booking['reference_code']) ?></td><td><?= e($booking['name']) ?></td><td><?= e($booking['full_name']) ?></td>
                <td><?= e(date('j M', strtotime($booking['start_date']))) ?>–<?= e(date('j M Y', strtotime($booking['end_date']))) ?><br><span class="muted"><?= number_format((float) $booking['quantity_kg'], 0) ?> kg</span></td>
                <td><span class="status-pill <?= e($booking['status']) ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
                <td><div class="dashboard-row-actions"><?php if ($booking['status'] === 'requested'): ?>
                    <form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="approved"><button class="button button-primary" type="submit">Approve</button></form>
                    <form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="button button-outline" type="submit">Reject</button></form>
                <?php elseif ($booking['status'] === 'approved'): ?>
                    <form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="active"><button class="button button-primary" type="submit">Start storage</button></form>
                <?php elseif ($booking['status'] === 'active'): ?>
                    <form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="completed"><button class="button button-primary" type="submit">Complete</button></form>
                <?php else: ?><span class="muted">No action required</span><?php endif; ?></div></td>
            </tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
    <?php render_dashboard_shortcuts('storage_provider', 'Operate storage capacity', 'Keep facilities, available space and booking decisions aligned.'); ?>
</div>

<section class="workspace-section dashboard-readiness" aria-labelledby="storage-readiness-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Service readiness</p><h2 id="storage-readiness-title">Capacity checks</h2><p>Only active, accountable facility records should accept bookings.</p></div></div>
    <div class="dashboard-checklist">
        <article><span class="check-state <?= $providerId > 0 ? 'is-ready' : 'needs-action' ?>"><?= $providerId > 0 ? 'Recorded' : 'Needs setup' ?></span><h3>Provider profile</h3><p><?= $providerId > 0 ? e((string) ($provider['business_name'] ?: 'Provider profile exists; complete any missing business details.')) : 'No storage provider profile is attached to this account.' ?></p><a href="<?= e(app_url('account/profile.php')) ?>">Review business profile</a></article>
        <article><span class="check-state <?= $activeFacilityCount > 0 ? 'is-ready' : 'needs-action' ?>"><?= $activeFacilityCount > 0 ? 'Bookable' : 'Needs action' ?></span><h3>Facility availability</h3><p><?= $activeFacilityCount > 0 ? number_format($availableCapacity, 0) . ' kg is currently available across active facilities.' : 'Add and activate a complete facility record before taking requests.' ?></p><a href="<?= e(app_url('storage/facilities.php')) ?>">Review facilities</a></article>
        <article><span class="check-state <?= $pendingCount > 0 ? 'needs-action' : 'is-clear' ?>"><?= $pendingCount > 0 ? 'Decision needed' : 'Clear' ?></span><h3>Booking decisions</h3><p><?= $pendingCount > 0 ? 'Confirm capacity before approving each incoming request.' : 'No booking request is waiting for a response.' ?></p><a href="#storage-booking-queue">Open booking queue</a></article>
    </div>
</section>
<?php workspace_close(); ?>
