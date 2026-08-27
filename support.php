<?php
/** Orchard Ledger support register: authenticated requests are recorded and routed to accountable local dashboards without email, SMS, SMTP, or external helpdesk delivery. */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/support-desk.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_login();
$supportReady = support_desk_is_available();
$statuses = support_desk_statuses();
$selectedStatus = (string) ($_GET['status'] ?? '');
if (!isset($statuses[$selectedStatus])) {
    $selectedStatus = '';
}
$requestId = filter_var($_GET['request'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$returnQuery = static function (int $id = 0, string $status = ''): string {
    $parameters = [];
    if ($id > 0) {
        $parameters['request'] = $id;
    }
    if ($status !== '') {
        $parameters['status'] = $status;
    }
    $query = http_build_query($parameters);
    return 'support.php' . ($query === '' ? '' : '?' . $query);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (!$supportReady) {
            throw new RuntimeException('The local support register is not ready. Import database/migrations/20260827_add_in_app_support_desk.sql, then refresh this page.');
        }
        $action = (string) ($_POST['support_action'] ?? '');
        if ($action === 'create') {
            $createdId = support_desk_create_request($user, $_POST);
            flash('success', 'Your support request was recorded locally and routed to the accountable dashboard. No email or external helpdesk message was sent.');
            redirect($returnQuery($createdId));
        }
        $actionRequestId = filter_var($_POST['support_request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        if ($action === 'claim') {
            support_desk_claim_or_assign_request($user, $actionRequestId);
            flash('success', 'You are now the accountable local handler for this support request.');
        } elseif ($action === 'assign') {
            $assigneeId = filter_var($_POST['assigned_to_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            support_desk_claim_or_assign_request($user, $actionRequestId, $assigneeId);
            flash('success', 'The local support request assignment was recorded.');
        } elseif ($action === 'reply') {
            support_desk_add_message($user, $actionRequestId, $_POST);
            flash('success', 'Your in-app support message was recorded.');
        } elseif ($action === 'status') {
            support_desk_change_status($user, $actionRequestId, (string) ($_POST['status'] ?? ''));
            flash('success', 'The support request status was recorded.');
        } else {
            throw new RuntimeException('Choose a valid local support action.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The local support action could not be completed.');
    }
    redirect($returnQuery((int) ($_POST['support_request_id'] ?? 0), $selectedStatus));
}

$request = $requestId > 0 ? support_desk_request_by_id($requestId) : null;
if ($request !== null && !support_desk_can_view_request($user, $request)) {
    http_response_code(403);
    exit('You are not authorized to access this support request.');
}
$messages = $request === null ? [] : support_desk_request_messages((int) $request['id']);
$events = $request === null ? [] : support_desk_request_events((int) $request['id']);
$assignees = $request === null || $user['role_slug'] !== 'admin' ? [] : support_desk_assignees($request['routed_role']);
$requests = support_desk_visible_requests($user, $selectedStatus);
$pageTitle = 'In-app support';
workspace_open('In-app support register', 'support');
?>
<section class="workspace-section support-intro"><div class="workspace-section-header"><div><p class="desk-kicker">Local account support</p><h2>Keep support work in the accountable workspace.</h2><p>Requests route to an administrator, cold-storage, or transport dashboard by category. The register uses only local PHP/MySQL records and in-app alerts: no email, SMS, SMTP, or external helpdesk delivery is used.</p></div></div><div class="support-boundary"><strong>Private operational boundary</strong><span>Do not enter passwords, reset links, recovery codes, tokens, or other account secrets. Use the local recovery workflow for account access.</span></div></section>
<?php if (!$supportReady): ?>
<section class="workspace-section support-blocker"><h2>Support register is not ready.</h2><p>Import <code>database/migrations/20260827_add_in_app_support_desk.sql</code>, then refresh this page. No support request can be recorded until its local record, message, and history tables are available.</p></section>
<?php else: ?>
<?php if ($message = flash('success')): ?><div class="flash flash-success"><?= e($message) ?></div><?php endif; ?><?php if ($message = flash('error')): ?><div class="flash flash-error"><?= e($message) ?></div><?php endif; ?>
<section class="workspace-section support-create"><div class="workspace-section-header"><div><p class="desk-kicker">Open a request</p><h2>Describe the operational help you need.</h2><p>Your request stays visible to your account and the role-responsible local desk. The category selects the routing; a named handler claims or is assigned the case inside this register.</p></div></div><form class="form-grid support-create-form" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="support_action" value="create"><div class="form-field"><label for="support-category">Support category</label><select id="support-category" name="category" required><option value="">Choose a category</option><?php foreach (support_desk_categories() as $key => $category): ?><option value="<?= e($key) ?>"><?= e($category['label']) ?> → <?= e(support_desk_route_label($category['route'])) ?></option><?php endforeach; ?></select></div><div class="form-field"><label for="support-subject">Short subject</label><input id="support-subject" name="subject" maxlength="160" required placeholder="e.g. Need help with a transport request"></div><div class="form-field support-message-field"><label for="support-message">What needs attention?</label><textarea id="support-message" name="message" maxlength="2000" rows="5" required placeholder="Share the record context and practical question. Do not include account secrets."></textarea><span class="form-help">Requests are local and auditable. Credentials and recovery secrets are not accepted.</span></div><div class="form-actions"><button class="button button-primary" type="submit">Record local support request</button></div></form></section>
<?php if ($request !== null): $isRequester = (int) $request['requester_user_id'] === (int) $user['id']; $canManage = support_desk_can_manage_request($user, $request); ?>
<section class="workspace-section support-detail"><div class="support-detail-heading"><div><p class="desk-kicker">Support request <?= e($request['reference_code']) ?></p><h2><?= e($request['subject']) ?></h2><p>Requested by <?= e($request['requester_name']) ?> (<?= e($request['requester_role_name']) ?>). Routed to <?= e(support_desk_route_label($request['routed_role'])) ?>.</p></div><span class="status-pill support-status-<?= e($request['status']) ?>"><?= e($statuses[$request['status']]) ?></span></div><div class="support-meta"><span><strong>Accountable handler:</strong> <?= $request['assignee_name'] === null ? 'Not yet claimed' : e($request['assignee_name']) ?></span><span><strong>Opened:</strong> <?= e(date('j M Y, H:i', strtotime((string) $request['created_at']))) ?></span><span><strong>Updated:</strong> <?= e(date('j M Y, H:i', strtotime((string) $request['updated_at']))) ?></span></div>
<?php if ($request['assigned_to_user_id'] === null && $request['routed_role'] === $user['role_slug']): ?><form class="support-action-form" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="support_action" value="claim"><input type="hidden" name="support_request_id" value="<?= (int) $request['id'] ?>"><button class="button button-primary" type="submit">Claim this routed request</button></form><?php endif; ?>
<?php if ($user['role_slug'] === 'admin' && $assignees !== []): ?><form class="support-assign-form" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="support_action" value="assign"><input type="hidden" name="support_request_id" value="<?= (int) $request['id'] ?>"><label for="support-assignee">Assign to accountable <?= e(support_desk_route_label($request['routed_role'])) ?></label><select id="support-assignee" name="assigned_to_user_id" required><?php foreach ($assignees as $assignee): ?><option value="<?= (int) $assignee['id'] ?>" <?= (int) ($request['assigned_to_user_id'] ?? 0) === (int) $assignee['id'] ? 'selected' : '' ?>><?= e($assignee['full_name']) ?></option><?php endforeach; ?></select><button class="button button-quiet" type="submit">Save assignment</button></form><?php endif; ?>
<div class="support-thread"><h3>Local support conversation</h3><?php foreach ($messages as $entry): ?><article class="support-message <?= (int) $entry['author_user_id'] === (int) $user['id'] ? 'is-own' : '' ?>"><div><strong><?= e($entry['author_name']) ?></strong><span><?= e($entry['author_role_name']) ?></span></div><p><?= e($entry['body']) ?></p><time datetime="<?= e($entry['created_at']) ?>"><?= e(date('j M Y, H:i', strtotime((string) $entry['created_at']))) ?></time></article><?php endforeach; ?></div>
<?php if (($isRequester || $canManage) && $request['status'] !== 'closed'): ?><form class="support-reply-form" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="support_action" value="reply"><input type="hidden" name="support_request_id" value="<?= (int) $request['id'] ?>"><label for="support-reply">Add a local <?= $isRequester ? 'follow-up' : 'response' ?></label><textarea id="support-reply" name="message" maxlength="2000" rows="4" required placeholder="Do not include passwords, reset links, tokens, or recovery codes."></textarea><button class="button button-primary" type="submit">Record in-app message</button></form><?php endif; ?>
<?php if ($canManage): ?><form class="support-status-form" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="support_action" value="status"><input type="hidden" name="support_request_id" value="<?= (int) $request['id'] ?>"><label for="support-status">Record status</label><select id="support-status" name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $request['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="button button-quiet" type="submit">Save status</button></form><?php endif; ?>
<div class="support-history"><h3>Accountability history</h3><ul><?php foreach ($events as $event): ?><li><strong><?= e($event['actor_name']) ?></strong> — <?= e(ucwords(str_replace('_', ' ', $event['event_type']))) ?><?php if ($event['from_status'] !== null || $event['to_status'] !== null): ?>: <?= e($event['from_status'] ?? '—') ?> → <?= e($event['to_status'] ?? '—') ?><?php endif; ?><span><?= e($event['note'] ?? '') ?></span><time><?= e(date('j M Y, H:i', strtotime((string) $event['created_at']))) ?></time></li><?php endforeach; ?></ul></div></section>
<?php endif; ?>
<section class="workspace-section support-register"><div class="workspace-section-header"><div><p class="desk-kicker">Role-routed register</p><h2><?= $user['role_slug'] === 'admin' ? 'Platform support oversight' : 'Support requests visible to this account' ?></h2><p>Requesters see their own records. An accountable role sees unclaimed requests routed to that role and the cases it has claimed; administrators retain oversight across the local register.</p></div></div><form class="support-filter" method="get"><label for="support-filter-status">Status<select id="support-filter-status" name="status"><option value="">All visible statuses</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $selectedStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><button class="button button-quiet" type="submit">Apply filter</button><a href="<?= e(app_url('support.php')) ?>">Clear</a></form><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Request</th><th>Requester</th><th>Routed desk</th><th>Handler</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php if ($requests === []): ?><tr><td colspan="6">No local support requests are visible for this account and filter.</td></tr><?php else: foreach ($requests as $row): ?><tr><td><a class="text-action" href="<?= e(app_url($returnQuery((int) $row['id'], $selectedStatus))) ?>"><strong><?= e($row['reference_code']) ?></strong><br><?= e($row['subject']) ?></a></td><td><?= e($row['requester_name']) ?><br><span class="muted"><?= e($row['requester_role_name']) ?></span></td><td><?= e(support_desk_route_label($row['routed_role'])) ?></td><td><?= $row['assignee_name'] === null ? 'Unclaimed' : e($row['assignee_name']) ?></td><td><span class="status-pill support-status-<?= e($row['status']) ?>"><?= e($statuses[$row['status']]) ?></span></td><td><?= e(date('j M Y, H:i', strtotime((string) $row['updated_at']))) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php endif; ?>
<?php workspace_close(); ?>
