<?php
/** Orchard Ledger price intelligence: data-led reference ranges, not a prediction or a transaction quote. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/prices.php';
$categoryId=filter_input(INPUT_GET,'category_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;$categories=fetch_all('SELECT id,name FROM produce_categories WHERE is_active=1 ORDER BY name');$prices=find_market_prices($categoryId);$pageTitle='Market Prices';
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro"><div class="site-container"><span class="eyebrow" style="color:var(--clay)">Market intelligence</span><h1>Use recorded local ranges as trade context.</h1><p>These entries are recorded by authorized platform administrators for demo purposes. They are reference information—not a guaranteed trade price, an offer, or financial advice.</p></div></section>
<section class="section"><div class="site-container"><div class="market-toolbar"><form data-price-filter data-endpoint="<?= e(app_url('ajax/prices/search.php')) ?>" class="form-field" style="min-width:260px"><label for="category_id">View a product</label><select id="category_id" name="category_id"><option value="">All recorded products</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>" <?= $categoryId===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach;?></select></form><p class="muted" data-price-feedback><?= count($prices) ?> recorded price entr<?= count($prices)===1?'y':'ies' ?> shown.</p></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Product</th><th>Market</th><th>Minimum</th><th>Maximum</th><th>Average</th><th>Recorded</th></tr></thead><tbody data-price-results><?= market_price_rows($prices) ?></tbody></table></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>

