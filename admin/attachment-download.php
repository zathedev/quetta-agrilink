<?php
/** Orchard Ledger attachment delivery: every protected file download is integrity-checked and audited first. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role(['admin']);
$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$attachmentId) {
    http_response_code(404);
    exit('Attachment not found.');
}

try {
    $attachment = prepare_attachment_download((int) $attachmentId, (int) $user['id']);
} catch (Throwable $exception) {
    error_log('Attachment download blocked: ' . $exception->getMessage());
    http_response_code(404);
    exit('Attachment not found or could not be verified.');
}

$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $attachment['original_name']) ?: 'attachment';
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) $attachment['file_size']);
header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, "\\\"") . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($attachment['absolute_path']);
exit;
