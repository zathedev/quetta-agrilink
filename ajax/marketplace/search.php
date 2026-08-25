<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/marketplace.php';
if (!is_ajax_request()) { http_response_code(404); exit; }
$filters = marketplace_filters($_GET);
$listings = find_listings($filters);
json_response(true, count($listings) . ' active listing' . (count($listings) === 1 ? '' : 's') . ' shown.', ['html' => listing_cards_html($listings), 'count' => count($listings)]);

