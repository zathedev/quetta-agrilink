<?php
/** Orchard Ledger contact page: an honest project contact route with no simulated message submission. */
declare(strict_types=1);$pageTitle='Contact';require __DIR__.'/includes/header.php';
?>
<section class="page-intro contact-intro"><div class="site-container"><span class="eyebrow" style="color:var(--clay)">Contact</span><h1>Talk about a practical post-harvest workflow.</h1><p>For local demonstration, partnership, university, or agricultural-organization enquiries, use the project repository documentation and configure an official contact channel before production release.</p></div></section>
<section class="section contact-context-section"><div class="site-container"><div class="cta-panel"><div><span class="eyebrow" style="color:var(--clay)">Project contact</span><h2>Set up a verified support channel before launch.</h2><p>This development build deliberately does not simulate a contact submission. Add an owned support email or authenticated helpdesk integration before using it with live customers.</p><div class="contact-readiness-row"><span>Owned support email</span><span>Customer-data policy</span><span>Authenticated support workflow</span></div></div><a class="button button-primary" href="<?= e(app_url('about.php')) ?>">Learn about the platform</a></div></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
