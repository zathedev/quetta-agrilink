<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST'); verify_csrf(); $buyer = require_role(['buyer']);
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$listingId || fetch_one('SELECT id FROM produce_listings WHERE id = :id AND status = "active" LIMIT 1', ['id' => $listingId]) === null) json_response(false, 'This active listing could not be found.', [], 404);
$favorite = fetch_one('SELECT id FROM favorites WHERE buyer_id = :buyer AND listing_id = :listing LIMIT 1', ['buyer' => $buyer['id'], 'listing' => $listingId]);
if ($favorite) { execute_query('DELETE FROM favorites WHERE id = :id AND buyer_id = :buyer', ['id' => $favorite['id'], 'buyer' => $buyer['id']]); json_response(true, 'Listing removed from favourites.', ['saved' => false]); }
execute_query('INSERT INTO favorites (buyer_id, listing_id) VALUES (:buyer, :listing)', ['buyer' => $buyer['id'], 'listing' => $listingId]);
json_response(true, 'Listing saved to favourites.', ['saved' => true]);

