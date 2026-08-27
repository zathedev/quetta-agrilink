<?php
/** Quetta Workbench storage facility editor: owner-scoped capacity records and compatible-produce controls remain authoritative in PHP/MySQL. */
declare(strict_types=1);

function storage_provider_profile_for_user(int $userId): ?array
{
    return fetch_one('SELECT id, business_name, location_id FROM storage_providers WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]);
}

function initialize_storage_provider_profile(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    $statement = db()->prepare('INSERT INTO storage_providers (user_id) SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :user_id AND r.slug = "storage_provider" ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)');
    $statement->execute(['user_id' => $userId]);
    return storage_provider_profile_for_user($userId);
}

function storage_facility_type_options(): array
{
    return ['cold_storage' => 'Cold storage', 'controlled_atmosphere' => 'Controlled atmosphere', 'warehouse' => 'Warehouse', 'hybrid' => 'Hybrid'];
}

function storage_facility_status_options(): array
{
    return ['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'];
}

function storage_facility_category_catalog(): array
{
    return fetch_all('SELECT id, name FROM produce_categories WHERE is_active = 1 ORDER BY name ASC');
}

function storage_facility_supported_category_ids(int $facilityId): array
{
    if ($facilityId < 1) {
        return [];
    }
    return array_map(static fn (array $row): int => (int) $row['category_id'], fetch_all('SELECT category_id FROM facility_supported_products WHERE facility_id = :facility_id ORDER BY category_id ASC', ['facility_id' => $facilityId]));
}

function storage_facility_for_provider(int $providerId, int $facilityId): ?array
{
    if ($providerId < 1 || $facilityId < 1) {
        return null;
    }
    $facility = fetch_one('SELECT id, location_id, name, description, storage_type, total_capacity_kg, available_capacity_kg, price_per_kg_day, status FROM storage_facilities WHERE id = :facility_id AND provider_id = :provider_id LIMIT 1', ['facility_id' => $facilityId, 'provider_id' => $providerId]);
    if ($facility === null) {
        return null;
    }
    $facility['category_ids'] = storage_facility_supported_category_ids($facilityId);
    return $facility;
}

function storage_facilities_for_provider(int $providerId): array
{
    if ($providerId < 1) {
        return [];
    }
    return fetch_all('SELECT sf.id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sf.status, l.district, l.tehsil, GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ", ") AS supported_products FROM storage_facilities sf JOIN locations l ON l.id = sf.location_id LEFT JOIN facility_supported_products fsp ON fsp.facility_id = sf.id LEFT JOIN produce_categories pc ON pc.id = fsp.category_id WHERE sf.provider_id = :provider_id GROUP BY sf.id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sf.status, l.district, l.tehsil ORDER BY sf.updated_at DESC, sf.id DESC', ['provider_id' => $providerId]);
}

function storage_facility_input(array $input, array $categories): array
{
    $types = storage_facility_type_options();
    $statuses = storage_facility_status_options();
    $name = normalize_text($input['facility_name'] ?? '', 160);
    $description = normalize_text($input['facility_description'] ?? '', 1000);
    $locationId = filter_var($input['location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $storageType = normalize_text($input['storage_type'] ?? '', 40);
    $status = normalize_text($input['status'] ?? '', 20);
    $capacity = positive_decimal($input['total_capacity_kg'] ?? null);
    $price = positive_decimal($input['price_per_kg_day'] ?? null, 4);
    $rawCategories = is_array($input['category_ids'] ?? null) ? $input['category_ids'] : [];
    $categoryIds = array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0, $rawCategories))));
    $allowedCategoryIds = array_fill_keys(array_map(static fn (array $category): int => (int) $category['id'], $categories), true);
    $errors = [];
    if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) < 3) {
        $errors['facility_name'] = 'Enter a facility name using at least 3 characters.';
    }
    if (!role_profile_location_exists($locationId)) {
        $errors['location_id'] = 'Choose a listed facility location.';
    }
    if (!array_key_exists($storageType, $types)) {
        $errors['storage_type'] = 'Choose a listed storage type.';
    }
    if (!array_key_exists($status, $statuses)) {
        $errors['status'] = 'Choose a valid facility status.';
    }
    if ($capacity === null || $capacity > 100000000) {
        $errors['total_capacity_kg'] = 'Enter a total capacity greater than zero and no more than 100,000,000 kg.';
    }
    if ($price === null || $price > 1000000) {
        $errors['price_per_kg_day'] = 'Enter a daily price greater than zero and no more than Rs. 1,000,000 per kg.';
    }
    if ($categoryIds === [] || count(array_diff($categoryIds, array_keys($allowedCategoryIds))) > 0) {
        $errors['category_ids'] = 'Choose at least one active produce category.';
    }
    return [['name' => $name, 'description' => $description, 'location_id' => $locationId, 'storage_type' => $storageType, 'status' => $status, 'total_capacity_kg' => $capacity, 'price_per_kg_day' => $price, 'category_ids' => $categoryIds], $errors];
}

