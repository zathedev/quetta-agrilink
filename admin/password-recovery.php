<?php
/** Market Desk administrator recovery register: offline identity verification precedes one-time reset-link issuance. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_note') {
            save_recovery_verification_note($requestId, (int) $user['id'], (string) ($_POST['verification_note'] ?? ''));
            flash('success', 'The identity-verification note has been recorded. You can now issue a reset link if appropriate.');
        } elseif ($action === 'issue') {
            flash('recovery_link', issue_local_password_reset($requestId, (int) $user['id']));
            flash('success', 'A one-time reset link is ready. Share it only after verifying the requester through your approved local process.');
        } elseif ($action === 'revoke') {
            revoke_local_password_reset($requestId, (int) $user['id']);
            flash('success', 'The reset link has been revoked.');
        } else {
            throw new RuntimeException('Choose a valid recovery action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/password-recovery.php');
}
$recoveryLink = flash('recovery_link');
$requests = local_password_recovery_is_available() && recovery_verification_notes_are_available() ? fetch_all('SELECT r.id, r.requested_at, r.issued_at, r.expires_at, r.used_at, r.revoked_at, r.verification_notes, r.verified_at, u.full_name, u.email, u.role_id, issuer.full_name AS issued_by_name, verifier.full_name AS verified_by_name FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id LEFT JOIN users issuer ON issuer.id = r.issued_by_user_id LEFT JOIN users verifier ON verifier.id = r.verified_by_user_id ORDER BY r.requested_at DESC LIMIT 80') : [];
workspace_open('Password recovery', 'recovery');
?>
<?php if (!local_password_recovery_is_available() || !recovery_verification_notes_are_available()): ?><div class="flash flash-error">Password recovery verification is not ready. Import <code>database/migrations/20260826_add_local_password_recovery.sql</code> and <code>database/migrations/20260826_add_recovery_verification_notes.sql</code> into the selected <code>quetta_agrilink</code> database.</div><?php else: ?><?php if ($recoveryLink): ?><section class="workspace-focus recovery-link-panel"><div><span>One-time link ready</span><h2>Share only after identity verification</h2><p>This link expires in 60 minutes and becomes invalid after use or revocation.</p><input readonly value="<?= e($recoveryLink) ?>" aria-label="One-time password reset link"></div></section><?php endif; ?><section class="workspace-section"><div class="workspace-section-header"><div><p class="desk-kicker">Administrator recovery register</p><h2>Local password recovery requests</h2><p>Record how identity was verified before issuing a link. Never store a reset link, token, or password in the note.</p></div><a class="button button-quiet" href="<?= e(app_url('admin/recovery-audit-export.php')) ?>">Export recovery audit CSV</a></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Requester</th><th>Verification record</th><th>Link status</th><th>Administrator action</th></tr></thead><tbody><?php if ($requests === []): ?><tr><td colspan="4">No local recovery requests are waiting for review.</td></tr><?php else: foreach ($requests as $request): $active = $request['issued_at'] !== null && $request['used_at'] === null && $request['revoked_at'] === null && strtotime((string) $request['expires_at']) > time(); ?><tr><td><strong><?= e($request['full_name']) ?></strong><br><span class="muted"><?= e($request['email']) ?></span><br><span class="muted">Requested <?= e(date('j M Y H:i', strtotime((string) $request['requested_at']))) ?></span></td><td><?php if ($request['verified_at'] !== null): ?><p class="verification-note"><?= e($request['verification_notes']) ?></p><small class="muted">Recorded by <?= e($request['verified_by_name'] ?? 'administrator') ?> at <?= e(date('j M H:i', strtotime((string) $request['verified_at']))) ?></small><?php else: ?><form method="post" class="verification-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><textarea name="verification_note" required maxlength="800" placeholder="How identity was verified — never enter a password or reset link."></textarea><button class="button button-quiet" type="submit" name="action" value="save_note">Record verification</button></form><?php endif; ?></td><td><?php if ($request['used_at'] !== null): ?><span class="status-pill">Used</span><?php elseif ($active): ?><span class="status-pill pending">Active until <?= e(date('H:i', strtotime((string) $request['expires_at']))) ?></span><?php elseif ($request['revoked_at'] !== null): ?><span class="status-pill cancelled">Revoked</span><?php else: ?><span class="status-pill">Awaiting issue</span><?php endif; ?></td><td><form method="post" class="inline-action-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><?php if ($active): ?><button class="button button-quiet" type="submit" name="action" value="revoke">Revoke link</button><?php elseif ($request['used_at'] === null && $request['verified_at'] !== null): ?><button class="button button-primary" type="submit" name="action" value="issue">Issue 60-minute link</button><?php else: ?><span class="muted">Verification required</span><?php endif; ?></form></td></tr><?php endforeach; endif; ?></tbody></table></div></section><?php endif; ?>
<?php workspace_close(); ?>
