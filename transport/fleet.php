<?php
/** Quetta Workbench fleet records: transport providers edit only their own vehicles and listed service coverage through protected PHP forms. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';
require_once __DIR__ . '/../includes/transport-asset-management.php';

$user = require_role(['transport_provider']);
$vehicleErrors = [];
$areaErrors = [];
$vehicleValues = [];
$areaValues = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = normalize_text($_POST['fleet_action'] ?? '', 30);
    if ($action === 'save_vehicle') {
        [$success, $message, $meta] = save_transport_vehicle((int) $user['id'], $_POST);
        if ($success) {
            flash('success', $message);
            redirect('transport/fleet.php' . (!empty($meta['vehicle_id']) ? '?vehicle=' . (int) $meta['vehicle_id'] : ''));
        }
        $vehicleErrors = $meta['errors'] ?? [];
        $vehicleValues = $meta['values'] ?? [];
        flash('error', $message);
    } elseif ($action === 'save_service_areas') {
        [$success, $message, $meta] = save_transport_service_areas((int) $user['id'], $_POST);
        if ($success) {
            flash('success', $message);
            redirect('transport/fleet.php');
        }
        $areaErrors = $meta['errors'] ?? [];
        $areaValues = $meta['values'] ?? [];
        flash('error', $message);
    }
}
$provider = transport_provider_profile_for_user((int) $user['id']);
$providerId = (int) ($provider['id'] ?? 0);
$vehicleId = filter_input(INPUT_GET, 'vehicle', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$editing = $vehicleId > 0 && $providerId > 0 ? transport_vehicle_for_provider($providerId, $vehicleId) : null;
$vehicleForm = $vehicleValues + ($editing ?? ['vehicle_type' => '', 'registration_number' => '', 'capacity_kg' => '', 'is_refrigerated' => 0, 'price_per_km' => '', 'status' => 'available']);
$locations = role_profile_locations();
$serviceAreaIds = $areaValues['service_area_ids'] ?? transport_service_area_ids_for_provider($providerId);
$vehicles = transport_vehicles_for_provider($providerId);
$locationLabel = static fn (array $location): string => implode(' · ', array_filter([$location['district'] ?? '', $location['tehsil'] ?? '', $location['area'] ?? '']));
workspace_open('Fleet and service areas', 'fleet');
?>
<?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($message = flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
<section class="workspace-section profile-section"><div class="workspace-section-header"><div><h2>Service areas</h2><p>Choose the verified reference locations where this transport operation can accept work. Coverage is kept separate from an individual vehicle.</p></div></div><form method="post" class="form-grid profile-form" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="fleet_action" value="save_service_areas"><fieldset class="supported-produce"><legend>Listed service areas</legend><p>Select all locations this provider currently serves.</p><div class="supported-produce-grid"><?php foreach ($locations as $location): ?><label><input type="checkbox" name="service_area_ids[]" value="<?= (int) $location['id'] ?>" <?= in_array((int) $location['id'], array_map('intval', $serviceAreaIds), true) ? 'checked' : '' ?>> <span><?= e($locationLabel($location)) ?></span></label><?php endforeach; ?></div><?php if (isset($areaErrors['service_area_ids'])): ?><small class="field-error"><?= e($areaErrors['service_area_ids']) ?></small><?php endif; ?></fieldset><div class="form-actions"><button class="button button-primary" type="submit">Save service areas</button></div></form></section>
<section class="workspace-section profile-section"><div class="workspace-section-header"><div><h2><?= $editing ? 'Edit vehicle' : 'Add a vehicle' ?></h2><p>Record vehicle capability and availability accurately. A vehicle with an active transport request cannot be marked maintenance or inactive.</p></div></div><form method="post" class="form-grid profile-form" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="fleet_action" value="save_vehicle"><?php if ($editing): ?><input type="hidden" name="vehicle_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?><div class="form-field"><label for="vehicle_type">Vehicle type</label><input id="vehicle_type" name="vehicle_type" maxlength="100" required value="<?= e((string) ($vehicleForm['vehicle_type'] ?? '')) ?>"><?php if (isset($vehicleErrors['vehicle_type'])): ?><small class="field-error"><?= e($vehicleErrors['vehicle_type']) ?></small><?php endif; ?></div><div class="form-field"><label for="registration_number">Registration number</label><input id="registration_number" name="registration_number" maxlength="50" required value="<?= e((string) ($vehicleForm['registration_number'] ?? '')) ?>"><?php if (isset($vehicleErrors['registration_number'])): ?><small class="field-error"><?= e($vehicleErrors['registration_number']) ?></small><?php endif; ?></div><div class="form-field"><label for="capacity_kg">Capacity (kg)</label><input id="capacity_kg" name="capacity_kg" type="number" min="0.01" max="100000000" step="0.01" required value="<?= e((string) ($vehicleForm['capacity_kg'] ?? '')) ?>"><?php if (isset($vehicleErrors['capacity_kg'])): ?><small class="field-error"><?= e($vehicleErrors['capacity_kg']) ?></small><?php endif; ?></div><div class="form-field"><label for="price_per_km">Price per km (Rs.)</label><input id="price_per_km" name="price_per_km" type="number" min="0.01" max="1000000" step="0.01" value="<?= e((string) ($vehicleForm['price_per_km'] ?? '')) ?>"><?php if (isset($vehicleErrors['price_per_km'])): ?><small class="field-error"><?= e($vehicleErrors['price_per_km']) ?></small><?php endif; ?></div><div class="form-field"><label for="status">Availability status</label><select id="status" name="status" required><?php foreach (transport_vehicle_status_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($vehicleForm['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (isset($vehicleErrors['status'])): ?><small class="field-error"><?= e($vehicleErrors['status']) ?></small><?php endif; ?></div><div class="form-field fleet-checkbox"><label><input type="checkbox" name="is_refrigerated" value="1" <?= !empty($vehicleForm['is_refrigerated']) ? 'checked' : '' ?>> Refrigerated vehicle</label><small class="form-help">Use this only when the vehicle can maintain the necessary cold chain.</small></div><div class="form-actions"><?php if ($editing): ?><a class="button button-outline" href="<?= e(app_url('transport/fleet.php')) ?>">Cancel edit</a><?php endif; ?><button class="button button-primary" type="submit"><?= $editing ? 'Save vehicle' : 'Add vehicle' ?></button></div></form></section>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Your fleet records</h2><p>Only vehicles owned by this provider account appear here. Use inactive rather than deleting a historical vehicle record.</p></div><span class="status-pill"><?= count($vehicles) ?> recorded</span></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Vehicle</th><th>Capacity</th><th>Cold chain</th><th>Price</th><th>Active requests</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if ($vehicles === []): ?><tr><td colspan="7">No vehicle records yet. Add verified fleet details before accepting live requests.</td></tr><?php else: foreach ($vehicles as $vehicle): ?><tr><td><strong><?= e($vehicle['vehicle_type']) ?></strong><br><span class="muted"><?= e($vehicle['registration_number']) ?></span></td><td><?= number_format((float) $vehicle['capacity_kg'], 0) ?> kg</td><td><?= (int) $vehicle['is_refrigerated'] === 1 ? 'Refrigerated' : 'Standard' ?></td><td><?= $vehicle['price_per_km'] !== null ? 'Rs. ' . number_format((float) $vehicle['price_per_km'], 2) . '/km' : 'Not recorded' ?></td><td><?= (int) $vehicle['active_request_count'] ?></td><td><span class="status-pill <?= e($vehicle['status']) ?>"><?= e(ucfirst($vehicle['status'])) ?></span></td><td><a class="button button-quiet" href="<?= e(app_url('transport/fleet.php?vehicle=' . (int) $vehicle['id'])) ?>">Edit</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