function storage_facility_value_changed(string $field, mixed $current, mixed $value): bool
{
    return in_array($field, ['total_capacity_kg', 'available_capacity_kg', 'price_per_kg_day'], true) ? round((float) ($current ?? 0), 4) !== round((float) ($value ?? 0), 4) : (string) ($current ?? '') !== (string) ($value ?? '');
}

function save_storage_facility(int $userId, array $input): array
{
    $categories = storage_facility_category_catalog();
    [$values, $errors] = storage_facility_input($input, $categories);
    $facilityId = filter_var($input['facility_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($errors !== []) {
        return [false, 'Please correct the highlighted facility details.', ['errors' => $errors, 'values' => $values]];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $provider = storage_provider_profile_for_user($userId) ?? initialize_storage_provider_profile($userId);
        if ($provider === null) {
            throw new RuntimeException('Your storage-provider profile is no longer available.');
        }
        if ($facilityId === 0) {
            $statement = $pdo->prepare('INSERT INTO storage_facilities (provider_id, location_id, name, description, storage_type, total_capacity_kg, available_capacity_kg, price_per_kg_day, status) VALUES (:provider_id, :location_id, :name, :description, :storage_type, :total_capacity_kg, :available_capacity_kg, :price_per_kg_day, :status)');
            $statement->execute(['provider_id' => $provider['id'], 'location_id' => $values['location_id'], 'name' => $values['name'], 'description' => $values['description'] !== '' ? $values['description'] : null, 'storage_type' => $values['storage_type'], 'total_capacity_kg' => $values['total_capacity_kg'], 'available_capacity_kg' => $values['total_capacity_kg'], 'price_per_kg_day' => $values['price_per_kg_day'], 'status' => $values['status']]);
            $facilityId = (int) $pdo->lastInsertId();
            $changed = ['facility_created', 'supported_produce'];
            $message = 'Your storage facility has been added.';
        } else {
            $facility = fetch_one('SELECT id, location_id, name, description, storage_type, total_capacity_kg, available_capacity_kg, price_per_kg_day, status FROM storage_facilities WHERE id = :facility_id AND provider_id = :provider_id FOR UPDATE', ['facility_id' => $facilityId, 'provider_id' => $provider['id']]);
            if ($facility === null) {
                throw new RuntimeException('That storage facility is not available for your account.');
            }
            $reserved = max(0, (float) $facility['total_capacity_kg'] - (float) $facility['available_capacity_kg']);
            if ((float) $values['total_capacity_kg'] < $reserved) {
                throw new RuntimeException('Total capacity cannot be lower than the ' . number_format($reserved, 2) . ' kg already reserved.');
            }
            $values['available_capacity_kg'] = (float) $values['total_capacity_kg'] - $reserved;
            $changed = [];
            foreach (['name', 'description', 'location_id', 'storage_type', 'total_capacity_kg', 'available_capacity_kg', 'price_per_kg_day', 'status'] as $field) {
                if (storage_facility_value_changed($field, $facility[$field] ?? null, $values[$field] ?? null)) {
                    $changed[] = $field;
                }
            }
            $currentCategories = storage_facility_supported_category_ids($facilityId);
            sort($currentCategories); $selectedCategories = $values['category_ids']; sort($selectedCategories);
            if ($currentCategories !== $selectedCategories) {
                $changed[] = 'supported_produce';
            }
            if ($changed === []) {
                $pdo->rollBack();
                return [true, 'This facility is already up to date.', ['changed' => []]];
            }
            $statement = $pdo->prepare('UPDATE storage_facilities SET location_id = :location_id, name = :name, description = :description, storage_type = :storage_type, total_capacity_kg = :total_capacity_kg, available_capacity_kg = :available_capacity_kg, price_per_kg_day = :price_per_kg_day, status = :status WHERE id = :facility_id AND provider_id = :provider_id');
            $statement->execute(['location_id' => $values['location_id'], 'name' => $values['name'], 'description' => $values['description'] !== '' ? $values['description'] : null, 'storage_type' => $values['storage_type'], 'total_capacity_kg' => $values['total_capacity_kg'], 'available_capacity_kg' => $values['available_capacity_kg'], 'price_per_kg_day' => $values['price_per_kg_day'], 'status' => $values['status'], 'facility_id' => $facilityId, 'provider_id' => $provider['id']]);
            $message = 'Your storage facility has been updated.';
        }
        if ($facilityId > 0 && ($facilityId !== 0)) {
            $pdo->prepare('DELETE FROM facility_supported_products WHERE facility_id = :facility_id')->execute(['facility_id' => $facilityId]);
            $categoryStatement = $pdo->prepare('INSERT INTO facility_supported_products (facility_id, category_id) VALUES (:facility_id, :category_id)');
            foreach ($values['category_ids'] as $categoryId) {
                $categoryStatement->execute(['facility_id' => $facilityId, 'category_id' => $categoryId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The facility details could not be saved. Please try again.', ['errors' => [], 'values' => $values]];
    }
    audit_log($userId, $facilityId > 0 && in_array('facility_created', $changed, true) ? 'storage_facility_created' : 'storage_facility_updated', 'storage_facilities', $facilityId, ['fields' => $changed]);
    return [true, $message, ['changed' => $changed, 'facility_id' => $facilityId]];
}
