<?php
/** Quetta Workbench registration: one direct local-account form with role-scoped access preserved. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if (is_logged_in()) { redirect(dashboard_path(current_user()['role_slug'])); }
$errors = [];
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'farmer'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = array_merge($values, array_intersect_key($_POST, $values));
    [$success, $message, $data] = register_account($_POST);
    if ($success) { flash('success', $message); redirect('auth/login.php'); }
    $errors = $data['errors'] ?? ['form' => $message];
}
$pageTitle = 'Create account';
require __DIR__ . '/../includes/header.php';
?>
<main class="auth-page auth-page-register">
    <aside class="auth-aside">
        <div class="auth-aside-copy">
            <span class="auth-kicker">Join the operating network</span>
            <h1>Choose the workspace that matches your work.</h1>
            <p>Start with one role and the essential account details. Operational profiles and records can be completed inside the workspace.</p>
        </div>
        <div class="auth-role-register" aria-label="Available account roles">
            <article><span>01</span><strong>Farmer</strong><small>Publish harvest supply</small></article>
            <article><span>02</span><strong>Buyer</strong><small>Source and negotiate</small></article>
            <article><span>03</span><strong>Storage</strong><small>Manage cold-chain capacity</small></article>
            <article><span>04</span><strong>Transport</strong><small>Coordinate movement</small></article>
        </div>
        <a class="auth-aside-link" href="<?= e(app_url('how-it-works.php')) ?>">See how the workflow connects</a>
    </aside>
    <section class="auth-form-wrap auth-form-wide">
        <div class="auth-form-heading"><span class="eyebrow">New participant</span><h1>Create your account</h1><p>Use accurate contact details and select the role you will operate.</p></div>
        <div class="auth-section-meta"><span>5 required fields</span><span>Role-scoped permissions</span></div>
        <?php if (isset($errors['form'])): ?><div class="flash flash-error"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" class="form-grid account-entry-form" data-account-form="register" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" required maxlength="120" value="<?= e((string) $values['full_name']) ?>" aria-describedby="full-name-error"><p class="field-error" id="full-name-error" data-field-error="full_name"<?= isset($errors['full_name']) ? '' : ' hidden' ?> aria-live="polite"><?= isset($errors['full_name']) ? e($errors['full_name']) : '' ?></p></div>
            <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e((string) $values['email']) ?>" aria-describedby="email-error"><p class="field-error" id="email-error" data-field-error="email"<?= isset($errors['email']) ? '' : ' hidden' ?> aria-live="polite"><?= isset($errors['email']) ? e($errors['email']) : '' ?></p></div>
            <div class="form-field"><label for="phone">Contact number</label><input id="phone" name="phone" inputmode="tel" autocomplete="tel" required value="<?= e((string) $values['phone']) ?>" aria-describedby="phone-error"><p class="field-error" id="phone-error" data-field-error="phone"<?= isset($errors['phone']) ? '' : ' hidden' ?> aria-live="polite"><?= isset($errors['phone']) ? e($errors['phone']) : '' ?></p></div>
            <div class="form-field"><label for="role">Account role</label><select id="role" name="role" required aria-describedby="role-error"><option value="farmer" <?= $values['role'] === 'farmer' ? 'selected' : '' ?>>Farmer</option><option value="buyer" <?= $values['role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option><option value="storage_provider" <?= $values['role'] === 'storage_provider' ? 'selected' : '' ?>>Cold-storage provider</option><option value="transport_provider" <?= $values['role'] === 'transport_provider' ? 'selected' : '' ?>>Transport provider</option></select><p class="field-error" id="role-error" data-field-error="role"<?= isset($errors['role']) ? '' : ' hidden' ?> aria-live="polite"><?= isset($errors['role']) ? e($errors['role']) : '' ?></p></div>
            <div class="form-field auth-field-wide"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-help password-error"><span class="form-help" id="password-help">Use at least 10 characters, including a letter and a number.</span><p class="field-error" id="password-error" data-field-error="password"<?= isset($errors['password']) ? '' : ' hidden' ?> aria-live="polite"><?= isset($errors['password']) ? e($errors['password']) : '' ?></p></div>
            <button class="button button-primary auth-submit auth-field-wide" type="submit">Create role workspace</button>
            <div class="auth-form-links auth-field-wide"><span>Already registered?</span><a href="<?= e(app_url('auth/login.php')) ?>">Sign in instead</a></div>
        </form>
        <p class="auth-role-note"><strong>Access starts narrow.</strong> Your account opens with only the permissions for the selected role.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
