<?php
/** Orchard Ledger authentication page: calm, direct, and focused on a secure return to work. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role_slug']));
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    [$success, $message, $data] = authenticate($email, (string) ($_POST['password'] ?? ''));
    if ($success) {
        flash('success', 'You are signed in.');
        redirect(dashboard_path($data['role']));
    }
    $error = $message;
}
$pageTitle = 'Sign in';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-page">
    <aside class="auth-aside"><span class="eyebrow">Quetta’s post-harvest network</span><h1>Keep every harvest moving.</h1><p>Manage produce, buyers, storage, transport, and operational updates from one trusted workspace.</p></aside>
    <div class="auth-form-wrap">
        <h1>Sign in</h1><p>Access your Quetta AgriLink workspace.</p>
        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-grid" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>"></div>
            <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <div class="form-actions"><a class="muted" href="<?= e(app_url('auth/register.php')) ?>">Need an account?</a><button class="button button-primary" type="submit">Sign in</button></div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

