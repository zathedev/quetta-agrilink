<?php
/** Orchard Ledger marketplace service: server-owned filters, accountable publishing, and default-filter alerts. */
declare(strict_types=1);

function marketplace_filters(array $input): array
{
    $sorts = ['recent' => 'pl.published_at DESC', 'price_low' => 'pl.expected_price ASC', 'price_high' => 'pl.expected_price DESC', 'quantity_high' => 'pl.quantity_available DESC'];
    $requestedSort = is_string($input['sort'] ?? null) ? $input['sort'] : 'recent';
    $sort = array_key_exists($requestedSort, $sorts) ? $requestedSort : 'recent';
    return [
        'search' => normalize_text($input['search'] ?? '', 80),
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

function default_saved_marketplace_filter_migration_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name', ['table_name' => 'saved_marketplace_filters', 'column_name' => 'is_default']);
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

function marketplace_filter_for_user(int $userId, int $filterId): ?array
{
    if ($userId < 1 || $filterId < 1 || !saved_marketplace_filters_migration_is_available()) {
        return null;
    }
    return fetch_one('SELECT * FROM saved_marketplace_filters WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $filterId, 'user_id' => $userId]);
}

function update_marketplace_filter(int $userId, int $filterId, string $name, array $filters): void
{
    $filter = marketplace_filter_for_user($userId, $filterId);
    if ($filter === null) {
        throw new RuntimeException('The saved filter is not available.');
    }
    $name = normalize_text($name, 80);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($nameLength < 3) {
        throw new RuntimeException('Use at least 3 characters to name this saved filter.');
    }
    $duplicate = fetch_one('SELECT id FROM saved_marketplace_filters WHERE user_id = :user_id AND name = :name AND id != :id LIMIT 1', ['user_id' => $userId, 'name' => $name, 'id' => $filterId]);
    if ($duplicate !== null) {
        throw new RuntimeException('Another saved filter already uses that name. Choose a different name.');
    }
    execute_query(
        'UPDATE saved_marketplace_filters SET name = :name, category_id = :category_id, district = :district, grade = :grade, min_price = :min_price, max_price = :max_price, min_quantity = :min_quantity, sort_key = :sort_key WHERE id = :id AND user_id = :user_id',
        ['id' => $filterId, 'user_id' => $userId, 'name' => $name, 'category_id' => $filters['category_id'], 'district' => $filters['district'] !== '' ? $filters['district'] : null, 'grade' => $filters['grade'] !== '' ? $filters['grade'] : null, 'min_price' => $filters['min_price'], 'max_price' => $filters['max_price'], 'min_quantity' => $filters['min_quantity'], 'sort_key' => $filters['sort']]
    );
    audit_log($userId, 'marketplace_filter_updated', 'saved_marketplace_filters', $filterId, ['name' => $name, 'is_default' => !empty($filter['is_default'])]);
}

function default_marketplace_filter_for_user(int $userId): ?array
{
    if ($userId < 1 || !default_saved_marketplace_filter_migration_is_available()) {
        return null;
    }
    return fetch_one('SELECT * FROM saved_marketplace_filters WHERE user_id = :user_id AND is_default = 1 ORDER BY updated_at DESC, id DESC LIMIT 1', ['user_id' => $userId]);
}

function set_default_marketplace_filter(int $userId, int $filterId): void
{
    if ($userId < 1 || $filterId < 1 || !default_saved_marketplace_filter_migration_is_available()) {
        throw new RuntimeException('Default filters are not ready. Import database/migrations/20260825_add_default_saved_marketplace_filters.sql into the quetta_agrilink database, then refresh this page.');
    }
    $filter = fetch_one('SELECT id, name FROM saved_marketplace_filters WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $filterId, 'user_id' => $userId]);
    if ($filter === null) {
        throw new RuntimeException('The saved filter is not available.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE saved_marketplace_filters SET is_default = 0 WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $pdo->prepare('UPDATE saved_marketplace_filters SET is_default = 1 WHERE id = :id AND user_id = :user_id')->execute(['id' => $filterId, 'user_id' => $userId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    audit_log($userId, 'marketplace_filter_defaulted', 'saved_marketplace_filters', $filterId, ['name' => $filter['name']]);
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

function notify_default_marketplace_filter_matches(int $listingId, int $publisherId): int
{
    if ($listingId < 1 || !default_saved_marketplace_filter_migration_is_available()) {
        return 0;
    }
    $listing = fetch_one('SELECT pl.id, pl.title, pl.category_id, pl.grade, pl.quantity_available, pl.expected_price, l.district FROM produce_listings pl JOIN locations l ON l.id = pl.location_id WHERE pl.id = :id AND pl.status = "active" LIMIT 1', ['id' => $listingId]);
    if ($listing === null) {
        return 0;
    }
    $defaultFilters = fetch_all('SELECT sf.*, u.status AS user_status FROM saved_marketplace_filters sf JOIN users u ON u.id = sf.user_id WHERE sf.is_default = 1');
    $created = 0;
    foreach ($defaultFilters as $filter) {
        if ($filter['user_status'] !== 'active' || (int) $filter['user_id'] === $publisherId) {
            continue;
        }
        $matches = ($filter['category_id'] === null || (int) $filter['category_id'] === (int) $listing['category_id'])
            && ($filter['district'] === null || $filter['district'] === $listing['district'])
            && ($filter['grade'] === null || $filter['grade'] === $listing['grade'])
            && ($filter['min_price'] === null || (float) $listing['expected_price'] >= (float) $filter['min_price'])
            && ($filter['max_price'] === null || (float) $listing['expected_price'] <= (float) $filter['max_price'])
            && ($filter['min_quantity'] === null || (float) $listing['quantity_available'] >= (float) $filter['min_quantity']);
        if (!$matches) {
            continue;
        }
        if (!notification_delivery_enabled((int) $filter['user_id'], 'marketplace_filter_match')) {
            continue;
        }
        $existing = fetch_one('SELECT id FROM notifications WHERE user_id = :user_id AND type = :type AND entity_type = :entity_type AND entity_id = :entity_id LIMIT 1', ['user_id' => $filter['user_id'], 'type' => 'marketplace_filter_match', 'entity_type' => 'produce_listing', 'entity_id' => $listingId]);
        if ($existing !== null) {
            continue;
        }
        create_notification((int) $filter['user_id'], 'marketplace_filter_match', 'New listing matches your default filter', $listing['title'] . ' · Grade ' . $listing['grade'] . ' · ' . $listing['district'] . ' · Rs. ' . number_format((float) $listing['expected_price'], 0) . '/kg.', 'marketplace/listing.php?id=' . $listingId, 'produce_listing', $listingId);
        audit_log($publisherId, 'marketplace_filter_match_notified', 'produce_listings', $listingId, ['recipient_user_id' => (int) $filter['user_id'], 'saved_filter_id' => (int) $filter['id']]);
        $created++;
    }
    return $created;
}

function publish_produce_listing(int $farmerId, array $input): int
{
    if ($farmerId < 1) {
        throw new RuntimeException('Sign in as a farmer to publish produce availability.');
    }
    $title = normalize_text($input['title'] ?? '', 160);
    $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
    $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $locationId = filter_var($input['location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $grade = in_array($input['grade'] ?? '', ['A', 'B', 'C', 'Mixed'], true) ? $input['grade'] : '';
    $quantity = positive_decimal($input['quantity_available'] ?? null);
    $price = positive_decimal($input['expected_price'] ?? null);
    $minimumOrder = positive_decimal($input['minimum_order_quantity'] ?? null);
    $description = normalize_text($input['description'] ?? '', 1000);
    $parseDate = static function (mixed $value): ?string {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date === false ? null : $date->format('Y-m-d');
    };
    $harvestDate = $parseDate($input['harvest_date'] ?? null);
    $availableFrom = $parseDate($input['available_from'] ?? null);
    if ($titleLength < 3 || !$categoryId || !$locationId || $grade === '' || $quantity === null || $price === null || $minimumOrder === null || $minimumOrder > $quantity) {
        throw new RuntimeException('Provide a title, valid crop category and origin, grade, positive quantity and price, and a minimum order no larger than the available quantity.');
    }
    if ((trim((string) ($input['harvest_date'] ?? '')) !== '' && $harvestDate === null) || (trim((string) ($input['available_from'] ?? '')) !== '' && $availableFrom === null)) {
        throw new RuntimeException('Use valid harvest and availability dates.');
    }
    if (fetch_one('SELECT id FROM produce_categories WHERE id = :id AND is_active = 1 LIMIT 1', ['id' => $categoryId]) === null || fetch_one('SELECT id FROM locations WHERE id = :id LIMIT 1', ['id' => $locationId]) === null) {
        throw new RuntimeException('Choose an active crop category and a valid origin location.');
    }
    $statement = db()->prepare('INSERT INTO produce_listings (farmer_id, category_id, location_id, title, description, grade, quantity_available, expected_price, harvest_date, available_from, minimum_order_quantity, status, published_at) VALUES (:farmer_id, :category_id, :location_id, :title, :description, :grade, :quantity, :price, :harvest_date, :available_from, :minimum_order, "active", NOW())');
    $statement->execute(['farmer_id' => $farmerId, 'category_id' => $categoryId, 'location_id' => $locationId, 'title' => $title, 'description' => $description !== '' ? $description : null, 'grade' => $grade, 'quantity' => $quantity, 'price' => $price, 'harvest_date' => $harvestDate, 'available_from' => $availableFrom, 'minimum_order' => $minimumOrder]);
    $listingId = (int) db()->lastInsertId();
    audit_log($farmerId, 'produce_listing_published', 'produce_listings', $listingId, ['title' => $title]);
    try {
        notify_default_marketplace_filter_matches($listingId, $farmerId);
    } catch (Throwable $exception) {
        error_log('Marketplace filter matching notification failed: ' . $exception->getMessage());
    }
    return $listingId;
}

function update_produce_listing_status(int $farmerId, int $listingId, string $status): string
{
    $allowedStatuses = ['active', 'paused', 'sold_out'];
    if ($farmerId < 1 || $listingId < 1 || !in_array($status, $allowedStatuses, true)) {
        throw new RuntimeException('That listing status action is not available.');
    }
    $listing = fetch_one('SELECT id, title, status FROM produce_listings WHERE id = :id AND farmer_id = :farmer_id LIMIT 1', ['id' => $listingId, 'farmer_id' => $farmerId]);
    if ($listing === null) {
        throw new RuntimeException('That produce record is not available in your workspace.');
    }
    if ($listing['status'] === $status) {
        return $status;
    }
    execute_query('UPDATE produce_listings SET status = :status_value, published_at = CASE WHEN :status_for_publish = "active" AND published_at IS NULL THEN NOW() ELSE published_at END WHERE id = :id AND farmer_id = :farmer_id', ['status_value' => $status, 'status_for_publish' => $status, 'id' => $listingId, 'farmer_id' => $farmerId]);
    audit_log($farmerId, 'produce_listing_status_updated', 'produce_listings', $listingId, ['title' => $listing['title'], 'from' => $listing['status'], 'to' => $status]);
    return $status;
}

function update_produce_listing_quantity(int $farmerId, int $listingId, mixed $requestedQuantity): float
{
    $quantity = positive_decimal($requestedQuantity);
    if ($farmerId < 1 || $listingId < 1 || $quantity === null) {
        throw new RuntimeException('Provide a positive available quantity for this produce record.');
    }
    $listing = fetch_one('SELECT id, title, quantity_available, minimum_order_quantity FROM produce_listings WHERE id = :id AND farmer_id = :farmer_id LIMIT 1', ['id' => $listingId, 'farmer_id' => $farmerId]);
    if ($listing === null) {
        throw new RuntimeException('That produce record is not available in your workspace.');
    }
    if ($quantity < (float) $listing['minimum_order_quantity']) {
        throw new RuntimeException('Available quantity cannot be lower than the minimum order quantity of ' . number_format((float) $listing['minimum_order_quantity'], 2) . ' kg.');
    }
    if ((float) $listing['quantity_available'] === $quantity) {
        return $quantity;
    }
    execute_query('UPDATE produce_listings SET quantity_available = :quantity WHERE id = :id AND farmer_id = :farmer_id', ['quantity' => $quantity, 'id' => $listingId, 'farmer_id' => $farmerId]);
    audit_log($farmerId, 'produce_listing_quantity_updated', 'produce_listings', $listingId, ['title' => $listing['title'], 'from_kg' => (float) $listing['quantity_available'], 'to_kg' => $quantity]);
    return $quantity;
}

function find_listings(array $filters, int $limit = 24, int $offset = 0): array
{
    $where = ['pl.status = "active"'];
    $params = [];
    if ($filters['search'] !== '') {
        $where[] = '(pl.title LIKE :search_title OR pl.description LIKE :search_description OR pc.name LIKE :search_category)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params['search_title'] = $searchTerm;
        $params['search_description'] = $searchTerm;
        $params['search_category'] = $searchTerm;
    }
    if ($filters['category_id'] !== null) { $where[] = 'pl.category_id = :category_id'; $params['category_id'] = $filters['category_id']; }
    if ($filters['district'] !== '') { $where[] = 'l.district = :district'; $params['district'] = $filters['district']; }
    if ($filters['grade'] !== '') { $where[] = 'pl.grade = :grade'; $params['grade'] = $filters['grade']; }
    if ($filters['min_price'] !== null) { $where[] = 'pl.expected_price >= :min_price'; $params['min_price'] = $filters['min_price']; }
    if ($filters['max_price'] !== null) { $where[] = 'pl.expected_price <= :max_price'; $params['max_price'] = $filters['max_price']; }
    if ($filters['min_quantity'] !== null) { $where[] = 'pl.quantity_available >= :min_quantity'; $params['min_quantity'] = $filters['min_quantity']; }
    $limit = max(1, min($limit, 48)); $offset = max(0, min($offset, 10000));
    $sql = 'SELECT pl.id, pl.title, pl.grade, pl.quantity_available, pl.unit, pl.expected_price, pl.harvest_date, pl.minimum_order_quantity, pc.name AS category_name, l.district, u.full_name AS farmer_name, pi.file_path AS image_path FROM produce_listings pl JOIN produce_categories pc ON pc.id = pl.category_id JOIN locations l ON l.id = pl.location_id JOIN users u ON u.id = pl.farmer_id LEFT JOIN produce_images pi ON pi.listing_id = pl.id AND pi.is_primary = 1 WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $filters['order_by'] . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    return fetch_all($sql, $params);
}

function count_listings(array $filters): int
{
    $where = ['pl.status = "active"']; $params = [];
    if ($filters['search'] !== '') { $where[] = '(pl.title LIKE :search_title OR pl.description LIKE :search_description OR pc.name LIKE :search_category)'; $term = '%' . $filters['search'] . '%'; $params = ['search_title' => $term, 'search_description' => $term, 'search_category' => $term]; }
    if ($filters['category_id'] !== null) { $where[] = 'pl.category_id = :category_id'; $params['category_id'] = $filters['category_id']; }
    if ($filters['district'] !== '') { $where[] = 'l.district = :district'; $params['district'] = $filters['district']; }
    if ($filters['grade'] !== '') { $where[] = 'pl.grade = :grade'; $params['grade'] = $filters['grade']; }
    if ($filters['min_price'] !== null) { $where[] = 'pl.expected_price >= :min_price'; $params['min_price'] = $filters['min_price']; }
    if ($filters['max_price'] !== null) { $where[] = 'pl.expected_price <= :max_price'; $params['max_price'] = $filters['max_price']; }
    if ($filters['min_quantity'] !== null) { $where[] = 'pl.quantity_available >= :min_quantity'; $params['min_quantity'] = $filters['min_quantity']; }
    return (int) (fetch_one('SELECT COUNT(*) AS count FROM produce_listings pl JOIN produce_categories pc ON pc.id = pl.category_id JOIN locations l ON l.id = pl.location_id WHERE ' . implode(' AND ', $where), $params)['count'] ?? 0);
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
