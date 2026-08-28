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

    $fullNameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);
    if ($fullNameLength < 3) {
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

function recovery_verification_notes_are_available(): bool
{
    if (!local_password_recovery_is_available()) {
        return false;
    }
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name', ['table_name' => 'local_password_recovery_requests', 'column_name' => 'verification_notes']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function account_contact_verification_is_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'account_contact_verifications']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function contact_review_reason_codes_are_available(): bool
{
    if (!account_contact_verification_is_available()) {
        return false;
    }
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name', ['table_name' => 'account_contact_verifications', 'column_name' => 'review_reason_code']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function contact_review_reason_catalog(): array
{
    return [
        'registration_record' => 'Compared with local registration record',
        'in_person_review' => 'Reviewed in person by authorised staff',
        'trade_document' => 'Compared with approved local trade document',
        'organisation_referral' => 'Confirmed through approved organisation referral',
        'other_local_evidence' => 'Other approved local evidence',
    ];
}

function contact_review_reason_label(?string $reasonCode): string
{
    $catalog = contact_review_reason_catalog();
    return $reasonCode !== null && isset($catalog[$reasonCode]) ? $catalog[$reasonCode] : 'Legacy local review record';
}

function account_contact_verification(int $userId): ?array
{
    if ($userId < 1 || !account_contact_verification_is_available()) {
        return null;
    }

    $reasonField = contact_review_reason_codes_are_available() ? 'v.review_reason_code,' : 'NULL AS review_reason_code,';
    return fetch_one('SELECT v.verified_email_at, v.verified_phone_at, v.verification_notes, ' . $reasonField . ' v.updated_at, verifier.full_name AS verified_by_name FROM account_contact_verifications v LEFT JOIN users verifier ON verifier.id = v.verified_by_user_id WHERE v.user_id = :user_id LIMIT 1', ['user_id' => $userId]);
}

function record_account_contact_verification(int $userId, int $administratorId, string $scope, string $reasonCode, string $note): void
{
    if (!account_contact_verification_is_available() || !contact_review_reason_codes_are_available()) {
        throw new RuntimeException('Contact verification is not ready. Import the account contact verification and contact review reason migrations, then refresh this page.');
    }
    if ($userId < 1 || $administratorId < 1 || !in_array($scope, ['email', 'phone', 'both'], true)) {
        throw new RuntimeException('Choose a valid account and contact review scope.');
    }
    if (!array_key_exists($reasonCode, contact_review_reason_catalog())) {
        throw new RuntimeException('Choose a valid local contact-review reason.');
    }

    $note = normalize_text($note, 800);
    if ((function_exists('mb_strlen') ? mb_strlen($note) : strlen($note)) < 8) {
        throw new RuntimeException('Record a brief description of how the current contact was reviewed.');
    }
    if (preg_match('#https?://|reset-password\.php|token=|password#i', $note)) {
        throw new RuntimeException('Do not store passwords, reset links, tokens, or credentials in a contact-verification note.');
    }

    $account = fetch_one('SELECT id, email, phone FROM users WHERE id = :id AND status = "active" LIMIT 1', ['id' => $userId]);
    if ($account === null || ($scope !== 'phone' && trim((string) $account['email']) === '') || ($scope !== 'email' && trim((string) $account['phone']) === '')) {
        throw new RuntimeException('The selected account no longer has the contact detail chosen for review.');
    }

    $emailAt = $scope === 'email' || $scope === 'both' ? date('Y-m-d H:i:s') : null;
    $phoneAt = $scope === 'phone' || $scope === 'both' ? date('Y-m-d H:i:s') : null;
    $statement = db()->prepare('INSERT INTO account_contact_verifications (user_id, verified_email_at, verified_phone_at, verification_notes, review_reason_code, verified_by_user_id) VALUES (:user_id, :email_at, :phone_at, :note, :reason_code, :administrator_id) ON DUPLICATE KEY UPDATE verification_notes = VALUES(verification_notes), review_reason_code = VALUES(review_reason_code), verified_by_user_id = VALUES(verified_by_user_id), verified_email_at = CASE WHEN VALUES(verified_email_at) IS NOT NULL THEN VALUES(verified_email_at) ELSE verified_email_at END, verified_phone_at = CASE WHEN VALUES(verified_phone_at) IS NOT NULL THEN VALUES(verified_phone_at) ELSE verified_phone_at END, updated_at = NOW()');
    $statement->execute(['user_id' => $userId, 'email_at' => $emailAt, 'phone_at' => $phoneAt, 'note' => $note, 'reason_code' => $reasonCode, 'administrator_id' => $administratorId]);
    audit_log($administratorId, 'account_contact_verification_recorded', 'users', $userId, ['scope' => $scope, 'reason_code' => $reasonCode]);
}

function contact_review_register_filters(array $query): array
{
    $search = normalize_text($query['search'] ?? '', 80);
    $statusInput = (string) ($query['status'] ?? 'all');
    $status = in_array($statusInput, ['all', 'needs_review', 'email_reviewed', 'phone_reviewed', 'fully_reviewed'], true) ? $statusInput : 'all';
    $reason = normalize_text($query['reason'] ?? '', 48);
    if (!array_key_exists($reason, contact_review_reason_catalog())) {
        $reason = '';
    }
    return ['search' => $search, 'status' => $status, 'reason' => $reason];
}

function contact_review_register_query(array $filters): string
{
    return http_build_query(array_filter([
        'search' => ($filters['search'] ?? '') !== '' ? $filters['search'] : null,
        'status' => ($filters['status'] ?? 'all') !== 'all' ? $filters['status'] : null,
        'reason' => ($filters['reason'] ?? '') !== '' ? $filters['reason'] : null,
    ], static fn (mixed $value): bool => $value !== null));
}

function contact_review_register_rows(array $filters, ?int $limit = null): array
{
    if (!account_contact_verification_is_available() || !contact_review_reason_codes_are_available()) {
        return [];
    }
    $conditions = ['u.status = "active"'];
    $params = [];
    $search = (string) ($filters['search'] ?? '');
    $status = (string) ($filters['status'] ?? 'all');
    $reason = (string) ($filters['reason'] ?? '');
    if ($search !== '') {
        $conditions[] = '(u.full_name LIKE :contact_search_name OR u.email LIKE :contact_search_email OR u.phone LIKE :contact_search_phone)';
        $params['contact_search_name'] = '%' . $search . '%';
        $params['contact_search_email'] = '%' . $search . '%';
        $params['contact_search_phone'] = '%' . $search . '%';
    }
    if ($status === 'needs_review') { $conditions[] = '(v.user_id IS NULL OR v.verified_email_at IS NULL OR v.verified_phone_at IS NULL)'; }
    if ($status === 'email_reviewed') { $conditions[] = 'v.verified_email_at IS NOT NULL'; }
    if ($status === 'phone_reviewed') { $conditions[] = 'v.verified_phone_at IS NOT NULL'; }
    if ($status === 'fully_reviewed') { $conditions[] = 'v.verified_email_at IS NOT NULL AND v.verified_phone_at IS NOT NULL'; }
    if ($reason !== '') { $conditions[] = 'v.review_reason_code = :contact_reason'; $params['contact_reason'] = $reason; }
    $sql = 'SELECT u.id, u.full_name, u.email, u.phone, r.name AS role_name, v.verified_email_at, v.verified_phone_at, v.verification_notes, v.review_reason_code, v.updated_at, verifier.full_name AS verified_by_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN account_contact_verifications v ON v.user_id = u.id LEFT JOIN users verifier ON verifier.id = v.verified_by_user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY u.full_name ASC';
    if ($limit !== null) { $sql .= ' LIMIT ' . max(1, min(1000, $limit)); }
    return fetch_all($sql, $params);
}

function local_recovery_audit_date_range(array $query): array
{
    $parseDate = static function (mixed $value): ?DateTimeImmutable {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    };
    $from = $parseDate($query['recovery_from'] ?? null);
    $to = $parseDate($query['recovery_to'] ?? null);
    if ($from !== null && $to !== null && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    return ['from' => $from, 'to' => $to];
}

function local_recovery_audit_role_options(): array
{
    return fetch_all('SELECT slug, name FROM roles WHERE is_active = 1 ORDER BY name ASC');
}

function local_recovery_audit_district_options(): array
{
    return fetch_all('SELECT DISTINCT account_locations.district FROM (SELECT l.district FROM farmer_profiles p JOIN locations l ON l.id = p.farm_location_id UNION SELECT l.district FROM buyer_profiles p JOIN locations l ON l.id = p.location_id UNION SELECT l.district FROM storage_providers p JOIN locations l ON l.id = p.location_id UNION SELECT l.district FROM transport_providers p JOIN locations l ON l.id = p.location_id) account_locations WHERE account_locations.district <> "" ORDER BY account_locations.district ASC');
}

function local_recovery_audit_filters(array $query): array
{
    $range = local_recovery_audit_date_range($query);
    $roles = local_recovery_audit_role_options();
    $allowedRoles = array_column($roles, 'slug');
    $role = normalize_text($query['recovery_role'] ?? '', 40);
    if (!in_array($role, $allowedRoles, true)) { $role = ''; }
    $districts = local_recovery_audit_district_options();
    $allowedDistricts = array_column($districts, 'district');
    $district = normalize_text($query['recovery_district'] ?? '', 100);
    if (!in_array($district, $allowedDistricts, true)) { $district = ''; }
    return $range + ['role' => $role, 'district' => $district];
}

function local_recovery_audit_filter_query(array $filters): string
{
    return http_build_query(array_filter([
        'recovery_from' => ($filters['from'] ?? null) instanceof DateTimeImmutable ? $filters['from']->format('Y-m-d') : null,
        'recovery_to' => ($filters['to'] ?? null) instanceof DateTimeImmutable ? $filters['to']->format('Y-m-d') : null,
        'recovery_role' => ($filters['role'] ?? '') !== '' ? $filters['role'] : null,
        'recovery_district' => ($filters['district'] ?? '') !== '' ? $filters['district'] : null,
    ], static fn (mixed $value): bool => $value !== null));
}

function local_recovery_audit_rows(array $range, ?int $limit = null): array
{
    $conditions = ['1 = 1'];
    $params = [];
    if (($range['from'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'r.requested_at >= :recovery_from';
        $params['recovery_from'] = $range['from']->format('Y-m-d 00:00:00');
    }
    if (($range['to'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'r.requested_at < :recovery_to_exclusive';
        $params['recovery_to_exclusive'] = $range['to']->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    if (($range['role'] ?? '') !== '') { $conditions[] = 'role.slug = :recovery_role'; $params['recovery_role'] = $range['role']; }
    if (($range['district'] ?? '') !== '') { $conditions[] = 'COALESCE(farmer_location.district, buyer_location.district, storage_location.district, transport_location.district, "") = :recovery_district'; $params['recovery_district'] = $range['district']; }
    $sql = 'SELECT r.id, r.requested_at, r.verified_at, r.issued_at, r.expires_at, r.used_at, r.revoked_at, r.verification_notes, u.full_name, u.email, role.name AS role_name, role.slug AS role_slug, COALESCE(farmer_location.district, buyer_location.district, storage_location.district, transport_location.district, "") AS district, verifier.full_name AS verified_by_name, issuer.full_name AS issued_by_name, revoker.full_name AS revoked_by_name FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id JOIN roles role ON role.id = u.role_id LEFT JOIN farmer_profiles farmer_profile ON farmer_profile.user_id = u.id LEFT JOIN locations farmer_location ON farmer_location.id = farmer_profile.farm_location_id LEFT JOIN buyer_profiles buyer_profile ON buyer_profile.user_id = u.id LEFT JOIN locations buyer_location ON buyer_location.id = buyer_profile.location_id LEFT JOIN storage_providers storage_profile ON storage_profile.user_id = u.id LEFT JOIN locations storage_location ON storage_location.id = storage_profile.location_id LEFT JOIN transport_providers transport_profile ON transport_profile.user_id = u.id LEFT JOIN locations transport_location ON transport_location.id = transport_profile.location_id LEFT JOIN users verifier ON verifier.id = r.verified_by_user_id LEFT JOIN users issuer ON issuer.id = r.issued_by_user_id LEFT JOIN users revoker ON revoker.id = r.revoked_by_user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY r.requested_at DESC, r.id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, min(500, $limit));
    }
    return fetch_all($sql, $params);
}

function csv_safe_cell(mixed $value): string
{
    $cell = (string) ($value ?? '');
    return preg_match('/^[=+\-@]/', $cell) === 1 ? "'" . $cell : $cell;
}

function save_recovery_verification_note(int $requestId, int $administratorId, string $note): void
{
    if (!recovery_verification_notes_are_available()) {
        throw new RuntimeException('Recovery verification notes are not ready. Import database/migrations/20260826_add_recovery_verification_notes.sql, then refresh this page.');
    }
    $note = normalize_text($note, 800);
    if ((function_exists('mb_strlen') ? mb_strlen($note) : strlen($note)) < 8) {
        throw new RuntimeException('Record at least a brief description of how identity was verified before issuing a link.');
    }
    if (preg_match('#https?://|reset-password\.php|token=#i', $note)) {
        throw new RuntimeException('Do not store reset links or tokens in the verification note.');
    }
    $updated = execute_query('UPDATE local_password_recovery_requests SET verification_notes = :note, verified_by_user_id = :administrator_id, verified_at = NOW() WHERE id = :id', ['note' => $note, 'administrator_id' => $administratorId, 'id' => $requestId]);
    if (!$updated) {
        throw new RuntimeException('That recovery request is no longer available.');
    }
    audit_log($administratorId, 'local_recovery_verification_recorded', 'local_password_recovery_requests', $requestId);
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

function update_account_profile(int $userId, array $input): array
{
    if ($userId < 1) {
        return [false, 'Your account could not be identified.', []];
    }
    $fullName = normalize_text($input['full_name'] ?? '', 120);
    $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = normalize_text($input['phone'] ?? '', 30);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);
    $errors = [];
    if ($nameLength < 3) {
        $errors['full_name'] = 'Enter your full name using at least 3 characters.';
    }
    if ($email === false) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        $errors['phone'] = 'Enter a valid contact number.';
    }
    if ($errors !== []) {
        return [false, 'Please correct the highlighted details.', ['errors' => $errors]];
    }
    $duplicate = fetch_one('SELECT id FROM users WHERE (email = :email OR phone = :phone) AND id <> :user_id LIMIT 1', ['email' => $email, 'phone' => $phone, 'user_id' => $userId]);
    if ($duplicate !== null) {
        return [false, 'That email address or contact number is already connected to another account.', ['errors' => ['email' => 'Use a different email address or contact number.']]];
    }
    $current = fetch_one('SELECT full_name, email, phone FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
    if ($current === null) {
        return [false, 'Your account is no longer available.', []];
    }
    $changed = [];
    foreach (['full_name' => $fullName, 'email' => $email, 'phone' => $phone] as $field => $value) {
        if (!hash_equals((string) $current[$field], (string) $value)) {
            $changed[] = $field;
        }
    }
    if ($changed === []) {
        return [true, 'Your profile is already up to date.', ['changed' => []]];
    }
    execute_query('UPDATE users SET full_name = :full_name, email = :email, phone = :phone WHERE id = :id', ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'id' => $userId]);
    if (account_contact_verification_is_available() && (in_array('email', $changed, true) || in_array('phone', $changed, true))) {
        if (in_array('email', $changed, true) && in_array('phone', $changed, true)) {
            execute_query('UPDATE account_contact_verifications SET verified_email_at = NULL, verified_phone_at = NULL WHERE user_id = :user_id', ['user_id' => $userId]);
        } elseif (in_array('email', $changed, true)) {
            execute_query('UPDATE account_contact_verifications SET verified_email_at = NULL WHERE user_id = :user_id', ['user_id' => $userId]);
        } else {
            execute_query('UPDATE account_contact_verifications SET verified_phone_at = NULL WHERE user_id = :user_id', ['user_id' => $userId]);
        }
    }
    audit_log($userId, 'profile_updated', 'users', $userId, ['fields' => $changed]);
    return [true, 'Your profile details have been updated.', ['changed' => $changed]];
}

function role_profile_locations(): array
{
    return fetch_all('SELECT id, province, district, tehsil, area FROM locations ORDER BY province ASC, district ASC, tehsil ASC, area ASC');
}

function role_business_profile(int $userId, string $role): ?array
{
    return match ($role) {
        'farmer' => fetch_one('SELECT farm_name, farm_location_id AS location_id, farm_size_acres, bio FROM farmer_profiles WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]),
        'buyer' => fetch_one('SELECT business_name, business_type, location_id, tax_reference, bio FROM buyer_profiles WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]),
        default => null,
    };
}

function initialize_role_business_profile(int $userId, string $role): ?array
{
    $table = match ($role) {
        'farmer' => 'farmer_profiles',
        'buyer' => 'buyer_profiles',
        default => null,
    };
    if ($userId < 1 || $table === null) {
        return null;
    }
    $statement = db()->prepare("INSERT INTO {$table} (user_id) SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :user_id AND r.slug = :role ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
    $statement->execute(['user_id' => $userId, 'role' => $role]);
    return role_business_profile($userId, $role);
}

function role_profile_location_exists(int $locationId): bool
{
    return $locationId > 0 && fetch_one('SELECT id FROM locations WHERE id = :location_id LIMIT 1', ['location_id' => $locationId]) !== null;
}

function optional_profile_decimal(mixed $value): ?float
{
    if (!is_scalar($value) || trim((string) $value) === '') {
        return null;
    }
    $number = positive_decimal($value);
    return $number !== null && $number <= 100000 ? $number : null;
}

function role_profile_field_changed(string $field, mixed $current, mixed $value): bool
{
    if ($field === 'farm_size_acres') {
        return round((float) ($current ?? 0), 2) !== round((float) ($value ?? 0), 2);
    }
    return (string) ($current ?? '') !== (string) ($value ?? '');
}

function update_farmer_business_profile(int $userId, array $input): array
{
    $farmName = normalize_text($input['farm_name'] ?? '', 160);
    $locationId = filter_var($input['farm_location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $farmSizeInput = trim((string) ($input['farm_size_acres'] ?? ''));
    $farmSize = optional_profile_decimal($farmSizeInput);
    $bio = normalize_text($input['farm_bio'] ?? '', 1000);
    $errors = [];
    if ((function_exists('mb_strlen') ? mb_strlen($farmName) : strlen($farmName)) < 3) {
        $errors['farm_name'] = 'Enter a farm name using at least 3 characters.';
    }
    if (!role_profile_location_exists($locationId)) {
        $errors['farm_location_id'] = 'Choose a listed farm location.';
    }
    if ($farmSizeInput !== '' && $farmSize === null) {
        $errors['farm_size_acres'] = 'Enter a farm size greater than zero and no more than 100,000 acres.';
    }
    if ($errors !== []) {
        return [false, 'Please correct the highlighted farm details.', ['errors' => $errors, 'values' => ['farm_name' => $farmName, 'location_id' => $locationId, 'farm_size_acres' => $farmSizeInput, 'bio' => $bio]]];
    }
    $profile = role_business_profile($userId, 'farmer') ?? initialize_role_business_profile($userId, 'farmer');
    if ($profile === null) {
        return [false, 'Your farmer profile is no longer available.', []];
    }
    $changed = [];
    foreach (['farm_name' => $farmName, 'location_id' => $locationId, 'farm_size_acres' => $farmSize, 'bio' => $bio] as $field => $value) {
        $current = $profile[$field === 'location_id' ? 'location_id' : $field] ?? null;
        if (role_profile_field_changed($field, $current, $value)) {
            $changed[] = $field;
        }
    }
    if ($changed === []) {
        return [true, 'Your farm details are already up to date.', ['changed' => []]];
    }
    execute_query('UPDATE farmer_profiles SET farm_name = :farm_name, farm_location_id = :location_id, farm_size_acres = :farm_size_acres, bio = :bio WHERE user_id = :user_id', ['farm_name' => $farmName, 'location_id' => $locationId, 'farm_size_acres' => $farmSize, 'bio' => $bio !== '' ? $bio : null, 'user_id' => $userId]);
    audit_log($userId, 'farmer_business_profile_updated', 'farmer_profiles', null, ['fields' => $changed]);
    return [true, 'Your farm details have been updated.', ['changed' => $changed]];
}

function update_buyer_business_profile(int $userId, array $input): array
{
    $businessName = normalize_text($input['business_name'] ?? '', 160);
    $businessType = normalize_text($input['business_type'] ?? '', 100);
    $locationId = filter_var($input['business_location_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $taxReference = normalize_text($input['tax_reference'] ?? '', 80);
    $bio = normalize_text($input['business_bio'] ?? '', 1000);
    $errors = [];
    if ((function_exists('mb_strlen') ? mb_strlen($businessName) : strlen($businessName)) < 3) {
        $errors['business_name'] = 'Enter a business name using at least 3 characters.';
    }
    if ((function_exists('mb_strlen') ? mb_strlen($businessType) : strlen($businessType)) < 3) {
        $errors['business_type'] = 'Enter a business type using at least 3 characters.';
    }
    if (!role_profile_location_exists($locationId)) {
        $errors['business_location_id'] = 'Choose a listed business location.';
    }
    if ($taxReference !== '' && preg_match('/^[A-Za-z0-9\-\/\s]{3,80}$/', $taxReference) !== 1) {
        $errors['tax_reference'] = 'Use 3–80 letters, numbers, spaces, hyphens, or slashes for the tax reference.';
    }
    if ($errors !== []) {
        return [false, 'Please correct the highlighted business details.', ['errors' => $errors, 'values' => ['business_name' => $businessName, 'business_type' => $businessType, 'location_id' => $locationId, 'tax_reference' => $taxReference, 'bio' => $bio]]];
    }
    $profile = role_business_profile($userId, 'buyer') ?? initialize_role_business_profile($userId, 'buyer');
    if ($profile === null) {
        return [false, 'Your buyer profile is no longer available.', []];
    }
    $changed = [];
    foreach (['business_name' => $businessName, 'business_type' => $businessType, 'location_id' => $locationId, 'tax_reference' => $taxReference, 'bio' => $bio] as $field => $value) {
        $current = $profile[$field === 'location_id' ? 'location_id' : $field] ?? null;
        if (role_profile_field_changed($field, $current, $value)) {
            $changed[] = $field;
        }
    }
    if ($changed === []) {
        return [true, 'Your business details are already up to date.', ['changed' => []]];
    }
    execute_query('UPDATE buyer_profiles SET business_name = :business_name, business_type = :business_type, location_id = :location_id, tax_reference = :tax_reference, bio = :bio WHERE user_id = :user_id', ['business_name' => $businessName, 'business_type' => $businessType, 'location_id' => $locationId, 'tax_reference' => $taxReference !== '' ? $taxReference : null, 'bio' => $bio !== '' ? $bio : null, 'user_id' => $userId]);
    audit_log($userId, 'buyer_business_profile_updated', 'buyer_profiles', null, ['fields' => $changed]);
    return [true, 'Your business details have been updated.', ['changed' => $changed]];
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
    if (!local_password_recovery_is_available() || !recovery_verification_notes_are_available()) {
        throw new RuntimeException('Password recovery is not ready. Import database/migrations/20260826_add_local_password_recovery.sql, then refresh this page.');
    }
    $request = fetch_one(
        'SELECT r.id, r.user_id, r.verification_notes, r.verified_at FROM local_password_recovery_requests r JOIN users u ON u.id = r.user_id WHERE r.id = :id AND u.status = "active" LIMIT 1',
        ['id' => $requestId]
    );
    if ($request === null) {
        throw new RuntimeException('That recovery request is no longer available.');
    }
    if (trim((string) $request['verification_notes']) === '' || $request['verified_at'] === null) {
        throw new RuntimeException('Record how the requester’s identity was verified before issuing a reset link.');
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
        require dirname(__DIR__) . '/403.php';
        exit;
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

function notification_preferences_are_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'user_notification_preferences']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function notification_preferences_for_user(int $userId): array
{
    $defaults = ['marketplace_match_alerts_enabled' => 1, 'browser_chime_enabled' => 0];
    if ($userId < 1 || !notification_preferences_are_available()) {
        return $defaults;
    }
    $row = fetch_one('SELECT marketplace_match_alerts_enabled, browser_chime_enabled FROM user_notification_preferences WHERE user_id = :user_id LIMIT 1', ['user_id' => $userId]);
    return $row === null ? $defaults : [
        'marketplace_match_alerts_enabled' => (int) $row['marketplace_match_alerts_enabled'],
        'browser_chime_enabled' => (int) $row['browser_chime_enabled'],
    ];
}

function save_notification_preferences(int $userId, array $input): array
{
    if ($userId < 1 || !notification_preferences_are_available()) {
        throw new RuntimeException('Notification preferences are not ready. Import database/migrations/20260826_add_user_notification_preferences.sql, then refresh this page.');
    }
    $marketplaceEnabled = !empty($input['marketplace_match_alerts_enabled']) ? 1 : 0;
    $browserChimeEnabled = !empty($input['browser_chime_enabled']) ? 1 : 0;
    $statement = db()->prepare('INSERT INTO user_notification_preferences (user_id, marketplace_match_alerts_enabled, browser_chime_enabled) VALUES (:user_id, :marketplace_enabled, :browser_enabled) ON DUPLICATE KEY UPDATE marketplace_match_alerts_enabled = VALUES(marketplace_match_alerts_enabled), browser_chime_enabled = VALUES(browser_chime_enabled), updated_at = NOW()');
    $statement->execute(['user_id' => $userId, 'marketplace_enabled' => $marketplaceEnabled, 'browser_enabled' => $browserChimeEnabled]);
    audit_log($userId, 'notification_preferences_saved', 'user_notification_preferences', $userId, ['marketplace_match_alerts_enabled' => $marketplaceEnabled, 'browser_chime_enabled' => $browserChimeEnabled]);
    return ['marketplace_match_alerts_enabled' => $marketplaceEnabled, 'browser_chime_enabled' => $browserChimeEnabled];
}

function notification_delivery_enabled(int $userId, string $type): bool
{
    if ($type !== 'marketplace_filter_match') {
        return true;
    }
    return notification_preferences_for_user($userId)['marketplace_match_alerts_enabled'] === 1;
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
        'QAH' => 'support_requests',
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
