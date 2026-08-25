<?php
/** Orchard Ledger marketplace page: a filter rail, saved buying briefs, and commodity-led results. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/marketplace.php';

$marketplaceUser = current_user();
$savedFiltersReady = saved_marketplace_filters_migration_is_available();
$defaultFiltersReady = default_saved_marketplace_filter_migration_is_available();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $marketplaceUser !== null && $defaultFiltersReady && ($_GET['default'] ?? '') === '1') {
    $defaultFilter = default_marketplace_filter_for_user((int) $marketplaceUser['id']);
    if ($defaultFilter !== null) {
        $_GET = array_merge($_GET, [
            'category_id' => $defaultFilter['category_id'], 'district' => $defaultFilter['district'], 'grade' => $defaultFilter['grade'],
            'min_price' => $defaultFilter['min_price'], 'max_price' => $defaultFilter['max_price'], 'min_quantity' => $defaultFilter['min_quantity'], 'sort' => $defaultFilter['sort_key'],
        ]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marketplaceUser = require_login();
    verify_csrf();
    $action = normalize_text($_POST['saved_filter_action'] ?? '', 30);
    $redirectFilters = marketplace_filters($_POST);
    $redirectPath = 'marketplace/index.php';
    try {
        if ($action === 'save') {
            save_marketplace_filter((int) $marketplaceUser['id'], (string) ($_POST['filter_name'] ?? ''), $redirectFilters);
            flash('success', 'Marketplace filter saved to your account. Choose it as the default if you want matching-listing alerts.');
        } elseif ($action === 'delete') {
            $filterId = filter_input(INPUT_POST, 'filter_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$filterId) { throw new RuntimeException('Choose a valid saved filter to remove.'); }
            delete_marketplace_filter((int) $marketplaceUser['id'], (int) $filterId);
            flash('success', 'Saved marketplace filter removed.');
        } elseif ($action === 'set_default') {
            $filterId = filter_input(INPUT_POST, 'filter_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$filterId) { throw new RuntimeException('Choose a valid saved filter to make the default.'); }
            set_default_marketplace_filter((int) $marketplaceUser['id'], (int) $filterId);
            flash('success', 'Default buying brief updated. Matching new listings will appear in your account alerts.');
            $redirectPath .= '?default=1';
        } else {
            throw new RuntimeException('That saved-filter action is not available.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The saved-filter action could not be completed.');
    }
    if ($redirectPath === 'marketplace/index.php') {
        $query = saved_marketplace_filter_query(['category_id' => $redirectFilters['category_id'], 'district' => $redirectFilters['district'], 'grade' => $redirectFilters['grade'], 'min_price' => $redirectFilters['min_price'], 'max_price' => $redirectFilters['max_price'], 'min_quantity' => $redirectFilters['min_quantity'], 'sort_key' => $redirectFilters['sort']]);
        $redirectPath .= $query !== '' ? '?' . $query : '';
    }
    redirect($redirectPath);
}

$filters = marketplace_filters($_GET);
$listings = find_listings($filters);
$categories = fetch_all('SELECT id, name FROM produce_categories WHERE is_active = 1 ORDER BY name');
$districts = fetch_all('SELECT DISTINCT district FROM locations ORDER BY district');
$savedFilters = $marketplaceUser === null ? [] : saved_marketplace_filters_for_user((int) $marketplaceUser['id']);
$defaultFilter = $marketplaceUser === null ? null : default_marketplace_filter_for_user((int) $marketplaceUser['id']);
$pageTitle = 'Produce Marketplace';
$pageDescription = 'Search active agricultural produce listings from growers across Quetta and Balochistan.';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-intro"><div class="site-container"><span class="eyebrow" style="color:var(--clay)">Produce marketplace</span><h1>Compare available produce before you call.</h1><p>Search listings by crop, origin, grade, expected price, and quantity. Every active entry records the information needed for a practical first conversation.</p></div></section>
<section class="section"><div class="site-container market-layout">
    <aside class="market-filter-rail">
        <form class="filter-panel" method="get" data-marketplace-filter data-endpoint="<?= e(app_url('ajax/marketplace/search.php')) ?>">
            <h2>Refine availability</h2>
            <div class="form-field category-filter-field"><label for="category_id">Crop category</label><select id="category_id" name="category_id"><option value="">All crop categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select><span class="form-help">Choose a crop family to refresh the active commodity ledger.</span></div>
            <div class="form-field"><label for="district">Location</label><select id="district" name="district"><option value="">All districts</option><?php foreach ($districts as $district): ?><option value="<?= e($district['district']) ?>" <?= $filters['district'] === $district['district'] ? 'selected' : '' ?>><?= e($district['district']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="grade">Grade</label><select id="grade" name="grade"><option value="">Any grade</option><?php foreach (['A', 'B', 'C', 'Mixed'] as $grade): ?><option value="<?= $grade ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>>Grade <?= $grade ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="min_price">Minimum price (Rs./kg)</label><input id="min_price" name="min_price" type="number" min="1" step="0.01" value="<?= e((string) ($filters['min_price'] ?? '')) ?>"></div>
            <div class="form-field"><label for="max_price">Maximum price (Rs./kg)</label><input id="max_price" name="max_price" type="number" min="1" step="0.01" value="<?= e((string) ($filters['max_price'] ?? '')) ?>"></div>
            <div class="form-field"><label for="min_quantity">Minimum quantity (kg)</label><input id="min_quantity" name="min_quantity" type="number" min="1" step="0.01" value="<?= e((string) ($filters['min_quantity'] ?? '')) ?>"></div>
            <div class="form-field"><label for="sort">Sort listings</label><select id="sort" name="sort"><option value="recent" <?= $filters['sort'] === 'recent' ? 'selected' : '' ?>>Most recent</option><option value="price_low" <?= $filters['sort'] === 'price_low' ? 'selected' : '' ?>>Price: low to high</option><option value="price_high" <?= $filters['sort'] === 'price_high' ? 'selected' : '' ?>>Price: high to low</option><option value="quantity_high" <?= $filters['sort'] === 'quantity_high' ? 'selected' : '' ?>>Highest quantity</option></select><span class="form-help">Category and sorting changes refresh automatically.</span></div>
            <button class="button button-primary" type="submit">Update results</button>
        </form>
        <?php if ($marketplaceUser === null): ?>
            <div class="saved-filter-panel"><span class="eyebrow">Saved discovery</span><h2>Keep a buying brief</h2><p>Sign in to save a produce search and return to the same market criteria later.</p><a class="button button-outline" href="<?= e(app_url('auth/login.php')) ?>">Sign in to save filters</a></div>
        <?php elseif (!$savedFiltersReady): ?>
            <div class="saved-filter-panel"><span class="eyebrow">Saved discovery</span><h2>Saved filters need setup</h2><p>Import <code>database/migrations/20260825_add_saved_marketplace_filters.sql</code> into <code>quetta_agrilink</code>, then refresh this page.</p></div>
        <?php else: ?>
            <div class="saved-filter-panel"><span class="eyebrow">Saved discovery</span><h2>Keep this buying brief</h2><p>Save the current crop, origin, price, quantity, and sorting criteria to your account.</p>
                <?php if (!$defaultFiltersReady): ?><p class="form-help">To choose a default filter and receive matching-listing alerts, import <code>database/migrations/20260825_add_default_saved_marketplace_filters.sql</code>.</p><?php elseif ($defaultFilter !== null): ?><a class="default-filter-link" href="<?= e(app_url('marketplace/index.php?default=1')) ?>">Use default: <?= e($defaultFilter['name']) ?></a><?php endif; ?>
                <form class="saved-filter-form" method="post" data-saved-marketplace-filter><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="saved_filter_action" value="save"><?php foreach (['category_id', 'district', 'grade', 'min_price', 'max_price', 'min_quantity', 'sort'] as $key): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) ($filters[$key] ?? '')) ?>" data-saved-filter-value><?php endforeach; ?><div class="form-field"><label for="filter-name">Filter name</label><input id="filter-name" name="filter_name" type="text" maxlength="80" required placeholder="Example: Grade A onions"></div><button class="button button-primary" type="submit">Save current filter</button></form>
                <div class="saved-filter-list"><?php if ($savedFilters === []): ?><p class="saved-filter-empty">No saved filters yet.</p><?php else: foreach ($savedFilters as $savedFilter): ?><article class="saved-filter-item"><div><strong><?= e($savedFilter['name']) ?><?= !empty($savedFilter['is_default']) ? ' · Default' : '' ?></strong><span><?= e($savedFilter['category_name'] ?? 'All crops') ?> · <?= e($savedFilter['district'] ?? 'All districts') ?> · <?= e(str_replace('_', ' ', $savedFilter['sort_key'])) ?></span></div><div class="saved-filter-actions"><a class="button button-quiet" href="<?= e(app_url('marketplace/index.php?' . saved_marketplace_filter_query($savedFilter))) ?>">Apply</a><?php if ($defaultFiltersReady && empty($savedFilter['is_default'])): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="saved_filter_action" value="set_default"><input type="hidden" name="filter_id" value="<?= (int) $savedFilter['id'] ?>"><button class="text-action default-action" type="submit">Make default</button></form><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="saved_filter_action" value="delete"><input type="hidden" name="filter_id" value="<?= (int) $savedFilter['id'] ?>"><button class="text-action" type="submit">Remove</button></form></div></article><?php endforeach; endif; ?></div>
            </div>
        <?php endif; ?>
    </aside>
    <div><div class="results-bar"><p data-marketplace-feedback aria-live="polite"><?= count($listings) ?> active listing<?= count($listings) === 1 ? '' : 's' ?> shown. Filtering updates without a page reload.</p><a class="button button-outline" href="<?= e(app_url('farmer/listings.php')) ?>">Publish produce</a></div><div class="marketplace-result-wrap"><div class="marketplace-loading" data-marketplace-loading aria-live="polite" aria-hidden="true"><span></span>Updating commodity ledger…</div><div class="listing-grid" data-marketplace-results><?= listing_cards_html($listings) ?></div></div><p class="pagination-note">Marketplace pages are paginated as inventory grows. Current results show the most relevant active entries first.</p></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
