<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function is_ajax_request(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_response(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_THROW_ON_ERROR);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['_csrf'] ?? '';

    if (!is_string($submitted) || !is_string($stored) || !hash_equals($stored, $submitted)) {
        if (is_ajax_request()) {
            json_response(false, 'Your session could not be verified. Refresh the page and try again.', [], 419);
        }

        http_response_code(419);
        exit('Your session could not be verified. Refresh the page and try again.');
    }
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        if (is_ajax_request()) {
            json_response(false, 'This action does not accept that request method.', [], 405);
        }
        http_response_code(405);
        exit('Method not allowed.');
    }
}

function normalize_text(?string $value, int $maxLength = 255): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function validate_registration(array $input): array
{
    $errors = [];
    $fullName = normalize_text($input['full_name'] ?? '', 120);
    $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = normalize_text($input['phone'] ?? '', 30);
    $role = normalize_text($input['role'] ?? '', 40);
    $password = (string) ($input['password'] ?? '');
    $allowedRoles = ['farmer', 'buyer', 'storage_provider', 'transport_provider'];

    if (mb_strlen($fullName) < 3) {
        $errors['full_name'] = 'Enter your full name using at least 3 characters.';
    }
    if ($email === false) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        $errors['phone'] = 'Enter a valid contact number.';
    }
    if (!in_array($role, $allowedRoles, true)) {
        $errors['role'] = 'Select a valid account type.';
    }
    if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Use at least 10 characters, including a letter and a number.';
    }

    return [$errors, compact('fullName', 'email', 'phone', 'role', 'password')];
}

