<?php
/** Orchard Ledger authentication page: clear role selection and a server-validated account opening flow. */
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
    <aside class="auth-aside"><span class="eyebrow">One platform after harvest</span><h1>Open the right trade connection.</h1><p>Join as a grower, buyer, cold-storage provider, or transport provider. Your tools and permissions will match the work you do.</p></aside>
    <div class="auth-form-wrap">
        <h1>Create an account</h1><p>Start with your account type. You can complete the operational profile inside your workspace.</p>
        <?php if (isset($errors['form'])): ?><div class="flash flash-error"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" class="form-grid" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120" value="<?= e((string) $values['full_name']) ?>"><?php if(isset($errors['full_name'])):?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" required value="<?= e((string) $values['email']) ?>"><?php if(isset($errors['email'])):?><p class="field-error"><?= e($errors['email']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="phone">Contact number</label><input id="phone" name="phone" inputmode="tel" required value="<?= e((string) $values['phone']) ?>"><?php if(isset($errors['phone'])):?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="role">I am joining as</label><select id="role" name="role" required><option value="farmer" <?= $values['role']==='farmer'?'selected':'' ?>>Farmer</option><option value="buyer" <?= $values['role']==='buyer'?'selected':'' ?>>Buyer</option><option value="storage_provider" <?= $values['role']==='storage_provider'?'selected':'' ?>>Cold-storage provider</option><option value="transport_provider" <?= $values['role']==='transport_provider'?'selected':'' ?>>Transport provider</option></select><?php if(isset($errors['role'])):?><p class="field-error"><?= e($errors['role']) ?></p><?php endif;?></div>
            <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required><span class="form-help">At least 10 characters, including a letter and a number.</span><?php if(isset($errors['password'])):?><p class="field-error"><?= e($errors['password']) ?></p><?php endif;?></div>
            <div class="form-actions"><a class="muted" href="<?= e(app_url('auth/login.php')) ?>">Already have an account?</a><button class="button button-primary" type="submit">Create account</button></div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

