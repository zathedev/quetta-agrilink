<?php
/** Market Desk reset completion: accepts only a verified, administrator-issued selector/token pair and marks it used after the password update. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$selector = (string) ($_GET['selector'] ?? $_POST['selector'] ?? '');
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$request = resolve_local_password_reset($selector, $token);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request !== null) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) {
        $error = 'Enter the same password in both fields.';
    } else {
        try {
            complete_local_password_reset((int) $request['id'], $selector, $token, $password);
            flash('success', 'Your password has been reset. Sign in with the new password.');
            redirect('auth/login.php');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
$pageTitle = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-page"><aside class="auth-aside"><span class="desk-kicker">Secure reset</span><h1>Set a new password for your workspace.</h1><p>This single-use page works only after an authorized local administrator has verified your recovery request and issued the link.</p><div class="auth-assurance-points"><div><span aria-hidden="true">01</span><p><strong>One verified reset</strong>This page becomes unavailable after a successful password change.</p></div><div><span aria-hidden="true">02</span><p><strong>Keep the link private</strong>Never forward the reset link or share a password through chat or email.</p></div></div></aside><div class="auth-form-wrap"><p class="desk-kicker">Reset password</p><h1>Choose a new password</h1><?php if ($request === null): ?><div class="flash flash-error">This reset link is invalid, expired, revoked, or already used. Request local recovery again if you still need help.</div><p class="auth-role-note"><a href="<?= e(app_url('auth/recover.php')) ?>">Request local recovery</a></p><?php else: ?><p>Resetting access for <strong><?= e($request['full_name']) ?></strong>. Use at least 10 characters, including a letter and a number.</p><?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-grid" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="selector" value="<?= e($selector) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><div class="form-field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required></div><div class="form-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div><div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Return to sign in</a><button class="button button-primary" type="submit">Save new password</button></div></form><?php endif; ?></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
