<?php
/** Protected dashboard-export audit download: emits only visible accountability columns for the current administrator filters. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/dashboard-export-audits.php';

$administrator = require_role(['admin']);
$filters = dashboard_summary_export_audit_filters($_GET);
$rows = dashboard_summary_export_audit_rows($filters, 5000);
audit_log((int) $administrator['id'], 'dashboard_export_audit_register_exported', 'audit_logs', null, ['row_count' => count($rows), 'role' => $filters['role'], 'has_account_filter' => $filters['account'] !== '', 'exported_from' => $filters['exported_from']?->format('Y-m-d'), 'exported_to' => $filters['exported_to']?->format('Y-m-d'), 'summary_from' => $filters['summary_from']?->format('Y-m-d'), 'summary_to' => $filters['summary_to']?->format('Y-m-d')]);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quetta-agrilink-dashboard-export-audit-' . date('Ymd-His') . '.csv"');
header('Cache-Control: no-store, private');
$output = fopen('php://output', 'wb');
fputcsv($output, ['Account ID', 'Account', 'Role at export', 'Period start', 'Period end', 'Exported at']);
foreach ($rows as $row) {
    fputcsv($output, array_map('csv_safe_cell', [$row['actor_user_id'], $row['actor_name'], dashboard_summary_export_audit_roles()[$row['exported_role']] ?? 'Unavailable role', $row['period_start'], $row['period_end'], $row['created_at']]));
}
fclose($output);
exit;
