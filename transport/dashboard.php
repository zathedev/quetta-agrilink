<?php
/** Orchard Ledger transport-provider dashboard: a retained credential with no fictional provider safely opens an empty dispatch workspace. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['transport_provider']);
$provider = fetch_one('SELECT id FROM transport_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]);
$providerId = (int) ($provider['id'] ?? 0);
$summaryWindow = workspace_summary_window();
$range = ['provider' => $providerId, 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];
$vehicles = ['count' => 0];
$pending = ['count' => 0];
$active = ['count' => 0];
$delivered = ['count' => 0];
$recent = [];
$supportAttention = support_desk_dashboard_attention($user);

if ($providerId > 0) {
    $vehicles = fetch_one('SELECT COUNT(*) AS count FROM vehicles WHERE provider_id=:provider AND status="available"', ['provider' => $providerId]) ?? $vehicles;
    $pending = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="requested" AND created_at >= :from AND created_at < :to', $range) ?? $pending;
    $active = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status IN("accepted","driver_assigned","pickup_scheduled","picked_up","in_transit") AND created_at >= :from AND created_at < :to', $range) ?? $active;
    $delivered = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="delivered" AND created_at >= :from AND created_at < :to', $range) ?? $delivered;
    $recent = fetch_all('SELECT tr.id,tr.reference_code,tr.produce_description,tr.quantity_kg,tr.pickup_date,tr.status,u.full_name FROM transport_requests tr JOIN users u ON u.id=tr.farmer_id WHERE tr.provider_id=:provider ORDER BY tr.created_at DESC LIMIT 6', ['provider' => $providerId]);
}

workspace_open('Transport provider dashboard', 'dashboard');
render_status_cards([
    ['label' => 'Available vehicles', 'value' => (int) $vehicles['count'], 'detail' => 'ready in your listed fleet'],
    ['label' => 'New requests', 'value' => (int) $pending['count'], 'detail' => 'awaiting a response'],
    ['label' => 'Support attention', 'value' => $supportAttention['queue_open'], 'detail' => $supportAttention['available'] ? 'routed local requests' : 'migration needed'],
    ['label' => 'Active trips', 'value' => (int) $active['count'], 'detail' => 'accepted through transit'],
    ['label' => 'Delivered', 'value' => (int) $delivered['count'], 'detail' => 'recorded delivery milestones'],
], $summaryWindow);
?>
<?php if ($providerId === 0): ?><section class="workspace-focus"><span>Account setup pending</span><h2>No transport provider profile has been added for this account.</h2><p>This retained development credential has no fictional fleet or dispatch operation. Add verified organisation details and real vehicles before using the account for live transport work.</p><a class="button button-primary" href="<?= e(app_url('transport/fleet.php')) ?>">Add fleet details</a></section><?php endif; ?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Transport requests</h2><p>Requests remain in a provider-scoped view until your team responds.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Farmer</th><th>Produce</th><th>Pickup</th><th>Status</th><th>Update</th></tr></thead><tbody><?php if ($recent === []): ?><tr><td colspan="6"><?= $providerId === 0 ? 'No transport provider profile is configured for this account.' : 'No transport requests have arrived.' ?></td></tr><?php else: foreach ($recent as $request): ?><tr><td><?= e($request['reference_code']) ?></td><td><?= e($request['full_name']) ?></td><td><?= e($request['produce_description']) ?> · <?= number_format((float) $request['quantity_kg'], 0) ?> kg</td><td><?= e(date('j M Y', strtotime($request['pickup_date']))) ?></td><td><span class="status-pill <?= e($request['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $request['status']))) ?></span></td><td><?php if ($request['status'] === 'requested'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="accepted"><button class="button button-primary" type="submit">Accept</button></form><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" style="margin-top:6px" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="button button-outline" type="submit">Decline</button></form><?php elseif ($request['status'] === 'accepted'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="driver_assigned"><button class="button button-primary" type="submit">Assign driver</button></form><?php elseif ($request['status'] === 'driver_assigned'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="pickup_scheduled"><button class="button button-primary" type="submit">Schedule pickup</button></form><?php elseif ($request['status'] === 'pickup_scheduled'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="picked_up"><button class="button button-primary" type="submit">Picked up</button></form><?php elseif ($request['status'] === 'picked_up'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="in_transit"><button class="button button-primary" type="submit">In transit</button></form><?php elseif ($request['status'] === 'in_transit'): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="delivered"><button class="button button-primary" type="submit">Delivered</button></form><?php else: ?><span class="muted">No action</span><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
