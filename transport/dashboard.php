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
$earnings = ['total' => 0];
$recent = [];
$supportAttention = support_desk_dashboard_attention($user);

if ($providerId > 0) {
    $vehicles = fetch_one('SELECT COUNT(*) AS count FROM vehicles WHERE provider_id=:provider AND status="available"', ['provider' => $providerId]) ?? $vehicles;
    $pending = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="requested" AND created_at >= :from AND created_at < :to', $range) ?? $pending;
    $active = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status IN("accepted","driver_assigned","pickup_scheduled","picked_up","in_transit") AND created_at >= :from AND created_at < :to', $range) ?? $active;
    $delivered = fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="delivered" AND created_at >= :from AND created_at < :to', $range) ?? $delivered;
    $earnings = fetch_one('SELECT COALESCE(SUM(COALESCE(final_price,estimated_price)),0) AS total FROM transport_requests WHERE provider_id=:provider AND status="delivered" AND created_at >= :from AND created_at < :to', $range) ?? $earnings;
    $recent = fetch_all('SELECT tr.*,u.full_name FROM transport_requests tr JOIN users u ON u.id=tr.farmer_id WHERE tr.provider_id=:provider ORDER BY tr.created_at DESC LIMIT 20', ['provider' => $providerId]);
}
$eligibleVehicles = $providerId > 0 ? fetch_all('SELECT id,vehicle_type,registration_number,capacity_kg,is_refrigerated FROM vehicles WHERE provider_id=:provider AND status="available" ORDER BY capacity_kg DESC',['provider'=>$providerId]) : [];

workspace_open('Transport provider dashboard', 'dashboard');
render_status_cards([
    ['label' => 'Available vehicles', 'value' => (int) $vehicles['count'], 'detail' => 'ready in your listed fleet'],
    ['label' => 'New requests', 'value' => (int) $pending['count'], 'detail' => 'awaiting a response'],
    ['label' => 'Support attention', 'value' => $supportAttention['queue_open'], 'detail' => $supportAttention['available'] ? 'routed local requests' : 'migration needed'],
    ['label' => 'Active trips', 'value' => (int) $active['count'], 'detail' => 'accepted through transit'],
    ['label' => 'Delivered', 'value' => (int) $delivered['count'], 'detail' => 'recorded delivery milestones'],
    ['label' => 'Earnings', 'value' => 'Rs. ' . number_format((float) $earnings['total'], 0), 'detail' => 'delivered trip value'],
], $summaryWindow);
?>
<?php if ($providerId === 0): ?><section class="workspace-focus"><span>Account setup pending</span><h2>No transport provider profile has been added for this account.</h2><p>This retained development credential has no fictional fleet or dispatch operation. Add verified organisation details and real vehicles before using the account for live transport work.</p><a class="button button-primary" href="<?= e(app_url('transport/fleet.php')) ?>">Add fleet details</a></section><?php endif; ?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Transport requests</h2><p>Provider quotes, vehicle and driver assignments, and each dispatch milestone are retained with the request.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Farmer / produce</th><th>Pickup</th><th>Assignment</th><th>Status</th><th>Update</th></tr></thead><tbody><?php if ($recent === []): ?><tr><td colspan="6"><?= $providerId === 0 ? 'No transport provider profile is configured for this account.' : 'No transport requests have arrived.' ?></td></tr><?php else: foreach ($recent as $request): ?><tr><td><?= e($request['reference_code']) ?></td><td><?= e($request['full_name']) ?><br><?= e($request['produce_description']) ?> · <?= number_format((float) $request['quantity_kg'], 0) ?> kg</td><td><?= e(date('j M Y', strtotime($request['pickup_date']))) ?></td><td><?= $request['driver_name']?e($request['driver_name'].' · '.$request['driver_phone']):'<span class="muted">Not assigned</span>' ?><br><?= $request['estimated_price']?'Rs. '.number_format((float)$request['estimated_price'],2):'' ?></td><td><span class="status-pill <?= e($request['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $request['status']))) ?></span></td><td><?php $next=match($request['status']){'requested'=>'accepted','accepted'=>'driver_assigned','driver_assigned'=>'pickup_scheduled','pickup_scheduled'=>'picked_up','picked_up'=>'in_transit','in_transit'=>'delivered',default=>''}; if($next!==''):?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form class="compact-action"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="status" value="<?= e($next) ?>"><?php if($next==='accepted'):?><input name="estimated_price" type="number" min="1" step=".01" required placeholder="Estimated price (Rs.)"><?php elseif($next==='driver_assigned'):?><select name="vehicle_id" required><option value="">Choose vehicle</option><?php foreach($eligibleVehicles as $vehicle):if((float)$vehicle['capacity_kg']<(float)$request['quantity_kg']||((int)$request['requires_refrigeration']===1&&(int)$vehicle['is_refrigerated']!==1))continue;?><option value="<?= (int)$vehicle['id'] ?>"><?= e($vehicle['vehicle_type'].' · '.$vehicle['registration_number']) ?></option><?php endforeach;?></select><input name="driver_name" required maxlength="120" placeholder="Driver name"><input name="driver_phone" required maxlength="30" placeholder="Driver phone"><?php endif;?><input name="provider_note" maxlength="500" placeholder="Optional note"><button class="button button-primary" type="submit"><?= e(order_status_label($next)) ?></button><span data-form-feedback></span></form><?php if(in_array($request['status'],['requested','accepted','driver_assigned','pickup_scheduled'],true)):?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="button button-outline" type="submit">Cancel</button></form><?php endif;else:?><span class="muted">No action</span><?php endif;?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
