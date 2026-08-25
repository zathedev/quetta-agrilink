<?php
/** Orchard Ledger market-price service: only administrator-recorded local ranges are displayed. */
declare(strict_types=1);

function find_market_prices(?int $categoryId = null): array
{
    $sql = 'SELECT mp.*, pc.name AS category_name, l.district, u.full_name AS recorder_name FROM market_prices mp JOIN produce_categories pc ON pc.id=mp.category_id JOIN locations l ON l.id=mp.location_id LEFT JOIN users u ON u.id=mp.recorded_by_user_id';
    $params = [];
    if ($categoryId !== null) { $sql .= ' WHERE mp.category_id=:category'; $params['category']=$categoryId; }
    return fetch_all($sql . ' ORDER BY mp.price_date DESC, pc.name ASC LIMIT 100', $params);
}

function market_price_rows(array $prices): string
{
    if ($prices === []) return '<tr><td colspan="6">No recorded market prices match this product.</td></tr>';
    $rows = '';
    foreach ($prices as $price) {
        $rows .= '<tr><td><strong>' . e($price['category_name']) . '</strong></td><td>' . e($price['district']) . '</td><td>Rs. ' . number_format((float)$price['min_price'],0) . '</td><td>Rs. ' . number_format((float)$price['max_price'],0) . '</td><td><strong>Rs. ' . number_format((float)$price['average_price'],0) . '/' . e($price['unit']) . '</strong></td><td>' . e(date('j M Y',strtotime($price['price_date']))) . '</td></tr>';
    }
    return $rows;
}

