<?php
/** Quetta Workbench password reset: short local-only reset screen retaining the verified selector/token flow. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$selector = (string) ($_GET['selector'] ?? $_POST['selector'] ?? '');
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$request = resolve_local_password_reset($selector, $token);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request !== null) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) { $error = 'Enter the same password in both fields.'; }
    else { try { complete_local_password_reset((int) $request['id'], $selector, $token, $password); flash('success', 'Your password has been reset. Sign in with the new password.'); redirect('auth/login.php'); } catch (Throwable $exception) { $error = $exception->getMessage(); } }
}
$pageTitle = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><aside class="auth-aside"><h1>Set a new password.</h1><p>This page works only with a current recovery link issued through the approved local process.</p><div class="auth-assurance-points"><div><span aria-hidden="true">1</span><p><strong>Use the link once</strong>The link is unavailable after a successful password change.</p></div><div><span aria-hidden="true">2</span><p><strong>Keep it private</strong>Do not forward the reset link or share a password.</p></div></div></aside><section class="auth-form-wrap"><h1>New password</h1><?php if ($request === null): ?><div class="flash flash-error">This reset link is invalid, expired, revoked, or already used.</div><p class="auth-role-note"><a href="<?= e(app_url('auth/recover.php')) ?>">Request local recovery</a></p><?php else: ?><p>Resetting access for <strong><?= e($request['full_name']) ?></strong>. Use at least 10 characters, including a letter and a number.</p><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="selector" value="<?= e($selector) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><div class="form-field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required></div><div class="form-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div><div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Back to sign in</a><button class="button button-primary" type="submit">Save password</button></div></form><?php endif; ?></section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
