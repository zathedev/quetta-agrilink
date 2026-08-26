<?php
/** Protected recovery audit export: rows omit selectors, reset tokens, hashes, and passwords. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$administrator = require_role(['admin']);
if (!local_password_recovery_is_available() || !recovery_verification_notes_are_available()) {
    http_response_code(503);
    exit('Password recovery records are not ready. Import the local recovery and verification-note migrations first.');
}
$rows = fetch_all('SELECT r.id, r.requested_at, r.verified_at, r.issued_at, r.expires_at, r.used_at, r.revoked_at, r.verification_notes, u.full_name, u.email, role.name AS role_name, verifier.full_name AS verified_by_name, issuer.full_name AS issued_by_name, revoker.full_name AS revoked_by_name FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id JOIN roles role ON role.id = u.role_id LEFT JOIN users verifier ON verifier.id = r.verified_by_user_id LEFT JOIN users issuer ON issuer.id = r.issued_by_user_id LEFT JOIN users revoker ON revoker.id = r.revoked_by_user_id ORDER BY r.requested_at DESC, r.id DESC');
audit_log((int) $administrator['id'], 'local_recovery_audit_exported', 'local_password_recovery_requests', null, ['row_count' => count($rows)]);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quetta-agrilink-recovery-audit-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store');
$output = fopen('php://output', 'wb');
fputcsv($output, ['Request ID', 'Account', 'Email', 'Role', 'Requested at', 'Verification note', 'Verified at', 'Verified by', 'Issued at', 'Issued by', 'Expires at', 'Used at', 'Revoked at', 'Revoked by', 'Status']);
foreach ($rows as $row) {
    $status = $row['used_at'] !== null ? 'Used' : ($row['revoked_at'] !== null ? 'Revoked' : ($row['issued_at'] !== null && strtotime((string) $row['expires_at']) > time() ? 'Active' : ($row['issued_at'] !== null ? 'Expired' : 'Awaiting issue')));
    fputcsv($output, [$row['id'], $row['full_name'], $row['email'], $row['role_name'], $row['requested_at'], $row['verification_notes'], $row['verified_at'], $row['verified_by_name'], $row['issued_at'], $row['issued_by_name'], $row['expires_at'], $row['used_at'], $row['revoked_at'], $row['revoked_by_name'], $status]);
}
fclose($output);
exit;
