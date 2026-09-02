<?php
/** Poll the active commercial thread and return conversation activity without exposing unrelated messages. */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('GET');
$user = require_role(['farmer', 'buyer']);
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$peerId = filter_input(INPUT_GET, 'peer_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$sinceId = filter_input(INPUT_GET, 'since_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$messages = [];

if ($orderId > 0 || $listingId > 0) {
    try {
        $context = commercial_context($listingId, $orderId, $user, $peerId);
        $params = ['since_id' => $sinceId];

        if ($orderId > 0) {
            $where = 'm.order_id=:thread_order';
            $params['thread_order'] = $orderId;
            $readWhere = 'order_id=:read_order';
            $readParams = ['read_order' => $orderId];
        } else {
            $where = 'm.listing_id=:thread_listing AND ((m.sender_id=:thread_user_sender AND m.recipient_id=:thread_peer_recipient) OR (m.sender_id=:thread_peer_sender AND m.recipient_id=:thread_user_recipient))';
            $params += [
                'thread_listing' => $listingId,
                'thread_user_sender' => (int) $user['id'],
                'thread_peer_recipient' => (int) $context['recipient_id'],
                'thread_peer_sender' => (int) $context['recipient_id'],
                'thread_user_recipient' => (int) $user['id'],
            ];
            $readWhere = 'listing_id=:read_listing';
            $readParams = ['read_listing' => $listingId];
        }

        $rows = fetch_all(
            'SELECT m.id,m.sender_id,m.body,m.created_at,u.full_name AS sender_name FROM messages m ' .
            'JOIN users u ON u.id=m.sender_id WHERE ' . $where . ' AND m.id>:since_id ORDER BY m.id',
            $params
        );
        execute_query(
            'UPDATE messages SET read_at=NOW() WHERE ' . $readWhere . ' AND recipient_id=:read_user AND sender_id=:read_peer AND read_at IS NULL',
            $readParams + ['read_user' => (int) $user['id'], 'read_peer' => (int) $context['recipient_id']]
        );

        foreach ($rows as $row) {
            $messages[] = [
                'id' => (int) $row['id'],
                'sender_id' => (int) $row['sender_id'],
                'sender_name' => $row['sender_name'],
                'body' => $row['body'],
                'created_at' => $row['created_at'],
                'created_label' => date('j M Y H:i', strtotime((string) $row['created_at'])),
            ];
        }
    } catch (Throwable $exception) {
        json_response(false, $exception->getMessage(), [], 422);
    }
}

json_response(true, 'Messages synchronized.', [
    'messages' => $messages,
    'conversations' => commercial_conversations($user),
]);
