<?php
/** Market Desk local recovery request: generic feedback avoids account enumeration and directs offline verification to an administrator. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role_slug']));
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    request_local_password_recovery((string) ($_POST['email'] ?? ''));
    $submitted = true;
}
$pageTitle = 'Recover account access';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-page"><aside class="auth-aside"><span class="desk-kicker">Local account recovery</span><h1>Request a secure return to your workspace.</h1><p>For this XAMPP installation, an administrator verifies your identity and gives you a one-time reset link through an agreed local channel.</p><div class="auth-assurance-points"><div><span aria-hidden="true">01</span><p><strong>The same reply for every email</strong>Your request never confirms whether an account exists.</p></div><div><span aria-hidden="true">02</span><p><strong>Verified local handover</strong>Recovery stays with the approved local process, not an unverified channel.</p></div></div></aside><div class="auth-form-wrap"><p class="desk-kicker">Recovery request</p><h1>Need a new password?</h1><p>Enter the email address used for your account. For privacy, this page always gives the same confirmation message.</p><?php if ($submitted): ?><div class="flash flash-success">If an active matching account exists, a local recovery request is ready for administrator review.</div><p class="auth-role-note">Contact your Quetta AgriLink administrator through your verified local process. Do not send passwords through chat or email.</p><?php else: ?><form method="post" class="form-grid" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="form-field"><label for="email">Account email address</label><input id="email" name="email" type="email" autocomplete="email" required placeholder="name@company.pk"></div><div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Return to sign in</a><button class="button button-primary" type="submit">Request local recovery</button></div></form><?php endif; ?></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
