<?php
/** Protected contact-review export: administrator CSV omits free-text evidence notes, recovery data, credentials, and reset secrets. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$administrator = require_role(['admin']);
if (!account_contact_verification_is_available() || !contact_review_reason_codes_are_available()) {
    http_response_code(503);
    exit('Contact-review records are not ready. Import the account contact verification and contact review reason migrations first.');
}
$filters = contact_review_register_filters($_GET);
$rows = contact_review_register_rows($filters);
audit_log((int) $administrator['id'], 'contact_review_register_exported', 'account_contact_verifications', null, ['row_count' => count($rows), 'status' => $filters['status'], 'reason' => $filters['reason'], 'has_search' => $filters['search'] !== '']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quetta-agrilink-contact-review-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store');
$output = fopen('php://output', 'wb');
fputcsv($output, ['Account ID', 'Account', 'Email', 'Phone', 'Role', 'Email reviewed at', 'Phone reviewed at', 'Review evidence', 'Last recorded at', 'Recorded by']);
foreach ($rows as $row) {
    fputcsv($output, array_map('csv_safe_cell', [$row['id'], $row['full_name'], $row['email'], $row['phone'], $row['role_name'], $row['verified_email_at'], $row['verified_phone_at'], $row['updated_at'] !== null ? contact_review_reason_label($row['review_reason_code']) : '', $row['updated_at'], $row['verified_by_name']]));
}
fclose($output);
exit;
