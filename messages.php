<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/workspace.php';

$user = require_role(['farmer', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedOrderId = (int) ($_POST['order_id'] ?? 0);
    $postedListingId = (int) ($_POST['listing_id'] ?? 0);
    $postedPeerId = (int) ($_POST['recipient_id'] ?? 0);
    $threadQuery = $postedOrderId > 0
        ? 'order_id=' . $postedOrderId
        : 'listing_id=' . $postedListingId . '&peer_id=' . $postedPeerId;

    try {
        $id = send_commercial_message(
            $user,
            $postedListingId,
            $postedOrderId,
            (string) ($_POST['body'] ?? ''),
            $postedPeerId
        );
        if (is_ajax_request()) {
            json_response(true, 'Message sent.', ['message_id' => $id]);
        }
        flash('success', 'Message sent.');
    } catch (Throwable $exception) {
        if (is_ajax_request()) {
            json_response(false, $exception->getMessage(), [], 422);
        }
        flash('error', $exception->getMessage());
    }
    redirect('messages.php?' . $threadQuery);
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$peerId = filter_input(INPUT_GET, 'peer_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$thread = [];
$context = null;

if ($orderId > 0 || $listingId > 0) {
    try {
        $context = commercial_context($listingId, $orderId, $user, $peerId);
        $peerId = (int) $context['recipient_id'];
        if ($orderId > 0) {
            $where = 'm.order_id=:thread_order';
            $params = ['thread_order' => $orderId];
            $readWhere = 'order_id=:read_order';
            $readParams = ['read_order' => $orderId];
        } else {
            $where = 'm.listing_id=:thread_listing AND ((m.sender_id=:thread_user_sender AND m.recipient_id=:thread_peer_recipient) OR (m.sender_id=:thread_peer_sender AND m.recipient_id=:thread_user_recipient))';
            $params = [
                'thread_listing' => $listingId,
                'thread_user_sender' => (int) $user['id'],
                'thread_peer_recipient' => $peerId,
                'thread_peer_sender' => $peerId,
                'thread_user_recipient' => (int) $user['id'],
            ];
            $readWhere = 'listing_id=:read_listing';
            $readParams = ['read_listing' => $listingId];
        }
        $thread = fetch_all(
            'SELECT m.*,u.full_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE ' . $where . ' ORDER BY m.created_at,m.id',
            $params
        );
        execute_query(
            'UPDATE messages SET read_at=NOW() WHERE ' . $readWhere . ' AND recipient_id=:read_user AND sender_id=:read_peer AND read_at IS NULL',
            $readParams + ['read_user' => (int) $user['id'], 'read_peer' => $peerId]
        );
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
}

$contexts = commercial_conversations($user);
$activeKey = $orderId > 0 ? 'order-' . $orderId : ($listingId > 0 ? 'listing-' . $listingId . '-peer-' . $peerId : '');
$latestMessageId = $thread === [] ? 0 : (int) $thread[array_key_last($thread)]['id'];

workspace_open('Commercial messages', 'messages');
?>
<section
    class="workspace-section message-layout"
    data-message-workspace
    data-sync-endpoint="<?= e(app_url('ajax/messages/sync.php')) ?>"
    data-current-user-id="<?= (int) $user['id'] ?>"
    data-order-id="<?= $orderId ?>"
    data-listing-id="<?= $listingId ?>"
    data-peer-id="<?= $peerId ?>"
    data-active-key="<?= e($activeKey) ?>"
    data-latest-message-id="<?= $latestMessageId ?>"
>
    <aside>
        <h2>Conversations</h2>
        <div class="message-context-list" data-conversation-list aria-live="polite">
            <?php if ($contexts === []): ?>
                <p class="muted" data-conversation-empty>No eligible listing or order conversations yet.</p>
            <?php else: ?>
                <?php foreach ($contexts as $item): ?>
                    <a class="message-context <?= $item['key'] === $activeKey ? 'is-active' : '' ?> <?= $item['unread_count'] > 0 ? 'is-unread' : '' ?>" href="<?= e($item['url']) ?>" data-context-key="<?= e($item['key']) ?>" data-activity-at="<?= e($item['activity_at']) ?>" data-unread-count="<?= $item['unread_count'] ?>">
                        <span class="message-context-label"><?= e($item['label']) ?></span>
                        <?php if ($item['unread_count'] > 0): ?>
                            <span class="message-unread" aria-label="<?= $item['unread_count'] ?> unread messages"><?= $item['unread_count'] > 99 ? '99+' : $item['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    <div>
        <div class="workspace-section-header">
            <div>
                <h2><?= e($context['label'] ?? 'Choose a conversation') ?></h2>
                <p>Simple in-app messages only; no external delivery or public contact details.</p>
            </div>
        </div>
        <?php if ($context): ?>
            <div class="message-thread" data-message-thread aria-live="polite">
                <?php if ($thread === []): ?>
                    <p class="muted" data-message-empty>No messages in this conversation yet.</p>
                <?php else: ?>
                    <?php foreach ($thread as $message): ?>
                        <article class="support-message <?= (int) $message['sender_id'] === (int) $user['id'] ? 'is-own' : '' ?>" data-message-id="<?= (int) $message['id'] ?>">
                            <strong><?= e($message['full_name']) ?></strong>
                            <p><?= e($message['body']) ?></p>
                            <time datetime="<?= e($message['created_at']) ?>"><?= e(date('j M Y H:i', strtotime($message['created_at']))) ?></time>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= e(app_url('messages.php')) ?>" data-message-form class="form-grid">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="listing_id" value="<?= (int) ($context['listing_id'] ?? 0) ?>">
                <input type="hidden" name="order_id" value="<?= (int) ($context['order_id'] ?? 0) ?>">
                <input type="hidden" name="recipient_id" value="<?= (int) $context['recipient_id'] ?>">
                <label>Message<textarea name="body" maxlength="2000" required rows="4"></textarea></label>
                <button class="button button-primary" type="submit">Send message</button>
                <span data-form-feedback role="status" aria-live="polite"></span>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php workspace_close(); ?>
