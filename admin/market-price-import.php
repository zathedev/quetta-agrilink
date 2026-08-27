<?php
/** Orchard Ledger local market-data intake: protected, source-led imports turn approved CSV records into accountable reference prices without retaining the upload. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/market-price-imports.php';
require_once __DIR__ . '/../includes/workspace.php';

$administrator = require_role(['admin']);
$importReady = market_price_imports_are_available();
$validationErrors = [];
$summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        [$success, $result] = market_price_import_csv((int) $administrator['id'], $_FILES['market_price_csv'] ?? [], $_POST);
        if (!$success) {
            $validationErrors = $result['errors'];
        } else {
            $summary = $result;
            flash('success', 'Market-data batch #' . $result['batch_id'] . ' recorded: ' . $result['inserted_rows'] . ' new and ' . $result['updated_rows'] . ' updated reference rows.');
            redirect('admin/market-price-import.php');
        }
    } catch (Throwable $exception) {
        $validationErrors = [$exception->getMessage()];
    }
}
$history = market_price_import_history();
workspace_open('Local market-data import', 'market_price_import');
?>
<section class="workspace-section market-import-intro"><div class="workspace-section-header"><div><p class="desk-kicker">Administrator data intake</p><h2>Import approved local price references.</h2><p>Use a source-owned CSV only after its market context has been checked. The importer validates every row before saving anything, records the source and accountable administrator, and never retains the uploaded file.</p></div></div><div class="market-import-rule"><strong>Record context, not a guarantee</strong><span>Market prices are reference information. They are not an offer, a payment request, financial advice, or a promise of future availability.</span></div></section>
<?php if (!$importReady): ?><section class="workspace-section market-import-blocker"><h2>Import register is not ready.</h2><p>Import <code>database/migrations/20260827_add_market_price_imports.sql</code>, then refresh this page. No file can be processed until source and batch history are available.</p></section><?php else: ?>
<section class="workspace-section market-import-form-section"><div class="workspace-section-header"><div><p class="desk-kicker">Validated CSV intake</p><h2>Check the source before the numbers enter the register.</h2><p>Upload a CSV with the exact header sequence shown below. A failed validation saves no price row and no batch record; correct the source file locally, then try again.</p></div></div><?php if ($validationErrors !== []): ?><div class="flash flash-error"><strong>No data was imported.</strong><ul><?php foreach ($validationErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form class="form-grid market-import-form" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="form-field"><label for="source-name">Accountable source</label><input id="source-name" name="source_name" required maxlength="160" value="<?= e((string) ($_POST['source_name'] ?? '')) ?>" placeholder="e.g. Quetta wholesale market committee"></div><div class="form-field"><label for="source-reference">Source reference</label><input id="source-reference" name="source_reference" maxlength="255" value="<?= e((string) ($_POST['source_reference'] ?? '')) ?>" placeholder="e.g. circular, register, or collection date"></div><div class="form-field market-import-file"><label for="market-price-csv">Approved CSV file</label><input id="market-price-csv" name="market_price_csv" type="file" accept=".csv,text/csv" required><span class="form-help">Maximum 2 MB and 500 data rows. The file is read for validation and not kept.</span></div><div class="market-import-schema"><strong>Required header, in this order</strong><code>product,district,minimum_price,maximum_price,average_price,unit,price_date,notes</code><span>Use active local product names and existing districts. Use YYYY-MM-DD dates; keep minimum ≤ average ≤ maximum.</span></div><div class="form-actions"><button class="button button-primary" type="submit">Validate and import local prices</button></div></form></section><?php endif; ?>
<section class="workspace-section market-import-history"><div class="workspace-section-header"><div><p class="desk-kicker">Protected import history</p><h2>Source and batch accountability</h2><p>History records source context, importer, row totals, and time. It never stores the source file, passwords, recovery data, or other account secrets.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Source</th><th>File record</th><th>Result</th><th>Administrator</th></tr></thead><tbody><?php if ($history === []): ?><tr><td colspan="5">No local market-data batches have been imported.</td></tr><?php else: foreach ($history as $batch): ?><tr><td><?= e(date('j M Y H:i', strtotime((string) $batch['created_at']))) ?></td><td><strong><?= e($batch['source_name']) ?></strong><?php if ($batch['source_reference'] !== null): ?><br><span class="muted"><?= e($batch['source_reference']) ?></span><?php endif; ?></td><td><?= e($batch['original_filename']) ?><br><span class="muted"><?= (int) $batch['total_rows'] ?> validated row<?= (int) $batch['total_rows'] === 1 ? '' : 's' ?></span></td><td><?= (int) $batch['inserted_rows'] ?> new<br><?= (int) $batch['updated_rows'] ?> updated</td><td><?= e($batch['importer_name']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