function register_account(array $input): array
{
    [$errors, $data] = validate_registration($input);
    if ($errors !== []) {
        return [false, 'Please correct the highlighted details.', ['errors' => $errors]];
    }

    $existing = fetch_one('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1', [
        'email' => $data['email'],
        'phone' => $data['phone'],
    ]);
    if ($existing !== null) {
        return [false, 'An account already uses that email address or contact number.', ['errors' => ['email' => 'Use a different email address.']]];
    }

    $roleRow = fetch_one('SELECT id, slug FROM roles WHERE slug = :role AND is_active = 1 LIMIT 1', ['role' => $data['role']]);
    if ($roleRow === null) {
        return [false, 'The selected account type is not currently available.', []];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (:role_id, :full_name, :email, :phone, :password_hash, "active")'
        );
        $statement->execute([
            'role_id' => $roleRow['id'],
            'full_name' => $data['fullName'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $profileTable = match ($roleRow['slug']) {
            'farmer' => 'farmer_profiles',
            'buyer' => 'buyer_profiles',
            'storage_provider' => 'storage_providers',
            'transport_provider' => 'transport_providers',
        };
        $pdo->prepare("INSERT INTO {$profileTable} (user_id) VALUES (:user_id)")->execute(['user_id' => $userId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Account registration failed: ' . $exception->getMessage());
        return [false, 'We could not create the account. Please try again.', []];
    }

    audit_log($userId, 'account_registered', 'users', $userId, ['role' => $roleRow['slug']]);
    return [true, 'Your account has been created. Please sign in to continue.', []];
}

function authenticate(string $email, string $password): array
{
    $user = fetch_one(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1',
        ['email' => trim($email)]
    );

    if ($user === null || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return [false, 'The email address or password is incorrect.', []];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity_at'] = time();
    unset($_SESSION['_csrf']);
    execute_query('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
    audit_log((int) $user['id'], 'login', 'users', (int) $user['id']);

    return [true, 'You are signed in.', ['role' => $user['role_slug']]];
}

/** Local XAMPP recovery uses an administrator-issued link, never an unconfigured external email service. */
function local_password_recovery_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'local_password_recovery_requests']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function onboarding_state_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name', ['table_name' => 'users', 'column_name' => 'onboarding_completed_at']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function onboarding_is_complete(int $userId): bool
{
    if ($userId < 1 || !onboarding_state_is_available()) {
        return true;
    }
    $row = fetch_one('SELECT onboarding_completed_at FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
    return $row !== null && $row['onboarding_completed_at'] !== null;
}

function complete_onboarding(int $userId): void
{
    if ($userId < 1 || !onboarding_state_is_available()) {
        return;
    }
    execute_query('UPDATE users SET onboarding_completed_at = COALESCE(onboarding_completed_at, NOW()) WHERE id = :id', ['id' => $userId]);
    audit_log($userId, 'onboarding_completed', 'users', $userId);
}

function request_local_password_recovery(string $email): void
{
    if (!local_password_recovery_is_available()) {
        error_log('Local password recovery request received before migration was imported.');
        return;
    }
    $now = time();
    if (($now - (int) ($_SESSION['local_recovery_requested_at'] ?? 0)) < 60) {
        return;
    }
    $_SESSION['local_recovery_requested_at'] = $now;
    $user = fetch_one('SELECT id FROM users WHERE email = :email AND status = "active" LIMIT 1', ['email' => trim($email)]);
    if ($user === null) {
        return;
    }
    execute_query(
        'INSERT INTO local_password_recovery_requests (user_id, requested_ip) VALUES (:user_id, :ip)',
        ['user_id' => (int) $user['id'], 'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]
    );
    audit_log((int) $user['id'], 'local_password_recovery_requested', 'users', (int) $user['id']);
}

function issue_local_password_reset(int $requestId, int $administratorId): string
{
    if (!local_password_recovery_is_available()) {
        throw new RuntimeException('Password recovery is not ready. Import database/migrations/20260826_add_local_password_recovery.sql, then refresh this page.');
    }
    $request = fetch_one(
        'SELECT r.id, r.user_id FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id WHERE r.id = :id AND u.status = "active" LIMIT 1',
        ['id' => $requestId]
    );
    if ($request === null) {
        throw new RuntimeException('That recovery request is no longer available.');
    }
    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE local_password_recovery_requests SET revoked_at = NOW(), revoked_by_user_id = :administrator_id WHERE user_id = :user_id AND selector IS NOT NULL AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()')
            ->execute(['administrator_id' => $administratorId, 'user_id' => (int) $request['user_id']]);
        $pdo->prepare('UPDATE local_password_recovery_requests SET issued_by_user_id = :administrator_id, issued_at = NOW(), selector = :selector, token_hash = :token_hash, expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE), used_at = NULL, revoked_at = NULL, revoked_by_user_id = NULL WHERE id = :id')
            ->execute(['administrator_id' => $administratorId, 'selector' => $selector, 'token_hash' => hash('sha256', $token), 'id' => $requestId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    audit_log($administratorId, 'local_password_reset_issued', 'local_password_recovery_requests', $requestId, ['user_id' => (int) $request['user_id'], 'expires_in_minutes' => 60]);
    return app_url('auth/reset-password.php?selector=' . rawurlencode($selector) . '&token=' . rawurlencode($token));
}

function revoke_local_password_reset(int $requestId, int $administratorId): void
{
    $affected = execute_query('UPDATE local_password_recovery_requests SET revoked_at = NOW(), revoked_by_user_id = :administrator_id WHERE id = :id AND selector IS NOT NULL AND used_at IS NULL AND revoked_at IS NULL', ['administrator_id' => $administratorId, 'id' => $requestId]);
    if ($affected < 1) {
        throw new RuntimeException('That reset link is not active.');
    }
    audit_log($administratorId, 'local_password_reset_revoked', 'local_password_recovery_requests', $requestId);
}

function resolve_local_password_reset(string $selector, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token) || !local_password_recovery_is_available()) {
        return null;
    }
    $request = fetch_one('SELECT r.id, r.user_id, r.token_hash, u.full_name FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id WHERE r.selector = :selector AND r.used_at IS NULL AND r.revoked_at IS NULL AND r.expires_at > NOW() LIMIT 1', ['selector' => $selector]);
    if ($request === null || !hash_equals((string) $request['token_hash'], hash('sha256', $token))) {
        return null;
    }
    return $request;
}

function complete_local_password_reset(int $requestId, string $selector, string $token, string $password): void
{
    if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new RuntimeException('Use at least 10 characters, including a letter and a number.');
    }
    $request = resolve_local_password_reset($selector, $token);
    if ($request === null || (int) $request['id'] !== $requestId) {
        throw new RuntimeException('This reset link is invalid, expired, or already used.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id')->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => (int) $request['user_id']]);
        $consume = $pdo->prepare('UPDATE local_password_recovery_requests SET used_at = NOW() WHERE id = :id AND selector = :selector AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()');
        $consume->execute(['id' => $requestId, 'selector' => $selector]);
        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('This reset link is no longer available.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    audit_log((int) $request['user_id'], 'local_password_reset_completed', 'local_password_recovery_requests', $requestId);
}

function current_user(): ?array
{
    static $userLoaded = false;
    static $user = null;

    if ($userLoaded) {
        return $user;
    }
    $userLoaded = true;

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $user = fetch_one(
        'SELECT u.id, u.full_name, u.email, u.phone, u.status, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1',
        ['id' => $userId]
    );

    if ($user === null || $user['status'] !== 'active') {
        clear_authenticated_session();
        return null;
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        if (is_ajax_request()) {
            json_response(false, 'Please sign in to continue.', [], 401);
        }
        flash('error', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role_slug'], $roles, true)) {
        if (is_ajax_request()) {
            json_response(false, 'You are not authorized to perform this action.', [], 403);
        }
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
    return $user;
}

function dashboard_path(string $role): string
{
    return match ($role) {
        'farmer' => 'farmer/dashboard.php',
        'buyer' => 'buyer/dashboard.php',
        'storage_provider' => 'storage/dashboard.php',
        'transport_provider' => 'transport/dashboard.php',
        'admin' => 'admin/dashboard.php',
        default => '',
    };
}

function clear_authenticated_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], (bool) $parameters['secure'], (bool) $parameters['httponly']);
    }
    session_destroy();
}

function audit_log(?int $actorId, string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
{
    try {
        execute_query(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, ip_address) VALUES (:actor, :action, :entity_type, :entity_id, :metadata, :ip)',
            [
                'actor' => $actorId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]
        );
    } catch (Throwable $exception) {
        error_log('Audit log failed: ' . $exception->getMessage());
    }
}

function unread_notification_count(int $userId): int
{
    return (int) unread_notification_summary($userId)['count'];
}

function unread_notification_summary(int $userId): array
{
    $row = fetch_one('SELECT COUNT(*) AS count, COALESCE(MAX(id), 0) AS latest_id FROM notifications WHERE user_id = :user_id AND read_at IS NULL', ['user_id' => $userId]);
    return ['count' => (int) ($row['count'] ?? 0), 'latest_id' => (int) ($row['latest_id'] ?? 0)];
}

function create_notification(int $userId, string $type, string $title, string $body, ?string $actionUrl = null, ?string $entityType = null, ?int $entityId = null): void
{
    execute_query(
        'INSERT INTO notifications (user_id, type, title, body, action_url, entity_type, entity_id) VALUES (:user_id, :type, :title, :body, :action_url, :entity_type, :entity_id)',
        [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]
    );
}

function mark_notification_read(int $userId, int $notificationId): bool
{
    if ($userId < 1 || $notificationId < 1) {
        return false;
    }
    $notification = fetch_one('SELECT id FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1', ['id' => $notificationId, 'user_id' => $userId]);
    if ($notification === null) {
        throw new RuntimeException('That notification is not available in your account.');
    }
    $affected = execute_query('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :user_id', ['id' => $notificationId, 'user_id' => $userId]);
    if ($affected > 0) {
        audit_log($userId, 'notification_read', 'notifications', $notificationId);
    }
    return $affected > 0;
}

function mark_all_notifications_read(int $userId): int
{
    if ($userId < 1) {
        return 0;
    }
    $affected = execute_query('UPDATE notifications SET read_at = NOW() WHERE user_id = :user_id AND read_at IS NULL', ['user_id' => $userId]);
    if ($affected > 0) {
        audit_log($userId, 'notifications_read_all', 'notifications', null, ['count' => $affected]);
    }
    return $affected;
}

function positive_decimal(mixed $value, int $scale = 2): ?float
{
    if (!is_scalar($value) || !is_numeric($value) || (float) $value <= 0) {
        return null;
    }
    return round((float) $value, $scale);
}

function generate_reference(string $prefix): string
{
    $table = match ($prefix) {
        'QAL' => 'orders',
        'QAS' => 'storage_bookings',
        'QAT' => 'transport_requests',
        default => throw new InvalidArgumentException('Unsupported reference prefix.'),
    };

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $reference = sprintf('%s-%s-%04d', $prefix, date('Y'), random_int(1, 9999));
        $existing = fetch_one("SELECT id FROM {$table} WHERE reference_code = :reference LIMIT 1", ['reference' => $reference]);
        if ($existing === null) {
            return $reference;
        }
    }
    throw new RuntimeException('A unique reference could not be generated.');
}

/**
 * Store an administrator-supplied attachment outside executable paths and return verified metadata.
 * The caller remains responsible for persisting the metadata in its database transaction.
 */
function validate_and_store_attachment(array $file, string $entityType, int $entityId): array
{
    $entityTables = [
        'produce_listing' => 'produce_listings',
        'storage_facility' => 'storage_facilities',
        'vehicle' => 'vehicles',
        'market_price' => 'market_prices',
    ];
    if (!isset($entityTables[$entityType]) || $entityId < 1) {
        throw new RuntimeException('Select a valid record before attaching a file.');
    }
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('The file exceeds the PHP upload limit. Set upload_max_filesize to at least 6M and post_max_size to at least 8M, then restart Apache.');
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a file to attach.');
    }
    if (!isset($file['tmp_name'], $file['size'], $file['name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded file could not be verified.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The file must be between 1 byte and 5 MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and PDF attachments are accepted.');
    }
    if (str_starts_with($mime, 'image/') && getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('The image file could not be validated.');
    }

    $entity = fetch_one('SELECT id FROM ' . $entityTables[$entityType] . ' WHERE id = :id LIMIT 1', ['id' => $entityId]);
    if ($entity === null) {
        throw new RuntimeException('The selected record no longer exists.');
    }
    $directory = 'attachments/' . date('Y') . '/' . date('m');
    $absoluteDirectory = rtrim(UPLOAD_STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0750, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('The attachment directory could not be created.');
    }
    $storedName = bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    $relativePath = $directory . '/' . $storedName;
    $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('The attachment could not be stored.');
    }
    @chmod($absolutePath, 0640);

    return [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'original_name' => normalize_text((string) $file['name'], 180),
        'stored_name' => $storedName,
        'relative_path' => $relativePath,
        'mime_type' => $mime,
        'file_size' => (int) $file['size'],
        'sha256' => hash_file('sha256', $absolutePath),
        'absolute_path' => $absolutePath,
    ];
}

function save_record_attachment(array $file, int $uploaderId, string $entityType, int $entityId): array
{
    if (!attachment_migration_is_available()) {
        throw new RuntimeException('Attachment storage is not ready. Import database/migrations/20260825_add_record_attachments.sql into the quetta_agrilink database, then refresh this page.');
    }
    $attachment = validate_and_store_attachment($file, $entityType, $entityId);
    $pdo = db();
    try {
        $statement = $pdo->prepare('INSERT INTO record_attachments (entity_type, entity_id, uploader_user_id, original_name, stored_name, relative_path, mime_type, file_size, sha256) VALUES (:entity_type, :entity_id, :uploader, :original_name, :stored_name, :relative_path, :mime_type, :file_size, :sha256)');
        $statement->execute([
            'entity_type' => $attachment['entity_type'],
            'entity_id' => $attachment['entity_id'],
            'uploader' => $uploaderId,
            'original_name' => $attachment['original_name'],
            'stored_name' => $attachment['stored_name'],
            'relative_path' => $attachment['relative_path'],
            'mime_type' => $attachment['mime_type'],
            'file_size' => $attachment['file_size'],
            'sha256' => $attachment['sha256'],
        ]);
        $attachment['id'] = (int) $pdo->lastInsertId();
        audit_log($uploaderId, 'attachment_uploaded', 'record_attachments', $attachment['id'], ['entity_type' => $entityType, 'entity_id' => $entityId]);
        unset($attachment['absolute_path']);
        return $attachment;
    } catch (Throwable $exception) {
        @unlink($attachment['absolute_path']);
        throw $exception;
    }
}

function attachment_migration_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'record_attachments']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * Resolve an attachment for a protected administrator download and write its accountability record
 * before any response headers or file contents are sent.
 */
function prepare_attachment_download(int $attachmentId, int $actorId): array
{
    if ($attachmentId < 1 || !attachment_migration_is_available()) {
        throw new RuntimeException('The requested attachment is not available.');
    }

    $attachment = fetch_one(
        'SELECT id, entity_type, entity_id, original_name, relative_path, mime_type, file_size, sha256 FROM record_attachments WHERE id = :id LIMIT 1',
        ['id' => $attachmentId]
    );
    if ($attachment === null) {
        throw new RuntimeException('The requested attachment is not available.');
    }

    $storageRoot = realpath(UPLOAD_STORAGE_PATH);
    $candidatePath = $storageRoot === false ? false : realpath($storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $attachment['relative_path'], '/\\')));
    $rootWithSeparator = $storageRoot === false ? '' : rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $isWithinStorage = is_string($candidatePath) && str_starts_with($candidatePath, $rootWithSeparator);
    if (!$isWithinStorage || !is_file($candidatePath) || !is_readable($candidatePath)) {
        throw new RuntimeException('The stored attachment could not be verified.');
    }

    $actualHash = hash_file('sha256', $candidatePath);
    if (!is_string($actualHash) || !hash_equals((string) $attachment['sha256'], $actualHash)) {
        throw new RuntimeException('The stored attachment failed its integrity check.');
    }

    execute_query(
        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, ip_address) VALUES (:actor, :action, :entity_type, :entity_id, :metadata, :ip)',
        [
            'actor' => $actorId,
            'action' => 'attachment_downloaded',
            'entity_type' => 'record_attachments',
            'entity_id' => $attachmentId,
            'metadata' => json_encode([
                'original_name' => $attachment['original_name'],
                'record_type' => $attachment['entity_type'],
                'record_id' => (int) $attachment['entity_id'],
                'file_size' => (int) $attachment['file_size'],
            ], JSON_THROW_ON_ERROR),
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]
    );

    $attachment['absolute_path'] = $candidatePath;
    return $attachment;
}
