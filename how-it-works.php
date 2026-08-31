<?php
/** Public workflow guide: each accountable record supports the next agricultural handoff. */
declare(strict_types=1);
$pageTitle = 'How It Works';
require __DIR__ . '/includes/header.php';
?>
<div class="public-commerce-page guide-page">
    <section class="commerce-page-hero guide-intro">
        <div class="site-container commerce-page-hero-grid">
            <div class="commerce-page-hero-copy">
                <span class="eyebrow">How it works</span>
                <h1>One connected record trail from harvest to delivery.</h1>
                <p>Each participant adds the information the next handoff needs: produce availability, commercial terms, storage requirements, transport details, and delivery status.</p>
                <div class="commerce-hero-tags"><span>Role-scoped workspaces</span><span>Visible commercial terms</span><span>Recorded status history</span></div>
            </div>
            <aside class="commerce-hero-register workflow-register" aria-label="Agricultural commerce workflow">
                <div class="commerce-register-heading"><span>Connected workflow</span><strong>Four accountable records</strong></div>
                <ol><li><span>01</span>Supply record</li><li><span>02</span>Trade terms</li><li><span>03</span>Capacity request</li><li><span>04</span>Delivery status</li></ol>
                <a href="#connected-workflow">Follow the workflow</a>
            </aside>
        </div>
    </section>

    <section class="section guide-workflow-section" id="connected-workflow">
        <div class="site-container">
            <div class="guide-workflow-lead">
                <div><p class="desk-kicker">Four connected records</p><h2>Each handover makes the next decision more specific.</h2></div>
                <p>Use the platform in the same practical order as the work: describe what is available, record trade interest, protect the crop with capacity, then coordinate its next movement.</p>
            </div>
            <div class="process-grid">
                <article class="process-step process-supply"><span class="process-number">01 / SUPPLY</span><span class="process-symbol" aria-hidden="true">S</span><h3>Publish availability</h3><p>Farmers list product, grade, quantity, price expectation, origin, and harvest timing.</p><a href="<?= e(app_url('marketplace/index.php')) ?>">See active supply</a></article>
                <article class="process-step process-trade"><span class="process-number">02 / TRADE</span><span class="process-symbol" aria-hidden="true">T</span><h3>Compare and offer</h3><p>Buyers filter active supply and submit a recorded offer with quantity and price.</p><a href="<?= e(app_url('market-prices.php')) ?>">Review price context</a></article>
                <article class="process-step process-preserve"><span class="process-number">03 / PRESERVE</span><span class="process-symbol" aria-hidden="true">P</span><h3>Book capacity</h3><p>Farmers request compatible cold storage with a date range and an estimated cost.</p><a href="<?= e(app_url('storage/index.php')) ?>">Find storage</a></article>
                <article class="process-step process-deliver"><span class="process-number">04 / DELIVER</span><span class="process-symbol" aria-hidden="true">D</span><h3>Request transport</h3><p>Transport providers receive structured pickup requirements and update movement milestones.</p><a href="<?= e(app_url('transport/index.php')) ?>">Find transport</a></article>
            </div>
        </div>
    </section>

    <section class="section workflow-principles-section">
        <div class="site-container">
            <div class="section-heading commerce-section-heading"><div><span class="eyebrow">Operational discipline</span><h2>The record stays useful after the first conversation.</h2></div><p>Quetta AgriLink keeps the information needed for follow-through close to the work that created it.</p></div>
            <div class="workflow-principles">
                <article><span>01</span><h3>Terms remain visible</h3><p>Quantity, expected price, grade, capacity, route needs, and status stay attached to the relevant record.</p></article>
                <article><span>02</span><h3>Ownership stays scoped</h3><p>Participants work from role-specific dashboards and only act on records available to their account.</p></article>
                <article><span>03</span><h3>Handoffs remain traceable</h3><p>Offers, bookings, dispatch updates, notifications, and supported actions retain accountable history.</p></article>
            </div>
            <div class="guide-record-note"><div><p class="desk-kicker">Start with the current record</p><h2>Every next action begins with visible terms.</h2><p>Open the marketplace to compare the produce, origin, grade, quantity, and expected price that should guide the first trade conversation.</p></div><a class="button button-primary" href="<?= e(app_url('marketplace/index.php')) ?>">Explore marketplace</a></div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
