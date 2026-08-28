<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/workspace.php';
$user = require_role(['farmer', 'buyer', 'admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$orderId) { throw new RuntimeException('Choose a valid order.'); }
        update_order_status((int) $orderId, normalize_text($_POST['status'] ?? '', 30), $user, (string) ($_POST['note'] ?? ''));
        $message = 'Order status updated.';
        if (is_ajax_request()) { json_response(true, $message, ['redirect' => app_url('orders.php?id=' . $orderId)]); }
        flash('success', $message);
    } catch (Throwable $exception) {
        if (is_ajax_request()) { json_response(false, $exception->getMessage(), [], 422); }
        flash('error', $exception->getMessage());
    }
    redirect('orders.php');
}
$scope = $user['role_slug'] === 'admin' ? '1=1' : ($user['role_slug'] === 'buyer' ? 'o.buyer_id=:user' : 'o.farmer_id=:user');
$params = $user['role_slug'] === 'admin' ? [] : ['user' => (int) $user['id']];
$orders = fetch_all('SELECT o.*,buyer.full_name AS buyer_name,farmer.full_name AS farmer_name,GROUP_CONCAT(CONCAT(oi.produce_name," · ",FORMAT(oi.quantity,0)," ",oi.unit) SEPARATOR "; ") AS items FROM orders o JOIN users buyer ON buyer.id=o.buyer_id JOIN users farmer ON farmer.id=o.farmer_id LEFT JOIN order_items oi ON oi.order_id=o.id WHERE ' . $scope . ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100', $params);
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$history = $selectedId ? fetch_all('SELECT h.*,u.full_name FROM order_status_history h LEFT JOIN users u ON u.id=h.changed_by_user_id WHERE h.order_id=:order AND EXISTS(SELECT 1 FROM orders o WHERE o.id=h.order_id AND ' . $scope . ') ORDER BY h.created_at,h.id', array_merge(['order' => $selectedId], $params)) : [];
workspace_open('Orders and sales history', 'orders');
?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Account-scoped orders</h2><p>Every change is validated server-side and retained in the status history.</p></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Produce</th><th>Parties</th><th>Total</th><th>Status</th><th>Next action</th></tr></thead><tbody><?php if ($orders === []): ?><tr><td colspan="6">No orders have been created for this account yet.</td></tr><?php else: foreach ($orders as $order): $nextStatuses=order_allowed_transitions($order['status'],$user['role_slug']); ?><tr><td><a href="<?= e(app_url('orders.php?id='.(int)$order['id'])) ?>"><?= e($order['reference_code']) ?></a><br><span class="muted"><?= e(date('j M Y',strtotime($order['created_at']))) ?></span></td><td><?= e($order['items'] ?? 'Order items unavailable') ?></td><td><?= e($order['farmer_name']) ?> → <?= e($order['buyer_name']) ?></td><td>Rs. <?= number_format((float)$order['total_amount'],2) ?></td><td><span class="status-pill <?= e($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></td><td><?php if ($nextStatuses === []): ?><span class="muted">No action required</span><?php else: ?><form method="post" data-ajax-form action="<?= e(app_url('orders.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><select name="status" required><option value="">Choose next status</option><?php foreach($nextStatuses as $next): ?><option value="<?= e($next) ?>"><?= e(order_status_label($next)) ?></option><?php endforeach; ?></select><input name="note" maxlength="500" placeholder="Optional operational note"><button class="button button-quiet" type="submit">Update order</button><span data-form-feedback></span></form><?php endif; ?><div><a class="text-action" href="<?= e(app_url('messages.php?order_id='.(int)$order['id'])) ?>">Open messages</a><?php if($order['status']==='completed'): ?> · <a class="text-action" href="<?= e(app_url('reviews.php?order_id='.(int)$order['id'])) ?>">Review trade</a><?php endif; ?></div></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php if ($selectedId): ?><section class="workspace-section"><div class="workspace-section-header"><div><h2>Status history</h2><p>Recorded milestones for the selected order.</p></div></div><div class="timeline-list"><?php if($history===[]): ?><p class="muted">No history is available for that order.</p><?php else: foreach($history as $entry): ?><article><strong><?= e(order_status_label($entry['status'])) ?></strong><span><?= e($entry['notes'] ?: 'Status recorded.') ?></span><small><?= e($entry['full_name'] ?? 'System') ?> · <?= e(date('j M Y H:i',strtotime($entry['created_at']))) ?></small></article><?php endforeach; endif; ?></div></section><?php endif; ?>
<?php workspace_close(); ?>
