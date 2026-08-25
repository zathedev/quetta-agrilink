<?php
/** Orchard Ledger audit export: administrator-only CSV evidence for protected attachment delivery. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

require_role(['admin']);
if (!attachment_migration_is_available()) {
    http_response_code(404);
    exit('Attachment audit history is not available.');
}

$statement = db()->prepare(
    'SELECT al.id, ra.id AS attachment_id, ra.original_name, ra.entity_type AS record_type, ra.entity_id AS record_id,
            COALESCE(u.full_name, "Unknown user") AS downloaded_by, COALESCE(u.email, "") AS downloader_email,
            al.ip_address, al.created_at
     FROM audit_logs al
     JOIN record_attachments ra ON ra.id = al.entity_id
     LEFT JOIN users u ON u.id = al.actor_user_id
     WHERE al.action = :action AND al.entity_type = :entity_type
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT 5000'
);
$statement->execute(['action' => 'attachment_downloaded', 'entity_type' => 'record_attachments']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quetta-agrilink-attachment-audit-' . date('Ymd-His') . '.csv"');
header('Cache-Control: private, no-store, max-age=0');
$output = fopen('php://output', 'wb');
fputcsv($output, ['Audit ID', 'Attachment ID', 'Original file', 'Record type', 'Record ID', 'Downloaded by', 'Downloader email', 'IP address', 'Timestamp']);
while ($row = $statement->fetch()) {
    fputcsv($output, [$row['id'], $row['attachment_id'], $row['original_name'], $row['record_type'], $row['record_id'], $row['downloaded_by'], $row['downloader_email'], $row['ip_address'], $row['created_at']]);
}
fclose($output);
exit;
