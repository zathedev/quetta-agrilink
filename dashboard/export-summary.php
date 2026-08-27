<?php
/** Quetta Workbench dashboard export: role-scoped selected-period summary CSV only. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/support-desk.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_login();
$window = workspace_summary_window();
$range = ['user' => (int) $user['id'], 'from' => $window['from'], 'to' => $window['to']];
$rows = [];
switch ($user['role_slug']) {
    case 'buyer':
        $rows = [
            ['Active orders', fetch_one('SELECT COUNT(*) AS count FROM orders WHERE buyer_id=:user AND status NOT IN("completed","cancelled") AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
            ['Pending offers', fetch_one('SELECT COUNT(*) AS count FROM offers WHERE buyer_id=:user AND status IN("pending","countered") AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
            ['Completed purchases', fetch_one('SELECT COUNT(*) AS count FROM orders WHERE buyer_id=:user AND status="completed" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
        ]; break;
    case 'farmer':
        $rows = [
            ['Active listings', fetch_one('SELECT COUNT(*) AS count FROM produce_listings WHERE farmer_id=:user AND status="active" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
            ['Offers to review', fetch_one('SELECT COUNT(*) AS count FROM offers WHERE farmer_id=:user AND status IN("pending","countered") AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
            ['Active orders', fetch_one('SELECT COUNT(*) AS count FROM orders WHERE farmer_id=:user AND status NOT IN("completed","cancelled") AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0],
        ]; break;
    case 'storage_provider':
        $provider = fetch_one('SELECT id FROM storage_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]); $range = ['provider' => (int) ($provider['id'] ?? 0), 'from' => $window['from'], 'to' => $window['to']];
        $rows = [['Pending bookings', fetch_one('SELECT COUNT(*) AS count FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider AND sb.status="requested" AND sb.created_at>=:from AND sb.created_at<:to', $range)['count'] ?? 0], ['Active bookings', fetch_one('SELECT COUNT(*) AS count FROM storage_bookings sb JOIN storage_facilities sf ON sf.id=sb.facility_id WHERE sf.provider_id=:provider AND sb.status IN("approved","active") AND sb.created_at>=:from AND sb.created_at<:to', $range)['count'] ?? 0]]; break;
    case 'transport_provider':
        $provider = fetch_one('SELECT id FROM transport_providers WHERE user_id=:user LIMIT 1', ['user' => $user['id']]); $range = ['provider' => (int) ($provider['id'] ?? 0), 'from' => $window['from'], 'to' => $window['to']];
        $rows = [['New requests', fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="requested" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0], ['Active deliveries', fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status IN("accepted","driver_assigned","pickup_scheduled","picked_up","in_transit") AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0], ['Delivered', fetch_one('SELECT COUNT(*) AS count FROM transport_requests WHERE provider_id=:provider AND status="delivered" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0]]; break;
    case 'admin':
        $rows = [['Active users', fetch_one('SELECT COUNT(*) AS count FROM users WHERE status="active" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0], ['Active listings', fetch_one('SELECT COUNT(*) AS count FROM produce_listings WHERE status="active" AND created_at>=:from AND created_at<:to', $range)['count'] ?? 0], ['Total orders', fetch_one('SELECT COUNT(*) AS count FROM orders WHERE created_at>=:from AND created_at<:to', $range)['count'] ?? 0]]; break;
    default: http_response_code(403); exit;
}
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="quetta-agrilink-summary-' . e($user['role_slug']) . '-' . $window['from_input'] . '-to-' . $window['to_input'] . '.csv"');
$out = fopen('php://output', 'wb'); fputcsv($out, ['Metric', 'Value', 'Period start', 'Period end']); foreach ($rows as $row) fputcsv($out, [$row[0], $row[1], $window['from_input'], $window['to_input']]); fclose($out);
