<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/prices.php';
if(!is_ajax_request()){http_response_code(404);exit;}$categoryId=filter_input(INPUT_GET,'category_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;$prices=find_market_prices($categoryId);json_response(true,count($prices).' recorded price entr'.(count($prices)===1?'y':'ies').' shown.',['html'=>market_price_rows($prices),'count'=>count($prices)]);

