<?php
/** Orchard Ledger listing activity export: the publishing farmer receives a bounded CSV evidence trail for one owned record. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$farmer = require_role(['farmer']);
$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$listing = $listingId ? fetch_one('SELECT id, title FROM produce_listings WHERE id = :id AND farmer_id = :farmer_id LIMIT 1', ['id' => $listingId, 'farmer_id' => $farmer['id']]) : null;
if ($listing === null) {
    http_response_code(404);
    exit('The requested listing activity is not available in your workspace.');
}

$statement = db()->prepare('SELECT al.action, al.metadata, al.ip_address, al.created_at, COALESCE(u.full_name, "System") AS actor_name FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id WHERE al.entity_type = :entity_type AND al.entity_id = :entity_id ORDER BY al.created_at ASC, al.id ASC LIMIT 5000');
$statement->execute(['entity_type' => 'produce_listings', 'entity_id' => $listing['id']]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="quetta-agrilink-listing-' . (int) $listing['id'] . '-activity-' . date('Ymd-His') . '.csv"');
header('Cache-Control: private, no-store, max-age=0');
$output = fopen('php://output', 'wb');
fputcsv($output, ['Listing ID', 'Listing title', 'Timestamp', 'Activity', 'Actor', 'Details', 'IP address']);
while ($row = $statement->fetch()) {
    $metadata = json_decode((string) $row['metadata'], true);
    fputcsv($output, [$listing['id'], $listing['title'], $row['created_at'], str_replace('_', ' ', $row['action']), $row['actor_name'], is_array($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '', $row['ip_address']]);
}
fclose($output);
exit;
