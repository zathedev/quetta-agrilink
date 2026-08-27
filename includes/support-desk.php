<?php
/** Orchard Ledger support desk: local, account-scoped requests route to the responsible operational role and use only in-app records and notifications. */
declare(strict_types=1);

function support_desk_is_available(): bool
{
    try {
        $table = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'support_requests']);
        $messages = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'support_request_messages']);
        $events = fetch_one('SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name', ['table_name' => 'support_request_events']);
        return (int) ($table['count'] ?? 0) === 1 && (int) ($messages['count'] ?? 0) === 1 && (int) ($events['count'] ?? 0) === 1;
    } catch (Throwable $exception) {
        return false;
    }
}

function support_desk_categories(): array
{
    return [
        'account_access' => ['label' => 'Account access', 'route' => 'admin'],
        'marketplace' => ['label' => 'Marketplace record', 'route' => 'admin'],
        'storage' => ['label' => 'Cold storage', 'route' => 'storage_provider'],
        'transport' => ['label' => 'Transport', 'route' => 'transport_provider'],
        'local_operator' => ['label' => 'Local operator or account administration', 'route' => 'admin'],
        'other' => ['label' => 'Other platform support', 'route' => 'admin'],
    ];
}

function support_desk_statuses(): array
{
    return [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_on_requester' => 'Waiting on requester',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];
}

function support_desk_route_label(string $role): string
{
    return match ($role) {
        'storage_provider' => 'Cold storage provider desk',
        'transport_provider' => 'Transport provider desk',
        default => 'Administrator desk',
    };
}

function support_desk_safe_text(mixed $value, int $maxLength, string $field): string
{
    $text = normalize_text(is_string($value) ? $value : '', $maxLength);
    if ($text === '') {
        throw new RuntimeException($field . ' is required.');
    }
    if (preg_match('/\b(password|passcode|reset\s*link|recovery\s*code|verification\s*code|token|secret|one[- ]time\s*link)\b/i', $text)) {
        throw new RuntimeException('Do not place passwords, reset links, recovery codes, tokens, or other account secrets in a support request. Use the local recovery workflow for account access.');
    }
    return $text;
}

function support_desk_request_by_id(int $requestId): ?array
{
    if ($requestId < 1 || !support_desk_is_available()) {
        return null;
    }
    return fetch_one(
        'SELECT s.*, requester.full_name AS requester_name, requester.role_id AS requester_role_id, requester_role.name AS requester_role_name, assignee.full_name AS assignee_name FROM support_requests s JOIN users requester ON requester.id = s.requester_user_id JOIN roles requester_role ON requester_role.id = requester.role_id LEFT JOIN users assignee ON assignee.id = s.assigned_to_user_id WHERE s.id = :request_id LIMIT 1',
        ['request_id' => $requestId]
    );
}

function support_desk_can_view_request(array $user, array $request): bool
{
    if ((int) $request['requester_user_id'] === (int) $user['id'] || $user['role_slug'] === 'admin') {
        return true;
    }
    if ((int) ($request['assigned_to_user_id'] ?? 0) === (int) $user['id']) {
        return true;
    }
    return $request['assigned_to_user_id'] === null && $request['routed_role'] === $user['role_slug'];
}

function support_desk_can_manage_request(array $user, array $request): bool
{
    return $user['role_slug'] === 'admin' || (int) ($request['assigned_to_user_id'] ?? 0) === (int) $user['id'];
}

function support_desk_visible_requests(array $user, string $status = ''): array
{
    if (!support_desk_is_available()) {
        return [];
    }
    $conditions = ['s.requester_user_id = :requester_user_id'];
    $params = ['requester_user_id' => (int) $user['id']];
    if ($user['role_slug'] === 'admin') {
        $conditions[] = '1 = 1';
    } else {
        $conditions[] = '(s.routed_role = :routed_role AND s.assigned_to_user_id IS NULL)';
        $conditions[] = 's.assigned_to_user_id = :assigned_to_user_id';
        $params['routed_role'] = $user['role_slug'];
        $params['assigned_to_user_id'] = (int) $user['id'];
    }
    $statuses = support_desk_statuses();
    if ($status !== '' && isset($statuses[$status])) {
        $conditions[] = 's.status = :status';
        $params['status'] = $status;
    }
    return fetch_all(
        'SELECT s.*, requester.full_name AS requester_name, requester_role.name AS requester_role_name, assignee.full_name AS assignee_name FROM support_requests s JOIN users requester ON requester.id = s.requester_user_id JOIN roles requester_role ON requester_role.id = requester.role_id LEFT JOIN users assignee ON assignee.id = s.assigned_to_user_id WHERE (' . implode(' OR ', $conditions) . ') ORDER BY FIELD(s.status, "open", "in_progress", "waiting_on_requester", "resolved", "closed"), s.updated_at DESC, s.id DESC LIMIT 60',
        $params
    );
}

function support_desk_request_messages(int $requestId): array
{
    return fetch_all('SELECT m.*, u.full_name AS author_name, r.name AS author_role_name FROM support_request_messages m JOIN users u ON u.id = m.author_user_id JOIN roles r ON r.id = u.role_id WHERE m.support_request_id = :request_id ORDER BY m.created_at ASC, m.id ASC', ['request_id' => $requestId]);
}

function support_desk_request_events(int $requestId): array
{
    return fetch_all('SELECT e.*, u.full_name AS actor_name FROM support_request_events e JOIN users u ON u.id = e.actor_user_id WHERE e.support_request_id = :request_id ORDER BY e.created_at ASC, e.id ASC', ['request_id' => $requestId]);
}

function support_desk_assignees(string $routedRole): array
{
    return fetch_all('SELECT u.id, u.full_name, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.slug = :role_slug ORDER BY u.full_name ASC, u.id ASC', ['role_slug' => $routedRole]);
}

function support_desk_record_event(PDO $pdo, int $requestId, int $actorId, string $eventType, ?string $fromStatus = null, ?string $toStatus = null, ?string $note = null): void
{
    $pdo->prepare('INSERT INTO support_request_events (support_request_id, actor_user_id, event_type, from_status, to_status, note) VALUES (:request_id, :actor_id, :event_type, :from_status, :to_status, :note)')
        ->execute(['request_id' => $requestId, 'actor_id' => $actorId, 'event_type' => $eventType, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'note' => $note]);
}

function support_desk_notify_users(array $userIds, string $title, string $body, int $requestId): void
{
    foreach (array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)) as $userId) {
        try {
            create_notification($userId, 'support_request', $title, $body, 'support.php?request=' . $requestId, 'support_request', $requestId);
        } catch (Throwable $exception) {
            error_log('Local support notification failed: ' . $exception->getMessage());
        }
    }
}

