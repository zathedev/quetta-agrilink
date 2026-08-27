<?php
/** Local operator transition helpers: administrators create accountable accounts and archive only known development credentials without recording passwords. */
declare(strict_types=1);

function operator_account_transitions_are_available(): bool
{
    try {
        $row = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'operator_account_transitions']);
        return (int) ($row['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function development_account_match(array $account): bool
{
    $email = strtolower((string) ($account['email'] ?? ''));
    $name = (string) ($account['full_name'] ?? '');
    return str_ends_with($email, '.demo@quettaagrilink.test') && str_starts_with($name, 'Demo ');
}

function operator_transition_roles(): array
{
    return fetch_all('SELECT id, slug, name FROM roles WHERE is_active = 1 ORDER BY id');
}

function create_local_operator_account(int $administratorId, array $input): int
{
    if (!operator_account_transitions_are_available()) {
        throw new RuntimeException('Operator transitions are not ready. Import database/migrations/20260827_add_operator_account_transitions.sql, then refresh this page.');
    }

    $fullName = normalize_text($input['full_name'] ?? '', 120);
    $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = normalize_text($input['phone'] ?? '', 30);
    $roleSlug = normalize_text($input['role'] ?? '', 40);
    $password = (string) ($input['password'] ?? '');

    if ((function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName)) < 3) {
        throw new RuntimeException('Enter the operator’s full name using at least 3 characters.');
    }
    if ($email === false) {
        throw new RuntimeException('Enter a valid operator email address.');
    }
    if (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        throw new RuntimeException('Enter a valid operator contact number.');
    }
    if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new RuntimeException('Use a temporary password of at least 10 characters, including a letter and a number.');
    }

    $role = fetch_one('SELECT id, slug, name FROM roles WHERE slug = :role_slug AND is_active = 1 LIMIT 1', ['role_slug' => $roleSlug]);
    if ($role === null) {
        throw new RuntimeException('Choose an active operator role.');
    }
    $existing = fetch_one('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1', ['email' => $email, 'phone' => $phone]);
    if ($existing !== null) {
        throw new RuntimeException('An account already uses that email address or contact number.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $createUser = $pdo->prepare('INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (:role_id, :full_name, :email, :phone, :password_hash, "active")');
        $createUser->execute([
            'role_id' => $role['id'],
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $profileTable = match ($role['slug']) {
            'farmer' => 'farmer_profiles',
            'buyer' => 'buyer_profiles',
            'storage_provider' => 'storage_providers',
            'transport_provider' => 'transport_providers',
            default => null,
        };
        if ($profileTable !== null) {
            $pdo->prepare("INSERT INTO {$profileTable} (user_id) VALUES (:user_id)")->execute(['user_id' => $userId]);
        }

        $transition = $pdo->prepare('INSERT INTO operator_account_transitions (administrator_id, created_user_id, action, details) VALUES (:administrator_id, :created_user_id, "operator_created", :details)');
        $transition->execute([
            'administrator_id' => $administratorId,
            'created_user_id' => $userId,
            'details' => json_encode(['role' => $role['slug'], 'email' => $email], JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Local operator account creation failed: ' . $exception->getMessage());
        throw new RuntimeException('The operator account could not be created. Check the details and try again.');
    }

    audit_log($administratorId, 'local_operator_account_created', 'users', $userId, ['role' => $role['slug'], 'email' => $email]);
    return $userId;
}

function archive_development_account(int $administratorId, int $targetUserId): void
{
    if (!operator_account_transitions_are_available()) {
        throw new RuntimeException('Operator transitions are not ready. Import database/migrations/20260827_add_operator_account_transitions.sql, then refresh this page.');
    }
    if ($targetUserId === $administratorId) {
        throw new RuntimeException('Sign in with a separate named administrator before archiving this development account.');
    }

    $target = fetch_one('SELECT u.id, u.full_name, u.email, u.status, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :target_user_id LIMIT 1', ['target_user_id' => $targetUserId]);
    if ($target === null || !development_account_match($target)) {
        throw new RuntimeException('Only the documented development accounts can be archived through this transition register.');
    }
    if ($target['status'] === 'archived') {
        throw new RuntimeException('That development account is already archived.');
    }
    if ($target['role_slug'] === 'admin') {
        $administrators = fetch_one('SELECT COUNT(*) AS count FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = "admin" AND u.status = "active"');
        if ((int) ($administrators['count'] ?? 0) < 2) {
            throw new RuntimeException('Create and verify a separate named administrator before archiving the final active development administrator.');
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $archive = $pdo->prepare('UPDATE users SET status = "archived" WHERE id = :target_user_id AND status <> "archived"');
        $archive->execute(['target_user_id' => $targetUserId]);
        if ($archive->rowCount() !== 1) {
            throw new RuntimeException('That development account could not be archived.');
        }
        $transition = $pdo->prepare('INSERT INTO operator_account_transitions (administrator_id, archived_user_id, action, details) VALUES (:administrator_id, :archived_user_id, "development_account_archived", :details)');
        $transition->execute([
            'administrator_id' => $administratorId,
            'archived_user_id' => $targetUserId,
            'details' => json_encode(['role' => $target['role_slug'], 'email' => $target['email']], JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof RuntimeException) {
            throw $exception;
        }
        error_log('Development account archival failed: ' . $exception->getMessage());
        throw new RuntimeException('The development account could not be archived.');
    }

    audit_log($administratorId, 'development_account_archived', 'users', $targetUserId, ['role' => $target['role_slug'], 'email' => $target['email']]);
}
