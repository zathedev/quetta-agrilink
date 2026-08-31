<?php
/** Quetta Workbench storage discovery: allowlisted public filters and deterministic facility ordering. */
declare(strict_types=1);

function storage_marketplace_filters(array $input): array
{
    $sorts = [
        'capacity_high' => 'sf.available_capacity_kg DESC, sf.updated_at DESC',
        'price_low' => 'sf.price_per_kg_day ASC, sf.available_capacity_kg DESC',
        'price_high' => 'sf.price_per_kg_day DESC, sf.available_capacity_kg DESC',
        'recent' => 'sf.updated_at DESC, sf.id DESC',
    ];
    $types = ['cold_storage', 'controlled_atmosphere', 'warehouse', 'hybrid'];
    $requestedSort = is_string($input['sort'] ?? null) ? $input['sort'] : 'capacity_high';
    $requestedType = is_string($input['storage_type'] ?? null) ? $input['storage_type'] : '';

    return [
        'category_id' => filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'district' => normalize_text($input['district'] ?? '', 100),
        'storage_type' => in_array($requestedType, $types, true) ? $requestedType : '',
        'min_capacity' => positive_decimal($input['min_capacity'] ?? null),
        'max_price' => positive_decimal($input['max_price'] ?? null),
        'sort' => array_key_exists($requestedSort, $sorts) ? $requestedSort : 'capacity_high',
        'order_by' => $sorts[array_key_exists($requestedSort, $sorts) ? $requestedSort : 'capacity_high'],
    ];
}

function saved_storage_searches_ready(): bool
{
    try { return (int) (fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => 'saved_storage_searches'])['count'] ?? 0) === 1; } catch (Throwable) { return false; }
}

function saved_storage_searches_for_user(int $userId): array
{
    if ($userId < 1 || !saved_storage_searches_ready()) return [];
    return fetch_all('SELECT ss.*, pc.name AS category_name FROM saved_storage_searches ss LEFT JOIN produce_categories pc ON pc.id = ss.category_id WHERE ss.user_id = :user_id ORDER BY ss.updated_at DESC, ss.id DESC LIMIT 20', ['user_id' => $userId]);
}

function save_storage_search(int $userId, string $name, array $filters): void
{
    if ($userId < 1 || !saved_storage_searches_ready()) throw new RuntimeException('Saved storage searches are not ready. Import database/migrations/20260827_add_saved_storage_searches.sql, then refresh this page.');
    $name = normalize_text($name, 80); $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($length < 3) throw new RuntimeException('Use at least 3 characters to name this storage search.');
    if (fetch_one('SELECT id FROM saved_storage_searches WHERE user_id = :user_id AND name = :name LIMIT 1', ['user_id' => $userId, 'name' => $name])) throw new RuntimeException('A saved storage search already uses that name.');
    execute_query('INSERT INTO saved_storage_searches (user_id, name, category_id, district, storage_type, min_capacity, max_price, sort_key) VALUES (:user_id, :name, :category_id, :district, :storage_type, :min_capacity, :max_price, :sort_key)', ['user_id' => $userId, 'name' => $name, 'category_id' => $filters['category_id'], 'district' => $filters['district'] ?: null, 'storage_type' => $filters['storage_type'] ?: null, 'min_capacity' => $filters['min_capacity'], 'max_price' => $filters['max_price'], 'sort_key' => $filters['sort']]);
    audit_log($userId, 'storage_search_saved', 'saved_storage_searches', (int) db()->lastInsertId(), ['name' => $name]);
}