function support_desk_handler_ids(array $request): array
{
    if ((int) ($request['assigned_to_user_id'] ?? 0) > 0) {
        return [(int) $request['assigned_to_user_id']];
    }
    return array_map('intval', array_column(fetch_all('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.status = "active" AND r.slug = :role_slug', ['role_slug' => $request['routed_role']]), 'id'));
}

function support_desk_create_request(array $user, array $input): int
{
    if (!support_desk_is_available()) {
        throw new RuntimeException('The local support register is not ready. Import database/migrations/20260827_add_in_app_support_desk.sql, then refresh this page.');
    }
    $categories = support_desk_categories();
    $category = (string) ($input['category'] ?? '');
    if (!isset($categories[$category])) {
        throw new RuntimeException('Choose a local support category.');
    }
    $subject = support_desk_safe_text($input['subject'] ?? '', 160, 'Subject');
    $message = support_desk_safe_text($input['message'] ?? '', 2000, 'Message');
    $route = $categories[$category]['route'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $reference = generate_reference('QAH');
        $statement = $pdo->prepare('INSERT INTO support_requests (reference_code, requester_user_id, category, routed_role, subject, status) VALUES (:reference_code, :requester_user_id, :category, :routed_role, :subject, "open")');
        $statement->execute(['reference_code' => $reference, 'requester_user_id' => (int) $user['id'], 'category' => $category, 'routed_role' => $route, 'subject' => $subject]);
        $requestId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO support_request_messages (support_request_id, author_user_id, body) VALUES (:request_id, :author_user_id, :body)')->execute(['request_id' => $requestId, 'author_user_id' => (int) $user['id'], 'body' => $message]);
        support_desk_record_event($pdo, $requestId, (int) $user['id'], 'created', null, 'open', 'Request routed to ' . support_desk_route_label($route) . '.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Local support request creation failed: ' . $exception->getMessage());
        throw new RuntimeException('The local support request could not be recorded. No message was sent outside this application.');
    }
    audit_log((int) $user['id'], 'support_request_created', 'support_requests', $requestId, ['category' => $category, 'routed_role' => $route]);
    $request = support_desk_request_by_id($requestId);
    if ($request !== null) {
        support_desk_notify_users(support_desk_handler_ids($request), 'New local support request', $reference . ' needs attention in the ' . support_desk_route_label($route) . '.', $requestId);
    }
    return $requestId;
}

