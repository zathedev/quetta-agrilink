<?php
/** Product homepage: current market activity and core trade tasks lead the experience. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Quetta AgriLink';
$pageDescription = 'Find produce, storage, transport, and local price records in Quetta.';
$pageStylesheets = ['assets/css/home.css'];
$preloadImage = 'assets/images/quetta-market-hero-optimized.png';
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
<!-- Homepage conversion path: browse verified supply first, then use storage, transport, and price records to complete the trade. -->
<main class="agrilink-home">
    <section class="home-hero" aria-labelledby="home-hero-title">
        <figure class="home-hero-media" aria-hidden="true">
            <img src="<?= e(app_url('assets/images/quetta-market-hero-optimized.png')) ?>" alt="" width="1824" height="864" fetchpriority="high">
        </figure>
        <div class="home-shell home-hero-layout">
            <div class="home-hero-copy">
                <p class="home-kicker">Quetta’s agricultural trade network</p>
                <h1 id="home-hero-title">Move produce from <span>farm to market</span> with fewer handoffs.</h1>
                <p class="home-hero-lead">Quetta AgriLink brings supply, storage, transport, price context, and trade records into one practical operating platform for Balochistan.</p>
                <div class="home-hero-actions">
                    <a class="home-action home-action-primary" href="<?= e(app_url('marketplace/index.php')) ?>">Browse available produce <span aria-hidden="true">→</span></a>
                    <a class="home-action home-action-secondary" href="<?= e(app_url('auth/register.php')) ?>">Create a business account</a>
                </div>
                <ul class="home-assurances" aria-label="Platform assurances">
                    <li>Role-scoped workspaces</li>
                    <li>Recorded commercial terms</li>
                    <li>Local operational support</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="home-pulse" aria-labelledby="network-overview-title">
        <div class="home-shell home-pulse-grid">
            <header class="home-pulse-heading">
                <p>Live network overview</p>
                <h2 id="network-overview-title">Current availability</h2>
                <span>Quetta · Balochistan</span>
            </header>
            <div class="home-pulse-metrics">
                <article><strong><?= $metrics['listings'] ?></strong><span>Active produce listings</span></article>
                <article><strong><?= $metrics['facilities'] ?></strong><span>Storage facilities</span></article>
                <article><strong><?= $metrics['providers'] ?></strong><span>Transport providers</span></article>
            </div>
            <div class="home-pulse-link"><span>Records update as operators publish availability.</span><a href="<?= e(app_url('market-prices.php')) ?>">Review market intelligence <span aria-hidden="true">→</span></a></div>
        </div>
    </section>

    <section class="home-section home-workflows" aria-labelledby="trade-paths-title">
        <div class="home-shell">
            <div class="home-section-intro home-section-intro-split">
                <div><p class="home-kicker">Core workflows</p><h2 id="trade-paths-title">Start with the work in front of you</h2></div>
                <p>Direct access to the records and services used across the post-harvest chain.</p>
            </div>
            <div class="home-task-grid">
                <article class="home-task home-task-lead"><span class="home-task-number">01</span><span class="home-task-label">Start here</span><h3>Source produce</h3><p>Compare origin, grade, available quantity, minimum order, and expected price.</p><a href="<?= e(app_url('marketplace/index.php')) ?>">Open produce marketplace <span aria-hidden="true">→</span></a></article>
                <article class="home-task"><span class="home-task-number">02</span><h3>Secure storage</h3><p>Evaluate location, compatible crops, available capacity, and daily rates.</p><a href="<?= e(app_url('storage/index.php')) ?>">Compare storage capacity <span aria-hidden="true">→</span></a></article>
                <article class="home-task"><span class="home-task-number">03</span><h3>Arrange logistics</h3><p>Match load requirements with vehicle capacity, service area, and pickup timing.</p><a href="<?= e(app_url('transport/index.php')) ?>">Find transport services <span aria-hidden="true">→</span></a></article>
                <article class="home-task"><span class="home-task-number">04</span><h3>Assess the market</h3><p>Use approved price records as context before negotiating commercial terms.</p><a href="<?= e(app_url('market-prices.php')) ?>">View price records <span aria-hidden="true">→</span></a></article>
            </div>
        </div>
    </section>

    <section class="home-section home-supply" aria-labelledby="latest-produce-title">
        <div class="home-shell">
            <div class="home-section-intro">
                <div><p class="home-kicker">Supply register</p><h2 id="latest-produce-title">Latest produce availability</h2><p>Recently published supply from active farmer accounts.</p></div>
                <a class="home-action home-action-outline" href="<?= e(app_url('marketplace/index.php')) ?>">View all produce</a>
            </div>
            <div class="home-ledger" role="region" aria-label="Latest produce listings" tabindex="0">
                <div class="home-ledger-row home-ledger-head"><span>Produce and origin</span><span>Grade</span><span>Available</span><span>Expected price</span><span></span></div>
                <?php if ($latestListings === []): ?>
                    <div class="home-ledger-row"><div class="home-commodity"><span class="home-produce-mark" aria-hidden="true"></span><div><h3>No produce listings yet</h3><p>New listings appear here when growers publish them.</p></div></div><span></span><span></span><span></span><span></span></div>
                <?php else: foreach ($latestListings as $index => $listing): ?>
                    <div class="home-ledger-row"><div class="home-commodity"><span class="home-produce-mark home-produce-mark-<?= $index % 4 ?>" aria-hidden="true"></span><div><h3><?= e($listing['title']) ?></h3><p><?= e($listing['district']) ?>, Balochistan</p></div></div><span><span class="home-grade">Grade <?= e($listing['grade']) ?></span></span><span><?= e((string) $listing['quantity_available']) ?> <?= e($listing['unit']) ?></span><strong>Rs. <?= number_format((float) $listing['expected_price'], 0) ?>/<?= e($listing['unit']) ?></strong><span><a class="home-row-action" href="<?= e(app_url('marketplace/listing.php?id=' . (int) $listing['id'])) ?>">View details</a></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-operations" aria-label="Storage and market intelligence">
        <div class="home-shell home-operations-grid">
            <div class="home-capacity">
                <div class="home-section-intro home-section-intro-compact"><div><p class="home-kicker">Capacity register</p><h2>Available cold storage</h2><p>Published capacity and current provider rates.</p></div><a class="home-text-link" href="<?= e(app_url('storage/index.php')) ?>">View all facilities <span aria-hidden="true">→</span></a></div>
                <div class="home-capacity-list">
                    <?php if ($facilities === []): ?>
                        <article><div><h3>No active facilities yet</h3><p>Provider capacity appears here after publication.</p></div></article>
                    <?php else: foreach ($facilities as $facility): ?>
                        <article><div><h3><?= e($facility['name']) ?></h3><p><?= e($facility['district']) ?> · <?= number_format((float) $facility['available_capacity_kg'], 0) ?> kg available</p></div><strong>Rs. <?= number_format((float) $facility['price_per_kg_day'], 2) ?><small>/kg/day</small></strong></article>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <aside class="home-prices">
                <p class="home-kicker">Market intelligence</p>
                <h2>Recent price records</h2>
                <p>Reference data for informed commercial discussions.</p>
                <div class="home-price-list">
                    <?php if ($prices === []): ?><div><span>Market prices</span><strong>Awaiting records</strong></div><?php else: foreach ($prices as $price): ?><div><span><?= e($price['category_name']) ?></span><strong>Rs. <?= number_format((float) $price['average_price'], 0) ?>/<?= e($price['unit']) ?></strong></div><?php endforeach; endif; ?>
                </div>
                <a class="home-action home-action-dark" href="<?= e(app_url('market-prices.php')) ?>">Open price register <span aria-hidden="true">→</span></a>
            </aside>
        </div>
    </section>

    <section class="home-section home-audience" aria-labelledby="audience-title">
        <div class="home-shell home-audience-layout">
            <div class="home-audience-intro"><p class="home-kicker">Connected operations</p><h2 id="audience-title">One record trail across the transaction</h2><p>Each participant sees the tools and decisions relevant to their role.</p></div>
            <div class="home-role-list">
                <article><span>For suppliers</span><div><h3>Publish and manage harvest supply</h3><p>Maintain availability, negotiate offers, track sales, arrange downstream services, and preserve transaction history.</p></div><a href="<?= e(app_url('auth/register.php?role=farmer')) ?>">Create a farmer account <span aria-hidden="true">→</span></a></article>
                <article><span>For procurement</span><div><h3>Source with clearer commercial context</h3><p>Filter active supply, compare specifications, save buying criteria, negotiate terms, and follow orders through completion.</p></div><a href="<?= e(app_url('auth/register.php?role=buyer')) ?>">Create a buyer account <span aria-hidden="true">→</span></a></article>
                <article><span>For operators</span><div><h3>Coordinate capacity and movement</h3><p>Manage facilities, fleet, booking requests, assignments, service status, and accountable support records.</p></div><a href="<?= e(app_url('how-it-works.php')) ?>">Review platform workflows <span aria-hidden="true">→</span></a></article>
            </div>
        </div>
    </section>

    <section class="home-final">
        <div class="home-shell home-final-layout"><div><p class="home-kicker">Business workspace</p><h2>Bring your next agricultural trade workflow into one system.</h2><p>Create the account that matches your role, or sign in to continue existing work.</p></div><div><a class="home-action home-action-primary" href="<?= e(app_url('auth/register.php')) ?>">Create account <span aria-hidden="true">→</span></a><a class="home-action home-action-on-dark" href="<?= e(app_url('auth/login.php')) ?>">Sign in</a></div></div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