function delete_storage_search(int $userId, int $searchId): void
{
    if ($userId < 1 || $searchId < 1 || !saved_storage_searches_ready()) throw new RuntimeException('That saved storage search is not available.');
    $search = fetch_one('SELECT id, name FROM saved_storage_searches WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $searchId, 'user_id' => $userId]);
    if ($search === null) throw new RuntimeException('That saved storage search is not available.');
    execute_query('DELETE FROM saved_storage_searches WHERE id = :id AND user_id = :user_id', ['id' => $searchId, 'user_id' => $userId]);
    audit_log($userId, 'storage_search_deleted', 'saved_storage_searches', $searchId, ['name' => $search['name']]);
}

function storage_search_for_user(int $userId, int $searchId): ?array
{
    if ($userId < 1 || $searchId < 1 || !saved_storage_searches_ready()) return null;
    return fetch_one('SELECT * FROM saved_storage_searches WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $searchId, 'user_id' => $userId]);
}

function update_storage_search(int $userId, int $searchId, string $name, array $filters): void
{
    if (storage_search_for_user($userId, $searchId) === null) throw new RuntimeException('That saved storage search is not available.');
    $name = normalize_text($name, 80); $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($length < 3) throw new RuntimeException('Use at least 3 characters to name this storage search.');
    if (fetch_one('SELECT id FROM saved_storage_searches WHERE user_id = :user_id AND name = :name AND id != :id LIMIT 1', ['user_id' => $userId, 'name' => $name, 'id' => $searchId])) throw new RuntimeException('Another saved storage search already uses that name.');
    execute_query('UPDATE saved_storage_searches SET name = :name, category_id = :category_id, district = :district, storage_type = :storage_type, min_capacity = :min_capacity, max_price = :max_price, sort_key = :sort_key WHERE id = :id AND user_id = :user_id', ['id' => $searchId, 'user_id' => $userId, 'name' => $name, 'category_id' => $filters['category_id'], 'district' => $filters['district'] ?: null, 'storage_type' => $filters['storage_type'] ?: null, 'min_capacity' => $filters['min_capacity'], 'max_price' => $filters['max_price'], 'sort_key' => $filters['sort']]);
    audit_log($userId, 'storage_search_updated', 'saved_storage_searches', $searchId, ['name' => $name]);
}

function saved_storage_search_query(array $search): string
{
    return http_build_query(array_filter(['category_id' => $search['category_id'] ?: null, 'district' => $search['district'] ?: null, 'storage_type' => $search['storage_type'] ?: null, 'min_capacity' => $search['min_capacity'] ?: null, 'max_price' => $search['max_price'] ?: null, 'sort' => $search['sort_key'] ?? 'capacity_high'], static fn($value) => $value !== null && $value !== ''));
}

function find_storage_facilities(array $filters, int $limit = 24, int $offset = 0): array
{
    $where = ['sf.status = "active"'];
    $params = [];
    if ($filters['district'] !== '') {
        $where[] = 'l.district = :district';
        $params['district'] = $filters['district'];
    }
    if ($filters['storage_type'] !== '') {
        $where[] = 'sf.storage_type = :storage_type';
        $params['storage_type'] = $filters['storage_type'];
    }
    if ($filters['min_capacity'] !== null) {
        $where[] = 'sf.available_capacity_kg >= :min_capacity';
        $params['min_capacity'] = $filters['min_capacity'];
    }
    if ($filters['max_price'] !== null) {
        $where[] = 'sf.price_per_kg_day <= :max_price';
        $params['max_price'] = $filters['max_price'];
    }
    if ($filters['category_id'] !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM facility_supported_products fsp_filter WHERE fsp_filter.facility_id = sf.id AND fsp_filter.category_id = :category_id)';
        $params['category_id'] = $filters['category_id'];
    }
    $limit = max(1, min($limit, 48)); $offset = max(0, min($offset, 10000));
    $sql = 'SELECT sf.id, sf.provider_id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sp.business_name, u.full_name AS contact_name, l.district, GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ", ") AS supported_products FROM storage_facilities sf JOIN storage_providers sp ON sp.id = sf.provider_id JOIN users u ON u.id = sp.user_id JOIN locations l ON l.id = sf.location_id LEFT JOIN facility_supported_products fsp ON fsp.facility_id = sf.id LEFT JOIN produce_categories pc ON pc.id = fsp.category_id WHERE ' . implode(' AND ', $where) . ' GROUP BY sf.id, sf.provider_id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sp.business_name, u.full_name, l.district ORDER BY ' . $filters['order_by'] . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    return fetch_all($sql, $params);
}

function count_storage_facilities(array $filters): int
{
    $where = ['sf.status = "active"']; $params = [];
    if ($filters['district'] !== '') { $where[] = 'l.district = :district'; $params['district'] = $filters['district']; }
    if ($filters['storage_type'] !== '') { $where[] = 'sf.storage_type = :storage_type'; $params['storage_type'] = $filters['storage_type']; }
    if ($filters['min_capacity'] !== null) { $where[] = 'sf.available_capacity_kg >= :min_capacity'; $params['min_capacity'] = $filters['min_capacity']; }
    if ($filters['max_price'] !== null) { $where[] = 'sf.price_per_kg_day <= :max_price'; $params['max_price'] = $filters['max_price']; }
    if ($filters['category_id'] !== null) { $where[] = 'EXISTS (SELECT 1 FROM facility_supported_products fsp_filter WHERE fsp_filter.facility_id = sf.id AND fsp_filter.category_id = :category_id)'; $params['category_id'] = $filters['category_id']; }
    return (int) (fetch_one('SELECT COUNT(*) AS count FROM storage_facilities sf JOIN locations l ON l.id = sf.location_id WHERE ' . implode(' AND ', $where), $params)['count'] ?? 0);
}

function storage_facility_cards_html(array $facilities, ?array $user = null): string
{
    if ($facilities === []) {
        return '<div class="listing-empty"><h2>No active storage facilities match these filters.</h2><p>Clear one or more filters, or return later when a provider publishes available capacity.</p></div>';
    }
    ob_start();
    foreach ($facilities as $facility):
        $availabilityPercent = (float) $facility['total_capacity_kg'] > 0 ? max(0, min(100, ((float) $facility['available_capacity_kg'] / (float) $facility['total_capacity_kg']) * 100)) : 0;
        ?>
        <article class="service-card storage-facility-card">
            <div class="service-card-accent"><span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) $facility['storage_type']))) ?></span><span><?= number_format($availabilityPercent, 0) ?>% available</span></div>
            <h2><?= e((string) $facility['name']) ?></h2>
            <p><?= e((string) $facility['district']) ?>, Balochistan · <?= e((string) ($facility['business_name'] ?: $facility['contact_name'])) ?></p>
            <div class="capacity-meter" role="img" aria-label="<?= number_format($availabilityPercent, 0) ?> percent of facility capacity available"><span style="width:<?= e(number_format($availabilityPercent, 2, '.', '')) ?>%"></span></div>
            <ul class="service-specs">
                <li><span>Available capacity</span><strong><?= number_format((float) $facility['available_capacity_kg'], 0) ?> kg</strong></li>
                <li><span>Total capacity</span><strong><?= number_format((float) $facility['total_capacity_kg'], 0) ?> kg</strong></li>
                <li><span>Daily price</span><strong>Rs. <?= number_format((float) $facility['price_per_kg_day'], 2) ?>/kg</strong></li>
                <li><span>Supported produce</span><strong><?= e((string) ($facility['supported_products'] ?: 'Provider to confirm')) ?></strong></li>
            </ul>
            <a class="button button-quiet" href="<?= e(app_url('storage/facility.php?id='.(int)$facility['id'])) ?>">View facility details</a>
            <?php if ($user !== null && $user['role_slug'] === 'farmer'): ?>
                <a class="button button-primary" href="#book-storage" data-facility-id="<?= (int) $facility['id'] ?>">Request this capacity</a>
            <?php else: ?>
                <a class="button button-outline" href="<?= e(app_url('auth/login.php')) ?>">Sign in as farmer to request</a>
            <?php endif; ?>
        </article>
    <?php endforeach;
    return (string) ob_get_clean();
}
