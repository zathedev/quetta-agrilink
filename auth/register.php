<?php
/** Market Desk sign-up: role-first onboarding helps an ordinary user understand the workspace they will receive. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role_slug']));
}

$errors = [];
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'farmer'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = array_merge($values, array_intersect_key($_POST, $values));
    [$success, $message, $data] = register_account($_POST);
    if ($success) {
        flash('success', $message);
        redirect('auth/login.php');
    }
    $errors = $data['errors'] ?? ['form' => $message];
}
$pageTitle = 'Create account';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-page">
    <aside class="auth-aside"><span class="desk-kicker">Create an account</span><h1>Start with the role you have today.</h1><p>Join as a grower, buyer, cold-storage provider, or transport provider. Your workspace will keep the records and next actions for that work clear.</p></aside>
    <div class="auth-form-wrap">
        <p class="desk-kicker">Account details</p><h1>Choose your workspace</h1><p>Start with basic details and your account type. You can complete operational information once your workspace opens.</p>
        <?php if (isset($errors['form'])): ?><div class="flash flash-error"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" class="form-grid" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120" value="<?= e((string) $values['full_name']) ?>"><?php if(isset($errors['full_name'])):?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" required value="<?= e((string) $values['email']) ?>"><?php if(isset($errors['email'])):?><p class="field-error"><?= e($errors['email']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="phone">Contact number</label><input id="phone" name="phone" inputmode="tel" required value="<?= e((string) $values['phone']) ?>"><?php if(isset($errors['phone'])):?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="role">I am joining as</label><select id="role" name="role" required><option value="farmer" <?= $values['role']==='farmer'?'selected':'' ?>>Farmer — publish harvest and manage offers</option><option value="buyer" <?= $values['role']==='buyer'?'selected':'' ?>>Buyer — compare supply and manage offers</option><option value="storage_provider" <?= $values['role']==='storage_provider'?'selected':'' ?>>Cold-storage provider — manage capacity and bookings</option><option value="transport_provider" <?= $values['role']==='transport_provider'?'selected':'' ?>>Transport provider — manage fleet and delivery requests</option></select><?php if(isset($errors['role'])):?><p class="field-error"><?= e($errors['role']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required><span class="form-help">At least 10 characters, including a letter and a number.</span><?php if(isset($errors['password'])):?><p class="field-error"><?= e($errors['password']) ?></p><?php endif;?></div>
            <div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Already have an account?</a><button class="button button-primary" type="submit">Create account</button></div>
        </form>
        <p class="auth-role-note">Choose the role that best matches your current work. Permissions and operational records are separated by role for clarity.</p>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
