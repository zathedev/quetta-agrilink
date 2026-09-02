<?php
/** Product footer with a clear network proposition and role-aware next steps. */
$footerUser = current_user();
$scriptUrl = static function (string $relativePath): string {
    $absolutePath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    $version = is_file($absolutePath) ? (string) filemtime($absolutePath) : '1';
    return app_url($relativePath) . '?v=' . rawurlencode($version);
};
?>
</div>
<footer class="site-footer">
    <div class="site-container footer-callout">
        <div><span>One connected agricultural network</span><h2>Move from market discovery to delivery with an accountable record.</h2></div>
        <div class="footer-callout-actions">
            <?php if ($footerUser !== null): ?>
                <a class="footer-primary-action" href="<?= e(app_url(dashboard_path($footerUser['role_slug']))) ?>">Open your dashboard <span aria-hidden="true">→</span></a>
                <a href="<?= e(app_url('notifications.php')) ?>">Review notifications</a>
            <?php else: ?>
                <a class="footer-primary-action" href="<?= e(app_url('auth/register.php')) ?>">Create your account <span aria-hidden="true">→</span></a>
                <a href="<?= e(app_url('how-it-works.php')) ?>">See how it works</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="site-container footer-grid">
        <div class="footer-summary">
            <a class="brand brand-footer" href="<?= e(app_url()) ?>"><span class="brand-mark" aria-hidden="true"></span><span class="brand-wordmark"><strong>Quetta AgriLink</strong><small>Agricultural commerce</small></span></a>
            <p>A practical operating platform connecting produce, storage, transport, pricing, and accountable trade records across Balochistan.</p>
            <div class="footer-trust-points"><span>Role-based workspaces</span><span>Local market context</span><span>Traceable handovers</span></div>
        </div>
        <nav class="footer-links" aria-label="Marketplace links"><h2>Marketplace</h2><a href="<?= e(app_url('marketplace/index.php')) ?>">Browse produce</a><a href="<?= e(app_url('market-prices.php')) ?>">Market intelligence</a><a href="<?= e(app_url('how-it-works.php')) ?>">How it works</a></nav>
        <nav class="footer-links" aria-label="Service links"><h2>Services</h2><a href="<?= e(app_url('storage/index.php')) ?>">Cold storage</a><a href="<?= e(app_url('transport/index.php')) ?>">Transport network</a><a href="<?= e(app_url('contact.php')) ?>">Support and contact</a></nav>
        <nav class="footer-links" aria-label="Company links"><h2>Company</h2><a href="<?= e(app_url('about.php')) ?>">About Quetta AgriLink</a><a href="<?= e(app_url('contact.php')) ?>">Contact us</a><a href="<?= e(app_url()) ?>">Home</a></nav>
        <nav class="footer-links" aria-label="Account links"><h2>Account</h2>
            <?php if ($footerUser !== null): ?>
                <a href="<?= e(app_url(dashboard_path($footerUser['role_slug']))) ?>">Dashboard</a><a href="<?= e(app_url('account/settings.php')) ?>">Account settings</a><a href="<?= e(app_url('notifications.php')) ?>">Notifications</a>
            <?php else: ?>
                <a href="<?= e(app_url('auth/register.php')) ?>">Create account</a><a href="<?= e(app_url('auth/login.php')) ?>">Sign in</a><a href="<?= e(app_url('auth/recover.php')) ?>">Recover access</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="site-container footer-bottom"><span>© <?= date('Y') ?> Quetta AgriLink. All rights reserved.</span><div><span>Built for practical agricultural commerce</span><span class="footer-status"><i aria-hidden="true"></i> Local platform available</span></div></div>
</footer>
<script src="<?= e($scriptUrl('assets/js/store.js')) ?>" defer></script>
<script src="<?= e($scriptUrl('assets/js/app.js')) ?>" defer></script>
</body>
</html>
