<?php /** Orchard Ledger footer: direct, practical, and regional. */ ?>
</main>
<footer class="site-footer">
    <div class="site-container footer-grid">
        <div>
            <a class="brand brand-footer" href="<?= e(app_url()) ?>"><span class="brand-mark" aria-hidden="true"></span><span><strong>Quetta Agri</strong><b>Link</b></span></a>
            <p>Local produce, storage and transport records.</p>
        </div>
        <div><h2>Marketplace</h2><a href="<?= e(app_url('marketplace/index.php')) ?>">Browse produce</a><a href="<?= e(app_url('market-prices.php')) ?>">Market prices</a><a href="<?= e(app_url('how-it-works.php')) ?>">How it works</a></div>
        <div><h2>Services</h2><a href="<?= e(app_url('storage/index.php')) ?>">Find cold storage</a><a href="<?= e(app_url('transport/index.php')) ?>">Find transport</a><a href="<?= e(app_url('contact.php')) ?>">Contact us</a></div>
        <div><h2>Account</h2><a href="<?= e(app_url('auth/register.php')) ?>">Create account</a><a href="<?= e(app_url('auth/login.php')) ?>">Sign in</a><a href="<?= e(app_url('about.php')) ?>">About Quetta AgriLink</a></div>
    </div>
    <div class="site-container footer-bottom"><span>© <?= date('Y') ?> Quetta AgriLink</span><span>Local trade workspace.</span></div>
</footer>
<script src="<?= e(app_url('assets/js/store.js')) ?>" defer></script>
<script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
