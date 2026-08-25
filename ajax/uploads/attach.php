<?php
/** Local XAMPP attachment endpoint: administrator-only, CSRF-protected, and metadata-backed. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('POST');
verify_csrf();
$user = require_role(['admin']);
$entityType = normalize_text($_POST['entity_type'] ?? '', 40);
$entityId = filter_input(INPUT_POST, 'entity_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$entityId) {
        throw new RuntimeException('Select a valid record before attaching a file.');
    }
    $attachment = save_record_attachment($_FILES['attachment'] ?? [], (int) $user['id'], $entityType, $entityId);
    json_response(true, 'Attachment stored securely.', ['attachment' => $attachment]);
} catch (Throwable $exception) {
    json_response(false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The attachment could not be stored.', [], 422);
}
