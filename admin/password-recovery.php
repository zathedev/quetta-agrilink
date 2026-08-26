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
$recoveryRange = local_recovery_audit_date_range($_GET);
$requests = local_password_recovery_is_available() && recovery_verification_notes_are_available() ? local_recovery_audit_rows($recoveryRange, 80) : [];
$recoveryQuery = http_build_query(array_filter(['recovery_from' => $recoveryRange['from']?->format('Y-m-d'), 'recovery_to' => $recoveryRange['to']?->format('Y-m-d')]));
workspace_open('Password recovery', 'recovery');
?>
<?php if (!local_password_recovery_is_available() || !recovery_verification_notes_are_available()): ?><div class="flash flash-error">Password recovery verification is not ready. Import <code>database/migrations/20260826_add_local_password_recovery.sql</code> and <code>database/migrations/20260826_add_recovery_verification_notes.sql</code> into the selected <code>quetta_agrilink</code> database.</div><?php else: ?><?php if ($recoveryLink): ?><section class="workspace-focus recovery-link-panel"><div><span>One-time link ready</span><h2>Share only after identity verification</h2><p>This link expires in 60 minutes and becomes invalid after use or revocation.</p><input readonly value="<?= e($recoveryLink) ?>" aria-label="One-time password reset link"></div></section><?php endif; ?><section class="workspace-section"><div class="workspace-section-header"><div><p class="desk-kicker">Administrator recovery register</p><h2>Local password recovery requests</h2><p>Filter requests by the date they were received. The same date range is used when you export this audit.</p></div><a class="button button-quiet" href="<?= e(app_url('admin/recovery-audit-export.php' . ($recoveryQuery !== '' ? '?' . $recoveryQuery : ''))) ?>">Export filtered audit CSV</a></div><form class="activity-date-filter" method="get"><label>From<input type="date" name="recovery_from" value="<?= e($recoveryRange['from']?->format('Y-m-d') ?? '') ?>"></label><label>To<input type="date" name="recovery_to" value="<?= e($recoveryRange['to']?->format('Y-m-d') ?? '') ?>"></label><button class="button button-quiet" type="submit">Filter requests</button><a href="<?= e(app_url('admin/password-recovery.php')) ?>">Clear dates</a></form><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Requester</th><th>Verification record</th><th>Link status</th><th>Administrator action</th></tr></thead><tbody><?php if ($requests === []): ?><tr><td colspan="4">No local recovery requests match this date range.</td></tr><?php else: foreach ($requests as $request): $active = $request['issued_at'] !== null && $request['used_at'] === null && $request['revoked_at'] === null && strtotime((string) $request['expires_at']) > time(); ?><tr><td><strong><?= e($request['full_name']) ?></strong><br><span class="muted"><?= e($request['email']) ?></span><br><span class="muted">Requested <?= e(date('j M Y H:i', strtotime((string) $request['requested_at']))) ?></span></td><td><?php if ($request['verified_at'] !== null): ?><p class="verification-note"><?= e($request['verification_notes']) ?></p><small class="muted">Recorded by <?= e($request['verified_by_name'] ?? 'administrator') ?> at <?= e(date('j M H:i', strtotime((string) $request['verified_at']))) ?></small><?php else: ?><form method="post" class="verification-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><textarea name="verification_note" required maxlength="800" placeholder="How identity was verified — never enter a password or reset link."></textarea><button class="button button-quiet" type="submit" name="action" value="save_note">Record verification</button></form><?php endif; ?></td><td><?php if ($request['used_at'] !== null): ?><span class="status-pill">Used</span><?php elseif ($active): ?><span class="status-pill pending">Active until <?= e(date('H:i', strtotime((string) $request['expires_at']))) ?></span><?php elseif ($request['revoked_at'] !== null): ?><span class="status-pill cancelled">Revoked</span><?php else: ?><span class="status-pill">Awaiting issue</span><?php endif; ?></td><td><form method="post" class="inline-action-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><?php if ($active): ?><button class="button button-quiet" type="submit" name="action" value="revoke">Revoke link</button><?php elseif ($request['used_at'] === null && $request['verified_at'] !== null): ?><button class="button button-primary" type="submit" name="action" value="issue">Issue 60-minute link</button><?php else: ?><span class="muted">Verification required</span><?php endif; ?></form></td></tr><?php endforeach; endif; ?></tbody></table></div></section><?php endif; ?>
<?php workspace_close(); ?>
