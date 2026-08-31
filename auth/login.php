<?php
/** Quetta Workbench account entry: direct sign-in instructions and a compact secure form. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_logged_in()) { redirect(dashboard_path(current_user()['role_slug'])); }
$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    [$success, $message, $data] = authenticate($email, (string) ($_POST['password'] ?? ''));
    if ($success) { flash('success', 'You are signed in.'); redirect(dashboard_path($data['role'])); }
    $error = $message;
}
$pageTitle = 'Sign in';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page auth-page-login">
    <aside class="auth-aside">
        <div class="auth-aside-copy">
            <span class="auth-kicker">Secure workspace access</span>
            <h1>Return to the work already in motion.</h1>
            <p>Sign in to review the commercial records, service requests, and next actions assigned to your role.</p>
        </div>
        <div class="auth-trust-register" aria-label="Workspace access principles">
            <div class="auth-register-heading"><span>Access register</span><strong>Role-defined view</strong></div>
            <div class="auth-flow-row"><span>01</span><p><strong>Scoped workspace</strong>Your account opens only the tools and records its role can manage.</p></div>
            <div class="auth-flow-row"><span>02</span><p><strong>Recorded activity</strong>Account actions remain connected to the named participant who performed them.</p></div>
        </div>
        <a class="auth-aside-link" href="<?= e(app_url('marketplace/index.php')) ?>">Explore the produce marketplace</a>
    </aside>
    <section class="auth-form-wrap">
        <div class="auth-form-heading"><span class="eyebrow">Account access</span><h1>Welcome back</h1><p>Enter the email address and password for this account.</p></div>
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid account-entry-form" data-account-form="login" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>" aria-describedby="email-error" placeholder="name@company.pk"><p class="field-error" id="email-error" data-field-error="email" hidden aria-live="polite"></p></div>
            <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error"><p class="field-error" id="password-error" data-field-error="password" hidden aria-live="polite"></p></div>
            <button class="button button-primary auth-submit" type="submit">Open workspace</button>
            <div class="auth-form-links"><a href="<?= e(app_url('auth/register.php')) ?>">Create an account</a><a href="<?= e(app_url('auth/recover.php')) ?>">Reset password</a></div>
        </form>
        <p class="auth-role-note"><strong>Private by role.</strong> This local workspace does not expose another account’s records.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
