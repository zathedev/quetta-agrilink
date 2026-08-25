<?php
/** Orchard Ledger attachment register: local administrator records retain verifiable supporting files. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';

$user = require_role(['admin']);
$attachmentMigrationReady = attachment_migration_is_available();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entityType = normalize_text($_POST['entity_type'] ?? '', 40);
    $entityId = filter_input(INPUT_POST, 'entity_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$attachmentMigrationReady) {
            throw new RuntimeException('Attachment storage is not ready. Import database/migrations/20260825_add_record_attachments.sql into the quetta_agrilink database, then refresh this page.');
        }
        if (!$entityId) {
            throw new RuntimeException('Select a valid record before attaching a file.');
        }
        $attachment = save_record_attachment($_FILES['attachment'] ?? [], (int) $user['id'], $entityType, $entityId);
        flash('success', 'Attachment #' . $attachment['id'] . ' was stored securely.');
    } catch (Throwable $exception) {
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The attachment could not be stored.');
    }
    redirect('admin/attachments.php');
}

$records = [
    'produce_listing' => fetch_all('SELECT id, title AS label FROM produce_listings ORDER BY id DESC LIMIT 50'),
    'storage_facility' => fetch_all('SELECT id, name AS label FROM storage_facilities ORDER BY id DESC LIMIT 50'),
    'vehicle' => fetch_all('SELECT id, CONCAT(vehicle_type, " · ", registration_number) AS label FROM vehicles ORDER BY id DESC LIMIT 50'),
    'market_price' => fetch_all('SELECT mp.id, CONCAT(pc.name, " · ", mp.price_date) AS label FROM market_prices mp JOIN produce_categories pc ON pc.id = mp.category_id ORDER BY mp.id DESC LIMIT 50'),
];
$attachments = $attachmentMigrationReady ? fetch_all('SELECT ra.*, u.full_name FROM record_attachments ra JOIN users u ON u.id = ra.uploader_user_id ORDER BY ra.created_at DESC LIMIT 30') : [];
workspace_open('Record attachments', 'attachments');
?>
<section class="workspace-section attachment-layout">
    <div class="workspace-section-header"><div><h2>Attach supporting records</h2><p>Files are checked for type, size, and record ownership before storage.</p></div></div>
    <?php if ($message = flash('success')): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
    <?php if (!$attachmentMigrationReady): ?><div class="alert alert-error"><strong>Attachment migration required.</strong> Import <code>database/migrations/20260825_add_record_attachments.sql</code> into the same <code>quetta_agrilink</code> database named in <code>config/config.php</code>, then refresh this page.</div><?php else: ?>
    <form class="attachment-form form-grid" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-field"><label for="entity-type">Record type</label><select id="entity-type" name="entity_type" required><?php foreach ($records as $type => $items): ?><option value="<?= e($type) ?>"><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="entity-id">Record ID</label><input id="entity-id" name="entity_id" type="number" min="1" required><span class="form-help">Use the record identifier from its administrator register.</span></div>
        <div class="form-field"><label for="attachment">Supporting file</label><input id="attachment" name="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required><span class="form-help">JPG, PNG, WEBP, or PDF · maximum 5 MB.</span></div>
        <div class="form-actions"><span class="form-help">Executable files and unverified MIME types are rejected.</span><button class="button button-primary" type="submit">Store attachment</button></div>
    </form>
    <?php endif; ?>
</section>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Recent attachment register</h2><p>Metadata is recorded with the administrator and linked operational record.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Record</th><th>Original file</th><th>Type</th><th>Size</th><th>Uploaded by</th><th>Date</th></tr></thead><tbody><?php if ($attachments === []): ?><tr><td colspan="6">No attachments have been stored.</td></tr><?php else: foreach ($attachments as $attachment): ?><tr><td><?= e($attachment['entity_type']) ?> #<?= e((string) $attachment['entity_id']) ?></td><td><?= e($attachment['original_name']) ?></td><td><?= e($attachment['mime_type']) ?></td><td><?= e(number_format((int) $attachment['file_size'] / 1024, 1)) ?> KB</td><td><?= e($attachment['full_name']) ?></td><td><?= e(date('j M Y H:i', strtotime($attachment['created_at']))) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php workspace_close(); ?>
