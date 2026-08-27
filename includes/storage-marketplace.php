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

function find_storage_facilities(array $filters, int $limit = 24): array
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
    $limit = max(1, min($limit, 48));
    $sql = 'SELECT sf.id, sf.provider_id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sp.business_name, u.full_name AS contact_name, l.district, GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ", ") AS supported_products FROM storage_facilities sf JOIN storage_providers sp ON sp.id = sf.provider_id JOIN users u ON u.id = sp.user_id JOIN locations l ON l.id = sf.location_id LEFT JOIN facility_supported_products fsp ON fsp.facility_id = sf.id LEFT JOIN produce_categories pc ON pc.id = fsp.category_id WHERE ' . implode(' AND ', $where) . ' GROUP BY sf.id, sf.provider_id, sf.name, sf.storage_type, sf.total_capacity_kg, sf.available_capacity_kg, sf.price_per_kg_day, sp.business_name, u.full_name, l.district ORDER BY ' . $filters['order_by'] . ' LIMIT ' . $limit;
    return fetch_all($sql, $params);
}

function storage_facility_cards_html(array $facilities, ?array $user = null): string
{
    if ($facilities === []) {
        return '<div class="listing-empty"><h2>No active storage facilities match these filters.</h2><p>Clear one or more filters, or return later when a provider publishes available capacity.</p></div>';
    }
    ob_start();
    foreach ($facilities as $facility): ?>
        <article class="service-card">
            <span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) $facility['storage_type']))) ?></span>
            <h2><?= e((string) $facility['name']) ?></h2>
            <p><?= e((string) $facility['district']) ?>, Balochistan · <?= e((string) ($facility['business_name'] ?: $facility['contact_name'])) ?></p>
            <ul class="service-specs">
                <li><span>Available capacity</span><strong><?= number_format((float) $facility['available_capacity_kg'], 0) ?> kg</strong></li>
                <li><span>Total capacity</span><strong><?= number_format((float) $facility['total_capacity_kg'], 0) ?> kg</strong></li>
                <li><span>Daily price</span><strong>Rs. <?= number_format((float) $facility['price_per_kg_day'], 2) ?>/kg</strong></li>
                <li><span>Supported produce</span><strong><?= e((string) ($facility['supported_products'] ?: 'Provider to confirm')) ?></strong></li>
            </ul>
            <?php if ($user !== null && $user['role_slug'] === 'farmer'): ?>
                <a class="button button-primary" href="#book-storage" data-facility-id="<?= (int) $facility['id'] ?>">Request this capacity</a>
            <?php else: ?>
                <a class="button button-outline" href="<?= e(app_url('auth/login.php')) ?>">Sign in as farmer to request</a>
            <?php endif; ?>
        </article>
    <?php endforeach;
    return (string) ob_get_clean();
}
