<?php
/** Quetta Workbench local recovery: a compact privacy-preserving request form. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_logged_in()) { redirect(dashboard_path(current_user()['role_slug'])); }
$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); request_local_password_recovery((string) ($_POST['email'] ?? '')); $submitted = true; }
$pageTitle = 'Recover account access';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page auth-page-recovery<?= $submitted ? ' auth-state-complete' : '' ?>">
    <aside class="auth-aside">
        <div class="auth-aside-copy">
            <span class="auth-kicker">Private recovery process</span>
            <h1>Restore access without exposing account status.</h1>
            <p>Recovery stays local and administrator-reviewed. The same response is shown whether or not an account matches.</p>
        </div>
        <div class="auth-trust-register" aria-label="Account recovery sequence">
            <div class="auth-register-heading"><span>Recovery path</span><strong>Three controlled steps</strong></div>
            <div class="auth-flow-row"><span>01</span><p><strong>Submit account email</strong>The public response does not reveal whether the address exists.</p></div>
            <div class="auth-flow-row"><span>02</span><p><strong>Verify identity locally</strong>An administrator follows the agreed verification process.</p></div>
            <div class="auth-flow-row"><span>03</span><p><strong>Use a one-time link</strong>A valid link can reset the password once.</p></div>
        </div>
    </aside>
    <section class="auth-form-wrap">
        <div class="auth-form-heading"><span class="eyebrow">Account recovery</span><h1><?= $submitted ? 'Request recorded' : 'Reset your password' ?></h1><p><?= $submitted ? 'The privacy-preserving response below completes the public step.' : 'Enter the email address used for this account.' ?></p></div>
        <?php if ($submitted): ?>
            <div class="auth-state-panel"><span aria-hidden="true">✓</span><div><strong>Recovery request accepted</strong><p>If an active matching account exists, a local recovery request is ready for administrator review.</p></div></div>
            <p class="auth-role-note"><strong>Keep credentials private.</strong> Use your verified local process to contact the administrator. Do not send passwords or reset links through chat or email.</p>
            <a class="button button-primary auth-submit" href="<?= e(app_url('auth/login.php')) ?>">Return to sign in</a>
        <?php else: ?>
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-field"><label for="email">Account email address</label><input id="email" name="email" type="email" autocomplete="email" required placeholder="name@company.pk"><span class="form-help">We show the same confirmation for every submitted address.</span></div>
                <button class="button button-primary auth-submit" type="submit">Request local recovery</button>
                <div class="auth-form-links"><a href="<?= e(app_url('auth/login.php')) ?>">Back to sign in</a></div>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
