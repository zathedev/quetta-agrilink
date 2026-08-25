<?php /** Orchard Ledger footer: direct, practical, and regional. */ ?>
</main>
<footer class="site-footer">
    <div class="site-container footer-grid">
        <div>
            <a class="brand brand-footer" href="<?= e(app_url()) ?>"><img src="/manus-storage/quetta-agrilink-mark_a4b760ba.png" alt="" width="42" height="42"><span><strong>Quetta Agri</strong><b>Link</b></span></a>
            <p>One platform for everything after harvest: selling, storage, and transport.</p>
        </div>
        <div><h2>Marketplace</h2><a href="<?= e(app_url('marketplace/index.php')) ?>">Browse produce</a><a href="<?= e(app_url('market-prices.php')) ?>">Market prices</a><a href="<?= e(app_url('how-it-works.php')) ?>">How it works</a></div>
        <div><h2>Services</h2><a href="<?= e(app_url('storage/index.php')) ?>">Find cold storage</a><a href="<?= e(app_url('transport/index.php')) ?>">Find transport</a><a href="<?= e(app_url('contact.php')) ?>">Contact us</a></div>
        <div><h2>Account</h2><a href="<?= e(app_url('auth/register.php')) ?>">Join the platform</a><a href="<?= e(app_url('auth/login.php')) ?>">Sign in</a><a href="<?= e(app_url('about.php')) ?>">About Quetta AgriLink</a></div>
    </div>
    <div class="site-container footer-bottom"><span>© <?= date('Y') ?> Quetta AgriLink</span><span>Built for reliable agricultural trade.</span></div>
</footer>
<script src="<?= e(app_url('assets/js/store.js')) ?>" defer></script>
<script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>

