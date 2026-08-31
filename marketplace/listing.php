<?php
/** Produce listing detail with transparent commercial facts and a protected offer path. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$listing = $listingId ? fetch_one(
    'SELECT pl.*, pc.name AS category_name, l.district, l.tehsil, l.area, u.full_name AS farmer_name, fp.farm_name, pi.file_path AS image_path
     FROM produce_listings pl
     JOIN produce_categories pc ON pc.id = pl.category_id
     JOIN locations l ON l.id = pl.location_id
     JOIN users u ON u.id = pl.farmer_id
     LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     LEFT JOIN produce_images pi ON pi.listing_id = pl.id AND pi.is_primary = 1
     WHERE pl.id = :id AND pl.status = "active"
     LIMIT 1',
    ['id' => $listingId]
) : null;

if ($listing !== null && !empty($listing['image_path'])) {
    $listing['image_path'] = app_url((string) $listing['image_path']);
}
if ($listing === null) {
    http_response_code(404);
    $pageTitle = 'Listing unavailable';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section"><div class="site-container listing-empty"><h2>This listing is unavailable.</h2><p>It may have been paused, sold out, or removed from the active marketplace.</p><a class="button button-primary" href="' . e(app_url('marketplace/index.php')) . '">Return to marketplace</a></div></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$user = current_user();
$isBuyer = $user !== null && $user['role_slug'] === 'buyer';
$isSaved = $isBuyer && fetch_one('SELECT id FROM favorites WHERE buyer_id = :buyer AND listing_id = :listing LIMIT 1', ['buyer' => $user['id'], 'listing' => $listing['id']]) !== null;
$farmerRating = fetch_one('SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE reviewed_user_id = :farmer AND status = "published"', ['farmer' => $listing['farmer_id']]);
$ratingLabel = (int) $farmerRating['review_count'] > 0
    ? number_format((float) $farmerRating['average_rating'], 1) . ' / 5 from ' . (int) $farmerRating['review_count'] . ' verified orders'
    : 'No verified-order rating yet';
$pageTitle = $listing['title'];
require __DIR__ . '/../includes/header.php';
?>
<div class="public-commerce-page marketplace-page listing-detail-page">
<section class="commerce-page-hero commerce-page-hero-compact">
    <div class="site-container commerce-page-hero-grid">
        <div class="commerce-page-hero-copy">
            <a class="eyebrow" href="<?= e(app_url('marketplace/index.php')) ?>">← Produce marketplace</a>
            <h1><?= e($listing['title']) ?></h1>
            <p><?= e($listing['district']) ?>, Balochistan · Listed by <?= e($listing['farmer_name']) ?><?= $listing['farm_name'] ? ' · ' . e($listing['farm_name']) : '' ?></p>
            <div class="commerce-hero-tags"><span>Grade <?= e($listing['grade']) ?></span><span><?= e($listing['category_name']) ?></span><span><?= e($ratingLabel) ?></span></div>
        </div>
        <aside class="commerce-hero-register listing-hero-register" aria-label="Listing commercial summary"><div class="commerce-register-heading"><span>Active produce record</span><strong>Terms at a glance</strong></div><div class="commerce-register-metrics"><article><strong>Rs. <?= number_format((float) $listing['expected_price'], 0) ?></strong><span>Expected / <?= e($listing['unit']) ?></span></article><article><strong><?= number_format((float) $listing['quantity_available'], 0) ?></strong><span><?= e($listing['unit']) ?> available</span></article><article><strong><?= number_format((float) $listing['minimum_order_quantity'], 0) ?></strong><span>Minimum <?= e($listing['unit']) ?></span></article></div></aside>
    </div>
</section>
<section class="section listing-detail-section">
    <div class="site-container detail-layout">
        <aside class="listing-visual-panel <?= !empty($listing['image_path']) ? 'has-listing-image' : '' ?>"<?php if (!empty($listing['image_path'])): ?> style="background-image:url('<?= e($listing['image_path']) ?>')"<?php endif; ?> aria-label="Produce record context for <?= e($listing['title']) ?>">
            <div class="listing-visual-content"><span>Produce record</span><strong><?= e($listing['category_name']) ?></strong><p>Grade <?= e($listing['grade']) ?> · <?= e($listing['district']) ?> origin</p></div>
            <div class="listing-visual-status"><span>Current availability</span><strong><?= number_format((float) $listing['quantity_available'], 0) ?> <?= e($listing['unit']) ?></strong></div>
        </aside>
        <div class="detail-summary">
            <span class="badge">Grade <?= e($listing['grade']) ?></span>
            <h2><?= e($listing['title']) ?></h2>
            <p><?= e($listing['description'] ?: 'The farmer has not added a description for this listing.') ?></p>
            <div class="detail-facts">
                <div><span>Expected price</span><strong>Rs. <?= number_format((float) $listing['expected_price'], 0) ?>/<?= e($listing['unit']) ?></strong></div>
                <div><span>Available quantity</span><strong><?= e((string) $listing['quantity_available']) ?> <?= e($listing['unit']) ?></strong></div>
                <div><span>Minimum order</span><strong><?= e((string) $listing['minimum_order_quantity']) ?> <?= e($listing['unit']) ?></strong></div>
                <div><span>Harvest date</span><strong><?= $listing['harvest_date'] ? e(date('j M Y', strtotime($listing['harvest_date']))) : 'Not stated' ?></strong></div>
                <div><span>Available from</span><strong><?= $listing['available_from'] ? e(date('j M Y', strtotime($listing['available_from']))) : 'Now' ?></strong></div>
                <div><span>Origin</span><strong><?= e($listing['district']) ?><?= $listing['tehsil'] ? ', ' . e($listing['tehsil']) : '' ?></strong></div>
            </div>
            <?php if ($isBuyer): ?>
                <button class="favorite-button <?= $isSaved ? 'is-saved' : '' ?>" type="button" data-favorite-toggle="<?= (int) $listing['id'] ?>" data-endpoint="<?= e(app_url('ajax/favorites/toggle.php')) ?>"><?= $isSaved ? 'Saved to favourites' : 'Save listing' ?></button>
                <div class="action-panel">
                    <h2>Send a purchase offer</h2>
                    <p>Offer a quantity and price. The farmer can accept, reject, or return a revised proposal.</p>
                    <form action="<?= e(app_url('ajax/offers/create.php')) ?>" method="post" class="inline-form" data-ajax-form>
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <div class="form-field"><label for="quantity">Quantity (<?= e($listing['unit']) ?>)</label><input id="quantity" name="quantity" type="number" min="<?= e((string) $listing['minimum_order_quantity']) ?>" max="<?= e((string) $listing['quantity_available']) ?>" step="0.01" required></div>
                        <div class="form-field"><label for="offered_price">Price / <?= e($listing['unit']) ?> (Rs.)</label><input id="offered_price" name="offered_price" type="number" min="1" step="0.01" required></div>
                        <button class="button button-primary" type="submit">Send offer</button>
                        <div data-form-feedback style="grid-column:1/-1"></div>
                    </form>
                </div>
            <?php elseif ($user === null): ?>
                <div class="action-panel">
                    <h2>Ready to purchase?</h2>
                    <p>Sign in as a buyer to submit a documented offer and keep the discussion on the platform.</p>
                    <a class="button button-primary" href="<?= e(app_url('auth/login.php')) ?>">Sign in to offer</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
