<?php
/** Quetta Workbench fleet management: transport providers control only their own vehicles and reference-based service areas through authoritative PHP/MySQL writes. */
declare(strict_types=1);

function transport_provider_profile_for_user(int $userId): ?array
{
    return fetch_one('SELECT id, company_name, location_id FROM transport_providers WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]);
}

function initialize_transport_provider_profile(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    $statement = db()->prepare('INSERT INTO transport_providers (user_id) SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :user_id AND r.slug = "transport_provider" ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)');
    $statement->execute(['user_id' => $userId]);
    return transport_provider_profile_for_user($userId);
}

function transport_vehicle_status_options(): array
{
    return ['available' => 'Available', 'busy' => 'Busy', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'];
}

function transport_vehicle_for_provider(int $providerId, int $vehicleId): ?array
{
    if ($providerId < 1 || $vehicleId < 1) {
        return null;
    }
    return fetch_one('SELECT id, vehicle_type, registration_number, capacity_kg, is_refrigerated, price_per_km, status FROM vehicles WHERE id = :vehicle_id AND provider_id = :provider_id LIMIT 1', ['vehicle_id' => $vehicleId, 'provider_id' => $providerId]);
}

function transport_vehicles_for_provider(int $providerId): array
{
    if ($providerId < 1) {
        return [];
    }
    return fetch_all('SELECT v.id, v.vehicle_type, v.registration_number, v.capacity_kg, v.is_refrigerated, v.price_per_km, v.status, COUNT(tr.id) AS active_request_count FROM vehicles v LEFT JOIN transport_requests tr ON tr.vehicle_id = v.id AND tr.status IN ("accepted", "driver_assigned", "pickup_scheduled", "picked_up", "in_transit") WHERE v.provider_id = :provider_id GROUP BY v.id, v.vehicle_type, v.registration_number, v.capacity_kg, v.is_refrigerated, v.price_per_km, v.status ORDER BY v.updated_at DESC, v.id DESC', ['provider_id' => $providerId]);
}

function transport_service_area_ids_for_provider(int $providerId): array
{
    if ($providerId < 1) {
        return [];
    }
    return array_map(static fn (array $row): int => (int) $row['location_id'], fetch_all('SELECT location_id FROM transport_service_areas WHERE provider_id = :provider_id ORDER BY location_id ASC', ['provider_id' => $providerId]));
}

function transport_vehicle_input(array $input): array
{
    $statuses = transport_vehicle_status_options();
    $type = normalize_text($input['vehicle_type'] ?? '', 100);
    $registration = strtoupper(normalize_text($input['registration_number'] ?? '', 50));
    $capacity = positive_decimal($input['capacity_kg'] ?? null);
    $priceInput = trim((string) ($input['price_per_km'] ?? ''));
    $price = $priceInput === '' ? null : positive_decimal($priceInput);
    $status = normalize_text($input['status'] ?? '', 20);
    $errors = [];
    if ((function_exists('mb_strlen') ? mb_strlen($type) : strlen($type)) < 3) {
        $errors['vehicle_type'] = 'Enter a vehicle type using at least 3 characters.';
    }
    if (preg_match('/^[A-Z0-9][A-Z0-9\s-]{2,49}$/', $registration) !== 1) {
        $errors['registration_number'] = 'Use 3–50 uppercase letters, numbers, spaces, or hyphens for the registration.';
    }
    if ($capacity === null || $capacity > 100000000) {
        $errors['capacity_kg'] = 'Enter a vehicle capacity greater than zero and no more than 100,000,000 kg.';
    }
    if ($priceInput !== '' && ($price === null || $price > 1000000)) {
        $errors['price_per_km'] = 'Enter a per-kilometre price greater than zero and no more than Rs. 1,000,000.';
    }
    if (!array_key_exists($status, $statuses)) {
        $errors['status'] = 'Choose a valid vehicle status.';
    }
    return [['vehicle_type' => $type, 'registration_number' => $registration, 'capacity_kg' => $capacity, 'is_refrigerated' => isset($input['is_refrigerated']) ? 1 : 0, 'price_per_km' => $price, 'status' => $status], $errors];
}

function transport_vehicle_value_changed(string $field, mixed $current, mixed $value): bool
{
    return in_array($field, ['capacity_kg', 'price_per_km'], true) ? round((float) ($current ?? 0), 2) !== round((float) ($value ?? 0), 2) : (string) ($current ?? '') !== (string) ($value ?? '');
}

function save_transport_vehicle(int $userId, array $input): array
{
    [$values, $errors] = transport_vehicle_input($input);
    $vehicleId = filter_var($input['vehicle_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($errors !== []) {
        return [false, 'Please correct the highlighted vehicle details.', ['errors' => $errors, 'values' => $values]];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $provider = transport_provider_profile_for_user($userId) ?? initialize_transport_provider_profile($userId);
        if ($provider === null) {
            throw new RuntimeException('Your transport-provider profile is no longer available.');
        }
        if ($vehicleId === 0) {
            $duplicate = fetch_one('SELECT id FROM vehicles WHERE registration_number = :registration_number LIMIT 1', ['registration_number' => $values['registration_number']]);
            if ($duplicate !== null) {
                throw new RuntimeException('That registration number is already recorded for another vehicle.');
            }
            $statement = $pdo->prepare('INSERT INTO vehicles (provider_id, vehicle_type, registration_number, capacity_kg, is_refrigerated, price_per_km, status) VALUES (:provider_id, :vehicle_type, :registration_number, :capacity_kg, :is_refrigerated, :price_per_km, :status)');
            $statement->execute(['provider_id' => $provider['id'], ...$values]);
            $vehicleId = (int) $pdo->lastInsertId();
            $changed = ['vehicle_created'];
            $message = 'Your vehicle has been added.';
        } else {
            $vehicle = fetch_one('SELECT id, vehicle_type, registration_number, capacity_kg, is_refrigerated, price_per_km, status FROM vehicles WHERE id = :vehicle_id AND provider_id = :provider_id FOR UPDATE', ['vehicle_id' => $vehicleId, 'provider_id' => $provider['id']]);
            if ($vehicle === null) {
                throw new RuntimeException('That vehicle is not available for your account.');
            }
            $duplicate = fetch_one('SELECT id FROM vehicles WHERE registration_number = :registration_number AND id != :vehicle_id LIMIT 1', ['registration_number' => $values['registration_number'], 'vehicle_id' => $vehicleId]);
            if ($duplicate !== null) {
                throw new RuntimeException('That registration number is already recorded for another vehicle.');
            }
            $activeRequests = (int) (fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE vehicle_id = :vehicle_id AND status IN ("accepted", "driver_assigned", "pickup_scheduled", "picked_up", "in_transit")', ['vehicle_id' => $vehicleId])['count'] ?? 0);
            if ($activeRequests > 0 && in_array($values['status'], ['maintenance', 'inactive'], true)) {
                throw new RuntimeException('This vehicle has an active transport request and cannot be marked maintenance or inactive.');
            }
            $changed = [];
            foreach (['vehicle_type', 'registration_number', 'capacity_kg', 'is_refrigerated', 'price_per_km', 'status'] as $field) {
                if (transport_vehicle_value_changed($field, $vehicle[$field] ?? null, $values[$field])) {
                    $changed[] = $field;
                }
            }
            if ($changed === []) {
                $pdo->rollBack();
                return [true, 'This vehicle is already up to date.', ['changed' => [], 'vehicle_id' => $vehicleId]];
            }
            $statement = $pdo->prepare('UPDATE vehicles SET vehicle_type = :vehicle_type, registration_number = :registration_number, capacity_kg = :capacity_kg, is_refrigerated = :is_refrigerated, price_per_km = :price_per_km, status = :status WHERE id = :vehicle_id AND provider_id = :provider_id');
            $statement->execute(['vehicle_id' => $vehicleId, 'provider_id' => $provider['id'], ...$values]);
            $message = 'Your vehicle has been updated.';
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The vehicle details could not be saved. Please try again.', ['errors' => [], 'values' => $values]];
    }
    audit_log($userId, in_array('vehicle_created', $changed, true) ? 'transport_vehicle_created' : 'transport_vehicle_updated', 'vehicles', $vehicleId, ['fields' => $changed]);
    return [true, $message, ['changed' => $changed, 'vehicle_id' => $vehicleId]];
}

function save_transport_service_areas(int $userId, array $input): array
{
    $rawLocations = is_array($input['service_area_ids'] ?? null) ? $input['service_area_ids'] : [];
    $locationIds = array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0, $rawLocations))));
    if ($locationIds === [] || count(array_filter($locationIds, static fn (int $id): bool => !role_profile_location_exists($id))) > 0) {
        return [false, 'Choose at least one listed service area.', ['errors' => ['service_area_ids' => 'Choose at least one listed service area.'], 'values' => ['service_area_ids' => $locationIds]]];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $provider = transport_provider_profile_for_user($userId) ?? initialize_transport_provider_profile($userId);
        if ($provider === null) {
            throw new RuntimeException('Your transport-provider profile is no longer available.');
        }
        $currentIds = transport_service_area_ids_for_provider((int) $provider['id']);
        sort($currentIds); $selectedIds = $locationIds; sort($selectedIds);
        if ($currentIds === $selectedIds) {
            $pdo->rollBack();
            return [true, 'Your service areas are already up to date.', ['changed' => []]];
        }
        $pdo->prepare('DELETE FROM transport_service_areas WHERE provider_id = :provider_id')->execute(['provider_id' => $provider['id']]);
        $statement = $pdo->prepare('INSERT INTO transport_service_areas (provider_id, location_id) VALUES (:provider_id, :location_id)');
        foreach ($selectedIds as $locationId) {
            $statement->execute(['provider_id' => $provider['id'], 'location_id' => $locationId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The service areas could not be saved. Please try again.', ['errors' => [], 'values' => ['service_area_ids' => $locationIds]]];
    }
    audit_log($userId, 'transport_service_areas_updated', 'transport_service_areas', null, ['fields' => ['service_areas']]);
    return [true, 'Your service areas have been updated.', ['changed' => ['service_areas']]];
}
