<?php
/** Orchard Ledger marketplace service: server-owned filters, strict query parameters, and reusable ledger cards. */
declare(strict_types=1);

function marketplace_filters(array $input): array
{
    $sorts = ['recent' => 'pl.published_at DESC', 'price_low' => 'pl.expected_price ASC', 'price_high' => 'pl.expected_price DESC', 'quantity_high' => 'pl.quantity_available DESC'];
    $requestedSort = is_string($input['sort'] ?? null) ? $input['sort'] : 'recent';
    $sort = array_key_exists($requestedSort, $sorts) ? $requestedSort : 'recent';
    return [
        'category_id' => filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'district' => normalize_text($input['district'] ?? '', 100),
        'grade' => in_array(($input['grade'] ?? ''), ['A', 'B', 'C', 'Mixed'], true) ? $input['grade'] : '',
        'min_price' => positive_decimal($input['min_price'] ?? null),
        'max_price' => positive_decimal($input['max_price'] ?? null),
        'min_quantity' => positive_decimal($input['min_quantity'] ?? null),
        'sort' => $sort,
        'order_by' => $sorts[$sort],
    ];
}

function saved_marketplace_filters_migration_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'saved_marketplace_filters']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function saved_marketplace_filters_for_user(int $userId): array
{
    if ($userId < 1 || !saved_marketplace_filters_migration_is_available()) {
        return [];
    }

    return fetch_all(
        'SELECT sf.*, pc.name AS category_name
         FROM saved_marketplace_filters sf
         LEFT JOIN produce_categories pc ON pc.id = sf.category_id
         WHERE sf.user_id = :user_id
         ORDER BY sf.updated_at DESC, sf.id DESC
         LIMIT 20',
        ['user_id' => $userId]
    );
}

function save_marketplace_filter(int $userId, string $name, array $filters): void
{
    if ($userId < 1 || !saved_marketplace_filters_migration_is_available()) {
        throw new RuntimeException('Saved filters are not ready. Import database/migrations/20260825_add_saved_marketplace_filters.sql into the quetta_agrilink database, then refresh this page.');
    }

    $name = normalize_text($name, 80);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nameLength < 3) {
        throw new RuntimeException('Use at least 3 characters to name this saved filter.');
    }
    $existing = fetch_one('SELECT id FROM saved_marketplace_filters WHERE user_id = :user_id AND name = :name LIMIT 1', ['user_id' => $userId, 'name' => $name]);
    if ($existing !== null) {
        throw new RuntimeException('A saved filter already uses that name. Choose a different name.');
    }

    execute_query(
        'INSERT INTO saved_marketplace_filters (user_id, name, category_id, district, grade, min_price, max_price, min_quantity, sort_key)
         VALUES (:user_id, :name, :category_id, :district, :grade, :min_price, :max_price, :min_quantity, :sort_key)',
        [
            'user_id' => $userId,
            'name' => $name,
            'category_id' => $filters['category_id'],
            'district' => $filters['district'] !== '' ? $filters['district'] : null,
            'grade' => $filters['grade'] !== '' ? $filters['grade'] : null,
            'min_price' => $filters['min_price'],
            'max_price' => $filters['max_price'],
            'min_quantity' => $filters['min_quantity'],
            'sort_key' => $filters['sort'],
        ]
    );
    $savedFilterId = (int) db()->lastInsertId();
    audit_log($userId, 'marketplace_filter_saved', 'saved_marketplace_filters', $savedFilterId, ['name' => $name]);
}

function delete_marketplace_filter(int $userId, int $filterId): void
{
    if ($userId < 1 || $filterId < 1 || !saved_marketplace_filters_migration_is_available()) {
        throw new RuntimeException('The saved filter is not available.');
    }
    $filter = fetch_one('SELECT id, name FROM saved_marketplace_filters WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $filterId, 'user_id' => $userId]);
    if ($filter === null) {
        throw new RuntimeException('The saved filter is not available.');
    }
    execute_query('DELETE FROM saved_marketplace_filters WHERE id = :id AND user_id = :user_id', ['id' => $filterId, 'user_id' => $userId]);
    audit_log($userId, 'marketplace_filter_deleted', 'saved_marketplace_filters', $filterId, ['name' => $filter['name']]);
}

function saved_marketplace_filter_query(array $filter): string
{
    $query = [
        'category_id' => $filter['category_id'] ?: null,
        'district' => $filter['district'] ?: null,
        'grade' => $filter['grade'] ?: null,
        'min_price' => $filter['min_price'] ?: null,
        'max_price' => $filter['max_price'] ?: null,
        'min_quantity' => $filter['min_quantity'] ?: null,
        'sort' => $filter['sort_key'] ?? 'recent',
    ];
    return http_build_query(array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== ''));
}

