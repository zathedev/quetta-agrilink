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
    $row = fetch_one('SELECT COUNT(*) AS count FROM notifications WHERE user_id = :user_id AND read_at IS NULL', ['user_id' => $userId]);
    return (int) ($row['count'] ?? 0);
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
