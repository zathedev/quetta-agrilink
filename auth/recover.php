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
<main class="auth-page"><aside class="auth-aside"><h1>Request local account recovery.</h1><p>An administrator verifies recovery requests through the agreed local process. This page does not confirm whether an account exists.</p><div class="auth-assurance-points"><div><span aria-hidden="true">1</span><p><strong>Same result for every email</strong>Your request keeps account status private.</p></div><div><span aria-hidden="true">2</span><p><strong>One-time reset link</strong>Use the link only after local identity verification.</p></div></div></aside><section class="auth-form-wrap"><h1>Reset password</h1><?php if ($submitted): ?><div class="flash flash-success">If an active matching account exists, a local recovery request is ready for administrator review.</div><p class="auth-role-note">Use your verified local process to contact the administrator. Do not send passwords or reset links through chat or email.</p><?php else: ?><p>Enter the email address used for this account.</p><form method="post" class="form-grid" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="form-field"><label for="email">Account email address</label><input id="email" name="email" type="email" autocomplete="email" required placeholder="name@company.pk"></div><div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Back to sign in</a><button class="button button-primary" type="submit">Request recovery</button></div></form><?php endif; ?></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
