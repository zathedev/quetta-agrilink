<?php
/** Public explanation of the platform's operational scope and participants. */
declare(strict_types=1);
$pageTitle = 'About Quetta AgriLink';
$pageDescription = 'The post-harvest marketplace for Balochistan growers and trade partners.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro about-intro">
    <div class="site-container">
        <span class="eyebrow">About the platform</span>
        <h1>Practical infrastructure for the work after harvest.</h1>
        <p>Quetta AgriLink connects growers, buyers, cold-storage operators, and transport providers around a shared view of available produce, capacity, movement, and status.</p>
    </div>
</section>
<section class="section about-context-section">
    <div class="site-container">
        <div class="feature-copy">
            <span class="eyebrow">Why it matters</span>
            <h2>One accountable record across the post-harvest chain.</h2>
            <p>Agricultural trade needs clear handovers between availability, commercial intent, temperature control, and delivery. The platform keeps those decisions in a shared operating record.</p>
            <ul class="feature-list">
                <li>Growers describe supply in trade-ready terms.</li>
                <li>Buyers compare availability before a first approach.</li>
                <li>Service providers receive specific, documented requests.</li>
            </ul>
            <a class="button button-primary" href="<?= e(app_url('marketplace/index.php')) ?>">See current supply</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
