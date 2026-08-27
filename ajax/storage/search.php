<?php
/** Public XHR endpoint for the local cold-storage discovery register. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/storage-marketplace.php';

if (!is_ajax_request()) {
    http_response_code(404);
    exit;
}

$filters = storage_marketplace_filters($_GET);
$facilities = find_storage_facilities($filters);
$user = current_user();
json_response(true, count($facilities) . ' storage facilit' . (count($facilities) === 1 ? 'y' : 'ies') . ' shown.', ['html' => storage_facility_cards_html($facilities, $user), 'count' => count($facilities)]);
