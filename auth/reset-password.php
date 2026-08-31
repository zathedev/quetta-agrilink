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
<main class="auth-page auth-page-reset<?= $request === null ? ' auth-state-invalid' : '' ?>">
    <aside class="auth-aside">
        <div class="auth-aside-copy">
            <span class="auth-kicker">One-time credential update</span>
            <h1>Set a new password through a verified link.</h1>
            <p>This page accepts only a current recovery link issued through the approved local process.</p>
        </div>
        <div class="auth-trust-register" aria-label="Password reset safeguards">
            <div class="auth-register-heading"><span>Reset safeguards</span><strong>Link-bound access</strong></div>
            <div class="auth-flow-row"><span>01</span><p><strong>Use the link once</strong>It becomes unavailable after a successful password change.</p></div>
            <div class="auth-flow-row"><span>02</span><p><strong>Keep it private</strong>Never forward a reset link or share the new password.</p></div>
        </div>
    </aside>
    <section class="auth-form-wrap">
        <div class="auth-form-heading"><span class="eyebrow">Credential reset</span><h1><?= $request === null ? 'Link unavailable' : 'Create a new password' ?></h1><?php if ($request !== null): ?><p>Resetting access for <strong><?= e($request['full_name']) ?></strong>.</p><?php endif; ?></div>
        <?php if ($request === null): ?>
            <div class="auth-state-panel auth-state-panel-error"><span aria-hidden="true">!</span><div><strong>This link cannot be used</strong><p>It is invalid, expired, revoked, or already completed.</p></div></div>
            <p class="auth-role-note">Start a new recovery request to receive another administrator-approved link.</p>
            <a class="button button-primary auth-submit" href="<?= e(app_url('auth/recover.php')) ?>">Request local recovery</a>
        <?php else: ?>
            <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="form-grid" novalidate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="selector" value="<?= e($selector) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required><span class="form-help">Use at least 10 characters, including a letter and a number.</span></div>
                <div class="form-field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <button class="button button-primary auth-submit" type="submit">Save new password</button>
                <div class="auth-form-links"><a href="<?= e(app_url('auth/login.php')) ?>">Back to sign in</a></div>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
