<?php
/** Orchard Ledger storage-provider dashboard: a retained credential with no fictional facility safely opens an empty operational workspace. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['storage_provider']);
$provider = fetch_one('SELECT id FROM storage_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]);
$providerId = (int) ($provider['id'] ?? 0);
$summaryWindow = workspace_summary_window(); $range = ['provider' => $providerId, 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];
$summary = ['total' => 0, 'occupied' => 0, 'available' => 0];
$pending = ['count' => 0];
$active = ['count' => 0];
$recent = [];
$supportAttention = support_desk_dashboard_attention($user);

if ($providerId > 0) {
    $summary = fetch_one('SELECT COALESCE(SUM(total_capacity_kg),0) AS total,COALESCE(SUM(total_capacity_kg-available_capacity_kg),0) AS occupied,COALESCE(SUM(available_capacity_kg),0) AS available FROM storage_facilities WHERE provider_id=:provider', ['provider' => $providerId]) ?? $summary;
    $pending = fetch_one('SELECT COUNT(*) AS count FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider AND sb.status="requested" AND sb.created_at >= :from AND sb.created_at < :to', $range) ?? $pending;
    $active = fetch_one('SELECT COUNT(*) AS count FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider AND sb.status IN("approved","active") AND sb.created_at >= :from AND sb.created_at < :to', $range) ?? $active;
    $recent = fetch_all('SELECT sb.id,sb.reference_code,sb.quantity_kg,sb.start_date,sb.end_date,sb.status,u.full_name,sf.name FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id JOIN users u ON u.id=sb.farmer_id WHERE sf.provider_id=:provider ORDER BY sb.created_at DESC LIMIT 6', ['provider' => $providerId]);
}

workspace_open('Storage provider dashboard', 'dashboard');
render_status_cards([
    ['label' => 'Total capacity', 'value' => number_format((float) $summary['total'], 0) . ' kg', 'detail' => 'across listed facilities'],
    ['label' => 'Occupied capacity', 'value' => number_format((float) $summary['occupied'], 0) . ' kg', 'detail' => 'calculated from current availability'],
    ['label' => 'Pending bookings', 'value' => (int) $pending['count'], 'detail' => 'awaiting your review'],
    ['label' => 'Support attention', 'value' => $supportAttention['queue_open'], 'detail' => $supportAttention['available'] ? 'routed local requests' : 'migration needed'],
    ['label' => 'Active bookings', 'value' => (int) $active['count'], 'detail' => 'approved or in storage'],
], $summaryWindow);
?>
<?php if ($providerId === 0): ?><section class="workspace-focus"><span>Account setup pending</span><h2>No facility profile has been added for this account.</h2><p>This retained development credential has no fictional storage operation. Add verified organisation details and a real facility before using the account for live capacity or booking work.</p><a class="button button-primary" href="<?= e(app_url('storage/facilities.php')) ?>">Add facility</a></section><?php endif; ?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Incoming booking requests</h2><p>Capacity is only committed when the request moves beyond review.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Facility</th><th>Farmer</th><th>Quantity</th><th>Status</th><th>Update</th></tr></thead><tbody><?php if ($recent === []): ?><tr><td colspan="6"><?= $providerId === 0 ? 'No facility profile is configured for this account.' : 'No storage requests have arrived.' ?></td></tr><?php else: foreach ($recent as $booking): ?><tr><td><?= e($booking['reference_code']) ?></td><td><?= e($booking['name']) ?></td><td><?= e($booking['full_name']) ?></td><td><?= number_format((float) $booking['quantity_kg'], 0) ?> kg</td><td><span class="status-pill <?= e($booking['status']) ?>"><?= e(ucfirst($booking['status'])) ?></span></td><td><?php if ($booking['status'] === 'requested'): ?><form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="approved"><button class="button button-primary" type="submit">Approve</button></form><form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" style="margin-top:6px" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="rejected"><button class="button button-outline" type="submit">Reject</button></form><?php elseif ($booking['status'] === 'approved'): ?><form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="active"><button class="button button-primary" type="submit">Activate</button></form><?php elseif ($booking['status'] === 'active'): ?><form action="<?= e(app_url('ajax/storage/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="status" value="completed"><button class="button button-primary" type="submit">Complete</button></form><?php else: ?><span class="muted">No action</span><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
