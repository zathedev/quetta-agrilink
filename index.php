<?php
/** Product homepage: current market activity and core trade tasks lead the experience. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Quetta AgriLink';
$pageDescription = 'Find produce, storage, transport, and local price records in Quetta.';
$latestListings = fetch_all('SELECT pl.id, pl.title, pl.grade, pl.quantity_available, pl.unit, pl.expected_price, l.district FROM produce_listings pl JOIN locations l ON l.id = pl.location_id WHERE pl.status = "active" ORDER BY pl.created_at DESC LIMIT 4');
$prices = fetch_all('SELECT mp.average_price, mp.unit, pc.name AS category_name FROM market_prices mp JOIN produce_categories pc ON pc.id = mp.category_id ORDER BY mp.price_date DESC, mp.created_at DESC LIMIT 4');
$facilities = fetch_all('SELECT sf.id,sf.name,sf.available_capacity_kg,sf.price_per_kg_day,l.district FROM storage_facilities sf JOIN locations l ON l.id=sf.location_id WHERE sf.status="active" ORDER BY sf.available_capacity_kg DESC LIMIT 3');
$metrics = [
    'listings' => (int) (fetch_one('SELECT COUNT(*) AS count FROM produce_listings WHERE status = "active"')['count'] ?? 0),
    'facilities' => (int) (fetch_one('SELECT COUNT(*) AS count FROM storage_facilities WHERE status = "active"')['count'] ?? 0),
    'providers' => (int) (fetch_one('SELECT COUNT(*) AS count FROM transport_providers tp JOIN users u ON u.id = tp.user_id WHERE u.status = "active"')['count'] ?? 0),
];
require __DIR__ . '/includes/header.php';
?>
<main class="desk-home flagship-home">
    <section class="commerce-hero">
        <div class="desk-wrap commerce-hero-grid">
            <div class="commerce-hero-copy">
                <p class="eyebrow">Agricultural commerce infrastructure</p>
                <h1>Move produce from farm to market with fewer handoffs.</h1>
                <p>Quetta AgriLink brings supply, storage, transport, price context, and trade records into one practical operating platform for Balochistan.</p>
                <div class="desk-hero-actions">
                    <a class="button button-primary" href="<?= e(app_url('marketplace/index.php')) ?>">Browse available produce</a>
                    <a class="button button-outline" href="<?= e(app_url('auth/register.php')) ?>">Create a business account</a>
                </div>
                <ul class="commerce-assurances" aria-label="Platform assurances">
                    <li>Role-scoped workspaces</li>
                    <li>Recorded commercial terms</li>
                    <li>Local operational support</li>
                </ul>
            </div>
            <aside class="network-overview" aria-labelledby="network-overview-title">
                <header><div><p>Live network overview</p><h2 id="network-overview-title">Current availability</h2></div><span>Quetta · Balochistan</span></header>
                <div class="network-metrics">
                    <article><strong><?= $metrics['listings'] ?></strong><span>Active produce listings</span></article>
                    <article><strong><?= $metrics['facilities'] ?></strong><span>Storage facilities</span></article>
                    <article><strong><?= $metrics['providers'] ?></strong><span>Transport providers</span></article>
                </div>
                <div class="network-overview-foot"><span>Marketplace records update as operators publish availability.</span><a href="<?= e(app_url('market-prices.php')) ?>">Review market intelligence</a></div>
            </aside>
        </div>
    </section>

    <div class="desk-wrap">
        <section class="desk-section trade-paths" aria-labelledby="trade-paths-title">
            <div class="desk-section-head"><div><p class="eyebrow">Core workflows</p><h2 id="trade-paths-title">Start with the work in front of you</h2></div><p>Direct access to the records and services used across the post-harvest chain.</p></div>
            <div class="desk-task-grid">
                <article class="desk-task"><span class="desk-task-mark">01</span><h3>Source produce</h3><p>Compare origin, grade, available quantity, minimum order, and expected price.</p><a href="<?= e(app_url('marketplace/index.php')) ?>">Open produce marketplace</a></article>
                <article class="desk-task"><span class="desk-task-mark">02</span><h3>Secure storage</h3><p>Evaluate location, compatible crops, available capacity, and daily rates.</p><a href="<?= e(app_url('storage/index.php')) ?>">Compare storage capacity</a></article>
                <article class="desk-task"><span class="desk-task-mark">03</span><h3>Arrange logistics</h3><p>Match load requirements with vehicle capacity, service area, and pickup timing.</p><a href="<?= e(app_url('transport/index.php')) ?>">Find transport services</a></article>
                <article class="desk-task"><span class="desk-task-mark">04</span><h3>Assess the market</h3><p>Use approved price records as context before negotiating commercial terms.</p><a href="<?= e(app_url('market-prices.php')) ?>">View price records</a></article>
            </div>
        </section>

        <section class="desk-section" aria-labelledby="latest-produce-title">
            <div class="desk-section-head"><div><p class="eyebrow">Supply register</p><h2 id="latest-produce-title">Latest produce availability</h2><p>Recently published supply from active farmer accounts.</p></div><a class="button button-outline" href="<?= e(app_url('marketplace/index.php')) ?>">View all produce</a></div>
            <div class="desk-market"><div class="ledger"><div class="ledger-row ledger-head"><span>Produce and origin</span><span>Grade</span><span>Available</span><span>Expected price</span><span></span></div><?php if ($latestListings === []): ?><div class="ledger-row"><div class="commodity"><div class="commodity-mark"></div><div><h3>No produce listings yet</h3><p>New listings appear here when growers publish them.</p></div></div><span></span><span></span><span></span><span></span></div><?php else: foreach ($latestListings as $index => $listing): ?><div class="ledger-row"><div class="commodity"><div class="commodity-mark <?= ['', 'grape', 'apricot', 'pomegranate'][$index % 4] ?>"></div><div><h3><?= e($listing['title']) ?></h3><p><?= e($listing['district']) ?>, Balochistan</p></div></div><span><span class="badge">Grade <?= e($listing['grade']) ?></span></span><span><?= e((string) $listing['quantity_available']) ?> <?= e($listing['unit']) ?></span><span class="price">Rs. <?= number_format((float) $listing['expected_price'], 0) ?>/<?= e($listing['unit']) ?></span><span class="list-action"><a class="button button-quiet" href="<?= e(app_url('marketplace/listing.php?id=' . (int) $listing['id'])) ?>">View details</a></span></div><?php endforeach; endif; ?></div></div>
        </section>

        <section class="desk-section operational-grid-section">
            <div class="operational-grid">
                <div>
                    <div class="desk-section-head"><div><p class="eyebrow">Capacity register</p><h2>Available cold storage</h2><p>Published capacity and current provider rates.</p></div><a class="text-link" href="<?= e(app_url('storage/index.php')) ?>">View all facilities</a></div>
                    <div class="capacity-list"><?php if ($facilities === []): ?><article><div><h3>No active facilities yet</h3><p>Provider capacity appears here after publication.</p></div></article><?php else: foreach ($facilities as $facility): ?><article><div><h3><?= e($facility['name']) ?></h3><p><?= e($facility['district']) ?> · <?= number_format((float) $facility['available_capacity_kg'], 0) ?> kg available</p></div><strong>Rs. <?= number_format((float) $facility['price_per_kg_day'], 2) ?><small>/kg/day</small></strong></article><?php endforeach; endif; ?></div>
                </div>
                <aside class="price-register-summary">
                    <div class="desk-section-head"><div><p class="eyebrow">Market intelligence</p><h2>Recent price records</h2><p>Reference data for informed commercial discussions.</p></div></div>
                    <div class="desk-price-lines"><?php if ($prices === []): ?><div class="desk-price-line"><span>Market prices</span><strong>Awaiting records</strong></div><?php else: foreach ($prices as $price): ?><div class="desk-price-line"><span><?= e($price['category_name']) ?></span><strong>Rs. <?= number_format((float) $price['average_price'], 0) ?>/<?= e($price['unit']) ?></strong></div><?php endforeach; endif; ?></div>
                    <a class="button button-outline" href="<?= e(app_url('market-prices.php')) ?>">Open price register</a>
                </aside>
            </div>
        </section>

        <section class="desk-section audience-section" aria-labelledby="audience-title">
            <div class="desk-section-head"><div><p class="eyebrow">Connected operations</p><h2 id="audience-title">One record trail across the transaction</h2><p>Each participant sees the tools and decisions relevant to their role.</p></div></div>
            <div class="audience-grid">
                <article><span>For suppliers</span><h3>Publish and manage harvest supply</h3><p>Maintain availability, negotiate offers, track sales, arrange downstream services, and preserve transaction history.</p><a href="<?= e(app_url('auth/register.php?role=farmer')) ?>">Create a farmer account</a></article>
                <article><span>For procurement</span><h3>Source with clearer commercial context</h3><p>Filter active supply, compare specifications, save buying criteria, negotiate terms, and follow orders through completion.</p><a href="<?= e(app_url('auth/register.php?role=buyer')) ?>">Create a buyer account</a></article>
                <article><span>For operators</span><h3>Coordinate capacity and movement</h3><p>Manage facilities, fleet, booking requests, assignments, service status, and accountable support records.</p><a href="<?= e(app_url('how-it-works.php')) ?>">Review platform workflows</a></article>
            </div>
        </section>

        <section class="desk-section"><div class="desk-cta"><div><p class="eyebrow">Business workspace</p><h2>Bring your next agricultural trade workflow into one system.</h2><p>Create the account that matches your role, or sign in to continue existing work.</p></div><div><a class="button button-primary" href="<?= e(app_url('auth/register.php')) ?>">Create account</a><a class="button button-outline" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a></div></div></section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