function support_desk_claim_or_assign_request(array $user, int $requestId, int $requestedAssigneeId = 0): void
{
    $request = support_desk_request_by_id($requestId);
    if ($request === null || !support_desk_can_view_request($user, $request)) {
        throw new RuntimeException('That support request is not available to this account.');
    }
    if ($user['role_slug'] === 'admin') {
        $assigneeId = $requestedAssigneeId;
    } else {
        if ($request['routed_role'] !== $user['role_slug']) {
            throw new RuntimeException('Only the routed role can claim this support request.');
        }
        $assigneeId = (int) $user['id'];
    }
    $assignee = fetch_one('SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :user_id AND u.status = "active" AND r.slug = :role_slug LIMIT 1', ['user_id' => $assigneeId, 'role_slug' => $request['routed_role']]);
    if ($assignee === null) {
        throw new RuntimeException('Choose an active account in the routed support role.');
    }
    $previousAssignee = (int) ($request['assigned_to_user_id'] ?? 0);
    if ($previousAssignee > 0 && $user['role_slug'] !== 'admin') {
        throw new RuntimeException('This support request has already been claimed by its accountable handler.');
    }
    $nextStatus = $request['status'] === 'open' ? 'in_progress' : $request['status'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $assignment = $pdo->prepare('UPDATE support_requests SET assigned_to_user_id = :assignee_id, status = :status, updated_at = NOW() WHERE id = :request_id' . ($user['role_slug'] === 'admin' ? '' : ' AND assigned_to_user_id IS NULL'));
        $assignment->execute(['assignee_id' => $assigneeId, 'status' => $nextStatus, 'request_id' => $requestId]);
        if ($assignment->rowCount() !== 1) {
            throw new RuntimeException('This support request has already been claimed. Refresh the register before taking another action.');
        }
        support_desk_record_event($pdo, $requestId, (int) $user['id'], 'assigned', $request['status'], $nextStatus, $previousAssignee > 0 ? 'Accountable handler reassigned.' : 'Accountable handler assigned.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception instanceof RuntimeException ? $exception : new RuntimeException('The support assignment could not be recorded.');
    }
    audit_log((int) $user['id'], 'support_request_assigned', 'support_requests', $requestId, ['routed_role' => $request['routed_role'], 'assigned_to_user_id' => $assigneeId]);
    support_desk_notify_users([(int) $request['requester_user_id']], 'Support request assigned', $request['reference_code'] . ' now has an accountable local handler.', $requestId);
    if ($previousAssignee > 0 && $previousAssignee !== $assigneeId) {
        support_desk_notify_users([$previousAssignee], 'Support assignment updated', $request['reference_code'] . ' has been reassigned in the local support register.', $requestId);
    }
}

function support_desk_add_message(array $user, int $requestId, array $input): void
{
    $request = support_desk_request_by_id($requestId);
    if ($request === null || !support_desk_can_view_request($user, $request)) {
        throw new RuntimeException('That support request is not available to this account.');
    }
    if ($request['status'] === 'closed') {
        throw new RuntimeException('This support request is closed and cannot receive another message. Open a new local request if further help is needed.');
    }
    $isRequester = (int) $request['requester_user_id'] === (int) $user['id'];
    if (!$isRequester && !support_desk_can_manage_request($user, $request)) {
        throw new RuntimeException('Only the requester or accountable support handler may add a message.');
    }
    $message = support_desk_safe_text($input['message'] ?? '', 2000, 'Message');
    $nextStatus = $isRequester ? 'in_progress' : 'waiting_on_requester';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO support_request_messages (support_request_id, author_user_id, body) VALUES (:request_id, :author_user_id, :body)')->execute(['request_id' => $requestId, 'author_user_id' => (int) $user['id'], 'body' => $message]);
        $pdo->prepare('UPDATE support_requests SET status = :status, updated_at = NOW() WHERE id = :request_id')->execute(['status' => $nextStatus, 'request_id' => $requestId]);
        support_desk_record_event($pdo, $requestId, (int) $user['id'], 'message_added', $request['status'], $nextStatus, $isRequester ? 'Requester added a follow-up.' : 'Accountable handler added a response.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('The in-app support message could not be recorded.');
    }
    audit_log((int) $user['id'], 'support_request_message_added', 'support_requests', $requestId, ['actor_is_requester' => $isRequester, 'status' => $nextStatus]);
    if ($isRequester) {
        support_desk_notify_users(support_desk_handler_ids($request), 'Support request updated', $request['reference_code'] . ' has a requester follow-up in the local support register.', $requestId);
    } else {
        support_desk_notify_users([(int) $request['requester_user_id']], 'Support response available', $request['reference_code'] . ' has a response in your local support register.', $requestId);
    }
}

function support_desk_change_status(array $user, int $requestId, string $status): void
{
    $statuses = support_desk_statuses();
    $request = support_desk_request_by_id($requestId);
    if ($request === null || !support_desk_can_manage_request($user, $request)) {
        throw new RuntimeException('Only the accountable support handler or an administrator may update this request.');
    }
    if (!isset($statuses[$status])) {
        throw new RuntimeException('Choose a valid support status.');
    }
    if ($status === $request['status']) {
        throw new RuntimeException('That support status is already recorded.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE support_requests SET status = :status, closed_at = :closed_at, updated_at = NOW() WHERE id = :request_id')->execute(['status' => $status, 'closed_at' => $status === 'closed' ? date('Y-m-d H:i:s') : null, 'request_id' => $requestId]);
        support_desk_record_event($pdo, $requestId, (int) $user['id'], 'status_changed', $request['status'], $status, 'Status changed to ' . $statuses[$status] . '.');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('The support status could not be recorded.');
    }
    audit_log((int) $user['id'], 'support_request_status_changed', 'support_requests', $requestId, ['from_status' => $request['status'], 'to_status' => $status]);
    support_desk_notify_users([(int) $request['requester_user_id']], 'Support request status updated', $request['reference_code'] . ' is now ' . strtolower($statuses[$status]) . '.', $requestId);
}

function support_desk_dashboard_attention(array $user): array
{
    if (!support_desk_is_available()) {
        return ['available' => false, 'requester_open' => 0, 'queue_open' => 0];
    }
    $requester = fetch_one('SELECT COUNT(*) AS count FROM support_requests WHERE requester_user_id = :user_id AND status <> "closed"', ['user_id' => (int) $user['id']]);
    if ($user['role_slug'] === 'admin') {
        $queue = fetch_one('SELECT COUNT(*) AS count FROM support_requests WHERE status IN ("open", "in_progress", "waiting_on_requester")', []);
    } else {
        $queue = fetch_one('SELECT COUNT(*) AS count FROM support_requests WHERE routed_role = :role_slug AND assigned_to_user_id IS NULL AND status IN ("open", "in_progress")', ['role_slug' => $user['role_slug']]);
    }
    return ['available' => true, 'requester_open' => (int) ($requester['count'] ?? 0), 'queue_open' => (int) ($queue['count'] ?? 0)];
}