function find_listings(array $filters, int $limit = 24): array
{
    $where = ['pl.status = "active"'];
    $params = [];
    if ($filters['category_id'] !== null) { $where[] = 'pl.category_id = :category_id'; $params['category_id'] = $filters['category_id']; }
    if ($filters['district'] !== '') { $where[] = 'l.district = :district'; $params['district'] = $filters['district']; }
    if ($filters['grade'] !== '') { $where[] = 'pl.grade = :grade'; $params['grade'] = $filters['grade']; }
    if ($filters['min_price'] !== null) { $where[] = 'pl.expected_price >= :min_price'; $params['min_price'] = $filters['min_price']; }
    if ($filters['max_price'] !== null) { $where[] = 'pl.expected_price <= :max_price'; $params['max_price'] = $filters['max_price']; }
    if ($filters['min_quantity'] !== null) { $where[] = 'pl.quantity_available >= :min_quantity'; $params['min_quantity'] = $filters['min_quantity']; }
    $limit = max(1, min($limit, 48));
    $sql = 'SELECT pl.id, pl.title, pl.grade, pl.quantity_available, pl.unit, pl.expected_price, pl.harvest_date, pl.minimum_order_quantity, pc.name AS category_name, l.district, u.full_name AS farmer_name, pi.file_path AS image_path FROM produce_listings pl JOIN produce_categories pc ON pc.id = pl.category_id JOIN locations l ON l.id = pl.location_id JOIN users u ON u.id = pl.farmer_id LEFT JOIN produce_images pi ON pi.listing_id = pl.id AND pi.is_primary = 1 WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $filters['order_by'] . ' LIMIT ' . $limit;
    return fetch_all($sql, $params);
}

function listing_card_html(array $listing, int $index = 0): string
{
    $image = $listing['image_path'] ?: '/manus-storage/quetta-agrilink-market_4c9d82f8.jpg';
    $tone = ['', 'grape', 'apricot', 'pomegranate'][$index % 4];
    ob_start(); ?>
    <article class="listing-card"><div class="listing-card-media" style="background-image:url('<?= e($image) ?>')"><span class="badge">Grade <?= e($listing['grade']) ?></span></div><div class="listing-card-body"><div class="listing-card-top"><h2><?= e($listing['title']) ?></h2><div class="commodity-mark <?= e($tone) ?>" aria-hidden="true"></div></div><p class="origin-stamp"><?= e($listing['district']) ?>, Balochistan · <?= e($listing['farmer_name']) ?></p><div class="listing-data"><div><span>Available</span><strong><?= e((string) $listing['quantity_available']) ?> <?= e($listing['unit']) ?></strong></div><div><span>Expected price</span><strong>Rs. <?= number_format((float) $listing['expected_price'], 0) ?>/<?= e($listing['unit']) ?></strong></div><div><span>Minimum order</span><strong><?= e((string) $listing['minimum_order_quantity']) ?> <?= e($listing['unit']) ?></strong></div><div><span>Harvest</span><strong><?= $listing['harvest_date'] ? e(date('j M Y', strtotime($listing['harvest_date']))) : 'Not stated' ?></strong></div></div><div class="listing-card-actions"><span class="muted" style="font-size:12px"><?= e($listing['category_name']) ?></span><a class="button button-quiet" href="<?= e(app_url('marketplace/listing.php?id=' . (int) $listing['id'])) ?>">View listing</a></div></div></article>
    <?php return (string) ob_get_clean();
}

function listing_cards_html(array $listings): string
{
    if ($listings === []) {
        return '<div class="listing-empty"><h2>No active produce listings match these filters.</h2><p>Clear one or more filters, or return later as growers publish new availability.</p></div>';
    }
    $html = '';
    foreach ($listings as $index => $listing) { $html .= listing_card_html($listing, $index); }
    return $html;
}
