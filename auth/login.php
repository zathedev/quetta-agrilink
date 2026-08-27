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
<main class="auth-page"><aside class="auth-aside"><h1>Sign in to your workspace.</h1><p>Review the records and actions assigned to your account.</p><div class="auth-assurance-points"><div><span aria-hidden="true">1</span><p><strong>Your role sets the workspace</strong>You only see the records and actions your account can manage.</p></div><div><span aria-hidden="true">2</span><p><strong>Use your local account details</strong>Ask your administrator if you need a local recovery request.</p></div></div></aside><section class="auth-form-wrap"><h1>Sign in</h1><p>Enter the email address and password for this account.</p><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>"></div><div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><div class="form-actions"><span><a class="muted" href="<?= e(app_url('auth/register.php')) ?>">Create an account</a><br><a class="muted" href="<?= e(app_url('auth/recover.php')) ?>">Reset password</a></span><button class="button button-primary" type="submit">Sign in</button></div></form><p class="auth-role-note">This local workspace does not expose another account’s records.</p></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
