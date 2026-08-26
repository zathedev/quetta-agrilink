<?php
/** Market Desk profile page: a signed-in user can update only their own contact details with clear validation and field-level audit logging. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_login();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$success, $message, $meta] = update_account_profile((int) $user['id'], $_POST);
    if ($success) {
        flash('success', $message);
        redirect('account/profile.php');
    }
    $errors = $meta['errors'] ?? [];
    flash('error', $message);
}
$user = current_user() ?? $user;
$contactVerification = account_contact_verification((int) $user['id']);
workspace_open('My profile', 'profile');
?>
<section class="workspace-section profile-section"><div class="workspace-section-header"><div><p class="desk-kicker">Account details</p><h2>Keep your contact details current</h2><p>Your email and phone support trusted local recovery, offer communication, and operational updates. Changes are recorded in your account audit trail.</p></div></div><div class="profile-contact-status"><div><span>Local contact review</span><strong>Email: <?= $contactVerification !== null && $contactVerification['verified_email_at'] !== null ? 'reviewed' : 'not reviewed' ?></strong><strong>Phone: <?= $contactVerification !== null && $contactVerification['verified_phone_at'] !== null ? 'reviewed' : 'not reviewed' ?></strong></div><p><?php if ($contactVerification !== null && $contactVerification['updated_at'] !== null): ?>An administrator last recorded a local review on <?= e(date('j M Y', strtotime((string) $contactVerification['updated_at']))) ?> using <?= e(contact_review_reason_label($contactVerification['review_reason_code'] ?? null)) ?>. Updating an email address or phone number clears its prior review status.<?php else: ?>Contact review is recorded by an administrator using the current account details. No automated email or phone verification is claimed.<?php endif; ?></p></div><form method="post" class="form-grid profile-form" novalidate><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="form-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" autocomplete="name" required><?php if (isset($errors['full_name'])): ?><small class="field-error"><?= e($errors['full_name']) ?></small><?php endif; ?></div><div class="form-field"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($user['email']) ?>" autocomplete="email" required><?php if (isset($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?></div><div class="form-field"><label for="phone">Contact number</label><input id="phone" name="phone" value="<?= e($user['phone']) ?>" autocomplete="tel" required><?php if (isset($errors['phone'])): ?><small class="field-error"><?= e($errors['phone']) ?></small><?php endif; ?></div><div class="profile-role-note"><span>Your account role</span><strong><?= e($user['role_name']) ?></strong><p>Role changes are managed by an administrator to keep operational records accountable.</p></div><div class="form-actions"><a class="muted" href="<?= e(app_url(dashboard_path($user['role_slug']))) ?>">Return to dashboard</a><button class="button button-primary" type="submit">Save profile details</button></div></form></section>
<?php workspace_close(); ?>
