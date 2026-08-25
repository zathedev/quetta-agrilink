<?php
/** Orchard Ledger farmer publishing: one accountable availability record also drives default-filter match alerts. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';
require_once __DIR__ . '/../includes/marketplace.php';

$farmer = require_role(['farmer']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $listingId = publish_produce_listing((int) $farmer['id'], $_POST);
        flash('success', 'Listing #' . $listingId . ' is now active. Matching default-filter alerts were recorded for eligible accounts.');
    } catch (Throwable $exception) {
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The listing could not be published.');
    }
    redirect('farmer/listings.php');
}

$categories = fetch_all('SELECT id, name FROM produce_categories WHERE is_active = 1 ORDER BY name');
$locations = fetch_all('SELECT id, district, tehsil, area FROM locations ORDER BY district, tehsil, area');
$listings = fetch_all('SELECT pl.*, pc.name AS category_name, l.district FROM produce_listings pl JOIN produce_categories pc ON pc.id = pl.category_id JOIN locations l ON l.id = pl.location_id WHERE pl.farmer_id = :farmer ORDER BY pl.created_at DESC LIMIT 30', ['farmer' => $farmer['id']]);
workspace_open('Publish produce availability', 'listings');
?>
<section class="workspace-section farmer-publication-layout">
    <div class="workspace-section-header"><div><h2>Publish an accountable supply record</h2><p>Once published, this record becomes visible in the marketplace and alerts accounts whose default buying brief matches its terms.</p></div></div>
    <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
    <form class="attachment-form form-grid" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-field"><label for="listing-title">Produce title</label><input id="listing-title" name="title" maxlength="160" required placeholder="Example: Pishin Grade A Apples"></div>
        <div class="form-field"><label for="listing-category">Crop category</label><select id="listing-category" name="category_id" required><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="listing-location">Origin location</label><select id="listing-location" name="location_id" required><option value="">Choose an origin</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['district'] . ' · ' . $location['tehsil'] . ' · ' . $location['area']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="listing-grade">Grade</label><select id="listing-grade" name="grade" required><?php foreach (['A', 'B', 'C', 'Mixed'] as $grade): ?><option value="<?= $grade ?>">Grade <?= $grade ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="listing-quantity">Available quantity (kg)</label><input id="listing-quantity" name="quantity_available" type="number" min="1" step="0.01" required></div>
        <div class="form-field"><label for="listing-price">Expected price (Rs./kg)</label><input id="listing-price" name="expected_price" type="number" min="1" step="0.01" required></div>
        <div class="form-field"><label for="listing-minimum">Minimum order (kg)</label><input id="listing-minimum" name="minimum_order_quantity" type="number" min="1" step="0.01" required></div>
        <div class="form-field"><label for="listing-harvest">Harvest date</label><input id="listing-harvest" name="harvest_date" type="date"></div>
        <div class="form-field"><label for="listing-available">Available from</label><input id="listing-available" name="available_from" type="date"></div>
        <div class="form-field"><label for="listing-description">Supply notes</label><textarea id="listing-description" name="description" rows="4" maxlength="1000" placeholder="Packing, maturity, collection, or trade terms buyers should know."></textarea></div>
        <div class="form-actions"><span class="form-help">Publication alerts only use saved filters marked as a default buying brief.</span><button class="button button-primary" type="submit">Publish availability</button></div>
    </form>
</section>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Your recent supply records</h2><p>Active records appear in the marketplace immediately; paused or sold-out records remain accountable in this register.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Produce</th><th>Origin</th><th>Grade</th><th>Available</th><th>Expected price</th><th>Status</th></tr></thead><tbody><?php if ($listings === []): ?><tr><td colspan="6">No supply records have been published yet.</td></tr><?php else: foreach ($listings as $listing): ?><tr><td><?= e($listing['title']) ?><span class="table-muted"> · <?= e($listing['category_name']) ?></span></td><td><?= e($listing['district']) ?></td><td>Grade <?= e($listing['grade']) ?></td><td><?= e(number_format((float) $listing['quantity_available'], 0)) ?> <?= e($listing['unit']) ?></td><td>Rs. <?= e(number_format((float) $listing['expected_price'], 0)) ?>/<?= e($listing['unit']) ?></td><td><span class="status-pill <?= e($listing['status']) ?>"><?= e(ucfirst($listing['status'])) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
