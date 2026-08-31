<?php
/** Transport-provider dashboard: dispatch queue, fleet readiness and delivery outcomes. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['transport_provider']);
$provider = fetch_one('SELECT id,company_name,verified_at FROM transport_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]);
$providerId = (int) ($provider['id'] ?? 0);
$summaryWindow = workspace_summary_window();
$providerScope = ['provider' => $providerId];
$periodScope = ['provider' => $providerId, 'from' => $summaryWindow['from'], 'to' => $summaryWindow['to']];

$fleet = ['total' => 0, 'available' => 0, 'busy' => 0, 'maintenance' => 0];
$requests = ['pending' => 0, 'active' => 0];
$delivered = ['count' => 0, 'earnings' => 0];
$serviceAreas = ['count' => 0];
$recent = [];
$eligibleVehicles = [];
$supportAttention = support_desk_dashboard_attention($user);

if ($providerId > 0) {
    $fleet = fetch_one('SELECT COUNT(*) AS total,SUM(status="available") AS available,SUM(status="busy") AS busy,SUM(status="maintenance") AS maintenance FROM vehicles WHERE provider_id=:provider', $providerScope) ?? $fleet;
    $requests = fetch_one('SELECT SUM(status="requested") AS pending,SUM(status IN ("accepted","driver_assigned","pickup_scheduled","picked_up","in_transit")) AS active FROM transport_requests WHERE provider_id=:provider', $providerScope) ?? $requests;
    $delivered = fetch_one(
        'SELECT COUNT(*) AS count,COALESCE(SUM(COALESCE(final_price,estimated_price)),0) AS earnings FROM transport_requests WHERE provider_id=:provider AND status="delivered" AND created_at >= :from AND created_at < :to',
        $periodScope
    ) ?? $delivered;
    $serviceAreas = fetch_one('SELECT COUNT(*) AS count FROM transport_service_areas WHERE provider_id=:provider', $providerScope) ?? $serviceAreas;
    $recent = fetch_all(
        'SELECT tr.*,u.full_name FROM transport_requests tr JOIN users u ON u.id=tr.farmer_id WHERE tr.provider_id=:provider ORDER BY FIELD(tr.status,"requested","accepted","driver_assigned","pickup_scheduled","picked_up","in_transit","delivered","cancelled"),tr.pickup_date ASC,tr.created_at DESC LIMIT 8',
        $providerScope
    );
    $eligibleVehicles = fetch_all('SELECT id,vehicle_type,registration_number,capacity_kg,is_refrigerated FROM vehicles WHERE provider_id=:provider AND status="available" ORDER BY capacity_kg DESC', $providerScope);
}

$pendingCount = (int) ($requests['pending'] ?? 0);
$activeTripCount = (int) ($requests['active'] ?? 0);
$availableVehicleCount = (int) ($fleet['available'] ?? 0);
$focus = $providerId === 0
    ? ['Complete the transport profile', 'Add verified organisation details, service areas and real vehicles before accepting dispatch work.', 'transport/fleet.php', 'Set up fleet']
    : ($pendingCount > 0
        ? ['Quote incoming transport requests', $pendingCount . ' request' . ($pendingCount === 1 ? '' : 's') . ' need a response before dispatch can begin.', 'transport/dashboard.php#transport-request-queue', 'Review requests']
        : ($activeTripCount > 0
            ? ['Advance active delivery milestones', 'Keep driver assignment, pickup and delivery status current for every active load.', 'transport/dashboard.php#transport-request-queue', 'Review active trips']
            : ($availableVehicleCount === 0
                ? ['Restore fleet availability', 'No vehicle is currently marked available for a new dispatch.', 'transport/fleet.php', 'Review fleet']
                : ['Keep dispatch capacity current', 'Confirm fleet availability and service coverage before the next load request.', 'transport/fleet.php', 'Review fleet'])));

workspace_open('Transport provider dashboard', 'dashboard', ['focus' => $focus]);
render_status_cards([
    ['label' => 'Available vehicles', 'value' => $availableVehicleCount, 'detail' => 'vehicles ready for assignment', 'scope' => 'Live', 'tone' => $availableVehicleCount > 0 ? 'positive' : 'attention'],
    ['label' => 'Busy vehicles', 'value' => (int) ($fleet['busy'] ?? 0), 'detail' => 'vehicles allocated to current work', 'scope' => 'Live'],
    ['label' => 'Service areas', 'value' => (int) ($serviceAreas['count'] ?? 0), 'detail' => 'recorded transport coverage areas', 'scope' => 'Live'],
    ['label' => 'Requests to quote', 'value' => $pendingCount, 'detail' => 'incoming loads awaiting response', 'scope' => 'Needs action', 'tone' => $pendingCount > 0 ? 'attention' : 'positive'],
    ['label' => 'Active trips', 'value' => $activeTripCount, 'detail' => 'accepted through in-transit loads', 'scope' => 'Live'],
    ['label' => 'Delivered loads', 'value' => (int) ($delivered['count'] ?? 0), 'detail' => 'deliveries closed in this period', 'scope' => 'Period'],
    ['label' => 'Delivery earnings', 'value' => 'Rs. ' . number_format((float) ($delivered['earnings'] ?? 0), 0), 'detail' => 'recorded delivered-trip value', 'scope' => 'Period'],
    ['label' => 'Support attention', 'value' => (int) $supportAttention['queue_open'], 'detail' => $supportAttention['available'] ? 'routed local requests' : 'support register unavailable', 'scope' => 'Live', 'tone' => (int) $supportAttention['queue_open'] > 0 ? 'attention' : 'positive'],
], $summaryWindow, ['heading' => 'Fleet and dispatch position']);
?>
<div class="dashboard-content-grid">
    <section class="workspace-section dashboard-panel dashboard-records" id="transport-request-queue" aria-labelledby="transport-requests-title">
        <div class="workspace-section-header">
            <div><p class="desk-kicker">Dispatch queue</p><h2 id="transport-requests-title">Transport requests</h2><p>Requests needing a quote or milestone update appear first.</p></div>
            <a class="button button-outline" href="<?= e(app_url('transport/fleet.php')) ?>">Manage fleet</a>
        </div>
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th>Reference</th><th>Farmer / load</th><th>Pickup</th><th>Assignment</th><th>Status</th><th>Next action</th></tr></thead>
            <tbody><?php if ($recent === []): ?><tr><td colspan="6"><?= $providerId === 0 ? 'Complete the provider and fleet setup to begin receiving transport requests.' : 'No transport requests have arrived.' ?></td></tr><?php else: foreach ($recent as $request): ?><tr>
                <td><?= e($request['reference_code']) ?></td>
                <td><?= e($request['full_name']) ?><br><span class="muted"><?= e($request['produce_description']) ?> · <?= number_format((float) $request['quantity_kg'], 0) ?> kg<?= (int) $request['requires_refrigeration'] === 1 ? ' · refrigerated' : '' ?></span></td>
                <td><?= e(date('j M Y', strtotime($request['pickup_date']))) ?></td>
                <td><?= $request['driver_name'] ? e($request['driver_name'] . ' · ' . $request['driver_phone']) : '<span class="muted">Not assigned</span>' ?><?php if ($request['estimated_price']): ?><br><span class="muted">Rs. <?= number_format((float) $request['estimated_price'], 0) ?></span><?php endif; ?></td>
                <td><span class="status-pill <?= e($request['status']) ?>"><?= e(order_status_label($request['status'])) ?></span></td>
                <td><?php $next = match ($request['status']) { 'requested' => 'accepted', 'accepted' => 'driver_assigned', 'driver_assigned' => 'pickup_scheduled', 'pickup_scheduled' => 'picked_up', 'picked_up' => 'in_transit', 'in_transit' => 'delivered', default => '' }; ?>
                    <?php if ($next !== ''): ?><div class="dashboard-row-actions dashboard-row-actions-stacked"><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form class="compact-action">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="<?= e($next) ?>">
                        <?php if ($next === 'accepted'): ?><input name="estimated_price" type="number" min="1" step=".01" required placeholder="Estimate (Rs.)" aria-label="Estimated transport price in rupees">
                        <?php elseif ($next === 'driver_assigned'): ?><select name="vehicle_id" required aria-label="Eligible vehicle"><option value="">Choose vehicle</option><?php foreach ($eligibleVehicles as $vehicle): if ((float) $vehicle['capacity_kg'] < (float) $request['quantity_kg'] || ((int) $request['requires_refrigeration'] === 1 && (int) $vehicle['is_refrigerated'] !== 1)) continue; ?><option value="<?= (int) $vehicle['id'] ?>"><?= e($vehicle['vehicle_type'] . ' · ' . $vehicle['registration_number']) ?></option><?php endforeach; ?></select><input name="driver_name" required maxlength="120" placeholder="Driver name" aria-label="Driver name"><input name="driver_phone" required maxlength="30" placeholder="Driver phone" aria-label="Driver phone">
                        <?php endif; ?><input name="provider_note" maxlength="500" placeholder="Optional note" aria-label="Optional provider note"><button class="button button-primary" type="submit"><?= e(order_status_label($next)) ?></button><span data-form-feedback></span>
                    </form><?php if (in_array($request['status'], ['requested','accepted','driver_assigned','pickup_scheduled'], true)): ?><form action="<?= e(app_url('ajax/transport/update-status.php')) ?>" method="post" data-ajax-form><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="button button-outline" type="submit">Cancel</button></form><?php endif; ?></div>
                    <?php else: ?><span class="muted">No action required</span><?php endif; ?></td>
            </tr><?php endforeach; endif; ?></tbody>
        </table></div>
    </section>
    <?php render_dashboard_shortcuts('transport_provider', 'Run the dispatch desk', 'Keep requests, vehicle capacity and service coverage aligned.'); ?>
</div>

<section class="workspace-section dashboard-readiness" aria-labelledby="transport-readiness-title">
    <div class="workspace-section-header"><div><p class="desk-kicker">Dispatch readiness</p><h2 id="transport-readiness-title">Prepare for the next load</h2><p>Readiness comes from current provider and fleet records.</p></div></div>
    <div class="dashboard-checklist">
        <article><span class="check-state <?= $providerId > 0 ? 'is-ready' : 'needs-action' ?>"><?= $providerId > 0 ? 'Recorded' : 'Needs setup' ?></span><h3>Provider profile</h3><p><?= $providerId > 0 ? e((string) ($provider['company_name'] ?: 'Provider profile exists; complete any missing company details.')) : 'No transport provider profile is attached to this account.' ?></p><a href="<?= e(app_url('account/profile.php')) ?>">Review business profile</a></article>
        <article><span class="check-state <?= $availableVehicleCount > 0 ? 'is-ready' : 'needs-action' ?>"><?= $availableVehicleCount > 0 ? 'Dispatchable' : 'Needs action' ?></span><h3>Fleet availability</h3><p><?= $availableVehicleCount > 0 ? $availableVehicleCount . ' vehicle' . ($availableVehicleCount === 1 ? ' is' : 's are') . ' currently available.' : 'No vehicle can currently be assigned to a new load.' ?></p><a href="<?= e(app_url('transport/fleet.php')) ?>">Review fleet records</a></article>
        <article><span class="check-state <?= $pendingCount > 0 ? 'needs-action' : ($activeTripCount > 0 ? 'is-ready' : 'is-clear') ?>"><?= $pendingCount > 0 ? 'Quote needed' : ($activeTripCount > 0 ? 'In progress' : 'Clear') ?></span><h3>Dispatch queue</h3><p><?= $pendingCount > 0 ? 'Quote each viable load before assigning a vehicle and driver.' : ($activeTripCount > 0 ? 'Keep each active trip milestone current.' : 'No transport request currently needs action.') ?></p><a href="#transport-request-queue">Open dispatch queue</a></article>
    </div>
</section>
<?php workspace_close(); ?>
