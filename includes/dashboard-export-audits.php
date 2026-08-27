<?php
/** Quetta Workbench export-audit data: minimal administrator accountability records with safe local filters, ordering, and paging. */
declare(strict_types=1);

function dashboard_summary_export_audit_roles(): array
{
    return [
        'farmer' => 'Farmer',
        'buyer' => 'Buyer',
        'storage_provider' => 'Cold storage provider',
        'transport_provider' => 'Transport provider',
        'admin' => 'Administrator',
    ];
}

function dashboard_summary_export_audit_sorts(): array
{
    return [
        'exported_at_desc' => ['sql' => 'al.created_at DESC, al.id DESC', 'direction' => 'descending', 'next' => 'exported_at_asc'],
        'exported_at_asc' => ['sql' => 'al.created_at ASC, al.id ASC', 'direction' => 'ascending', 'next' => 'exported_at_desc'],
        'account_asc' => ['sql' => 'COALESCE(u.full_name, "Unavailable account") ASC, al.id ASC', 'direction' => 'ascending', 'next' => 'account_desc'],
        'account_desc' => ['sql' => 'COALESCE(u.full_name, "Unavailable account") DESC, al.id DESC', 'direction' => 'descending', 'next' => 'account_asc'],
        'role_asc' => ['sql' => 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.role")) ASC, al.id ASC', 'direction' => 'ascending', 'next' => 'role_desc'],
        'role_desc' => ['sql' => 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.role")) DESC, al.id DESC', 'direction' => 'descending', 'next' => 'role_asc'],
        'summary_start_asc' => ['sql' => 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_start")) ASC, al.id ASC', 'direction' => 'ascending', 'next' => 'summary_start_desc'],
        'summary_start_desc' => ['sql' => 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_start")) DESC, al.id DESC', 'direction' => 'descending', 'next' => 'summary_start_asc'],
    ];
}

function dashboard_summary_export_audit_filters(array $query): array
{
    $parseDate = static function (mixed $value): ?DateTimeImmutable {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    };
    $role = normalize_text($query['role'] ?? '', 40);
    if (!array_key_exists($role, dashboard_summary_export_audit_roles())) {
        $role = '';
    }
    $exportedFrom = $parseDate($query['exported_from'] ?? null);
    $exportedTo = $parseDate($query['exported_to'] ?? null);
    if ($exportedFrom !== null && $exportedTo !== null && $exportedFrom > $exportedTo) {
        [$exportedFrom, $exportedTo] = [$exportedTo, $exportedFrom];
    }
    $summaryFrom = $parseDate($query['summary_from'] ?? null);
    $summaryTo = $parseDate($query['summary_to'] ?? null);
    if ($summaryFrom !== null && $summaryTo !== null && $summaryFrom > $summaryTo) {
        [$summaryFrom, $summaryTo] = [$summaryTo, $summaryFrom];
    }
    $sort = normalize_text($query['sort'] ?? 'exported_at_desc', 40);
    if (!array_key_exists($sort, dashboard_summary_export_audit_sorts())) {
        $sort = 'exported_at_desc';
    }
    $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 500]]);
    return [
        'account' => normalize_text($query['account'] ?? '', 80),
        'role' => $role,
        'exported_from' => $exportedFrom,
        'exported_to' => $exportedTo,
        'summary_from' => $summaryFrom,
        'summary_to' => $summaryTo,
        'sort' => $sort,
        'page' => (int) $page,
    ];
}

function dashboard_summary_export_audit_query(array $filters, array $overrides = []): string
{
    $filters = array_replace($filters, $overrides);
    return http_build_query(array_filter([
        'account' => ($filters['account'] ?? '') !== '' ? $filters['account'] : null,
        'role' => ($filters['role'] ?? '') !== '' ? $filters['role'] : null,
        'exported_from' => ($filters['exported_from'] ?? null)?->format('Y-m-d'),
        'exported_to' => ($filters['exported_to'] ?? null)?->format('Y-m-d'),
        'summary_from' => ($filters['summary_from'] ?? null)?->format('Y-m-d'),
        'summary_to' => ($filters['summary_to'] ?? null)?->format('Y-m-d'),
        'sort' => ($filters['sort'] ?? 'exported_at_desc') !== 'exported_at_desc' ? $filters['sort'] : null,
        'page' => (int) ($filters['page'] ?? 1) > 1 ? (int) $filters['page'] : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
}

function dashboard_summary_export_audit_sql_parts(array $filters): array
{
    $conditions = ['al.action = :audit_action', 'al.entity_type = :entity_type'];
    $params = ['audit_action' => 'dashboard_summary_exported', 'entity_type' => 'dashboard_summary'];
    if (($filters['account'] ?? '') !== '') {
        $conditions[] = 'u.full_name LIKE :account_name';
        $params['account_name'] = '%' . $filters['account'] . '%';
    }
    if (($filters['role'] ?? '') !== '') {
        $conditions[] = 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.role")) = :role';
        $params['role'] = $filters['role'];
    }
    if (($filters['exported_from'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'al.created_at >= :exported_from';
        $params['exported_from'] = $filters['exported_from']->format('Y-m-d 00:00:00');
    }
    if (($filters['exported_to'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'al.created_at < :exported_to';
        $params['exported_to'] = $filters['exported_to']->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    if (($filters['summary_from'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_start")) >= :summary_from';
        $params['summary_from'] = $filters['summary_from']->format('Y-m-d');
    }
    if (($filters['summary_to'] ?? null) instanceof DateTimeImmutable) {
        $conditions[] = 'JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_end")) <= :summary_to';
        $params['summary_to'] = $filters['summary_to']->format('Y-m-d');
    }
    return [$conditions, $params];
}

function dashboard_summary_export_audit_count(array $filters): int
{
    [$conditions, $params] = dashboard_summary_export_audit_sql_parts($filters);
    $row = fetch_one('SELECT COUNT(*) AS count FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id WHERE ' . implode(' AND ', $conditions), $params);
    return (int) ($row['count'] ?? 0);
}

function dashboard_summary_export_audit_rows(array $filters, int $limit = 25, int $offset = 0): array
{
    [$conditions, $params] = dashboard_summary_export_audit_sql_parts($filters);
    $sorts = dashboard_summary_export_audit_sorts();
    $sort = $sorts[$filters['sort'] ?? 'exported_at_desc'] ?? $sorts['exported_at_desc'];
    $limit = max(1, min(5000, $limit));
    $offset = max(0, min(10000, $offset));
    $sql = 'SELECT al.id, al.actor_user_id, al.created_at, COALESCE(u.full_name, "Unavailable account") AS actor_name, JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.role")) AS exported_role, JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_start")) AS period_start, JSON_UNQUOTE(JSON_EXTRACT(al.metadata, "$.period_end")) AS period_end FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY ' . $sort['sql'] . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    return fetch_all($sql, $params);
}
