<?php
declare(strict_types=1);

function order_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function order_allowed_transitions(string $status, string $role): array
{
    $forward = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['storage_required', 'transport_required', 'ready_for_pickup', 'cancelled'],
        'storage_required' => ['transport_required', 'ready_for_pickup', 'cancelled'],
        'transport_required' => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['picked_up', 'cancelled'],
        'picked_up' => ['in_transit'],
        'in_transit' => ['delivered'],
        'delivered' => ['completed'],
    ];
    if (!in_array($role, ['farmer', 'buyer', 'admin'], true)) {
        return [];
    }
    if ($role === 'buyer') {
        return match ($status) {
            'pending' => ['confirmed', 'cancelled'],
            'confirmed', 'storage_required', 'transport_required', 'ready_for_pickup' => ['cancelled'],
            'delivered' => ['completed'],
            default => [],
        };
    }
    return $forward[$status] ?? [];
}

function order_for_actor(int $orderId, array $actor, bool $lock = false): ?array
{
    $scope = $actor['role_slug'] === 'admin' ? '' : ' AND (o.buyer_id=:buyer_actor OR o.farmer_id=:farmer_actor)';
    $params = ['order' => $orderId];
    if ($scope !== '') {
        $params['buyer_actor'] = (int) $actor['id'];
        $params['farmer_actor'] = (int) $actor['id'];
    }
    return fetch_one('SELECT o.*, buyer.full_name AS buyer_name, farmer.full_name AS farmer_name FROM orders o JOIN users buyer ON buyer.id=o.buyer_id JOIN users farmer ON farmer.id=o.farmer_id WHERE o.id=:order' . $scope . ($lock ? ' FOR UPDATE' : '') . ' LIMIT 1', $params);
}

function update_order_status(int $orderId, string $next, array $actor, string $note = ''): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $order = order_for_actor($orderId, $actor, true);
        if ($order === null) {
            throw new RuntimeException('That order is not available to this account.');
        }
        if (!in_array($next, order_allowed_transitions((string) $order['status'], (string) $actor['role_slug']), true)) {
            throw new RuntimeException('That order cannot move to the selected status.');
        }
        $extra = $next === 'completed' ? ', completed_at=NOW()' : ($next === 'cancelled' ? ', cancelled_at=NOW()' : '');
        $pdo->prepare('UPDATE orders SET status=:status' . $extra . ' WHERE id=:id')->execute(['status' => $next, 'id' => $orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id,status,changed_by_user_id,notes) VALUES (:order,:status,:actor,:notes)')->execute([
            'order' => $orderId, 'status' => $next, 'actor' => (int) $actor['id'],
            'notes' => $note !== '' ? normalize_text($note, 500) : 'Order status updated by ' . $actor['role_name'] . '.',
        ]);
        $recipient = (int) $actor['id'] === (int) $order['buyer_id'] ? (int) $order['farmer_id'] : (int) $order['buyer_id'];
        create_notification($recipient, 'order_status_changed', 'Order ' . $order['reference_code'] . ' updated', 'The order is now ' . order_status_label($next) . '.', 'orders.php?id=' . $orderId, 'order', $orderId);
        $pdo->commit();
        audit_log((int) $actor['id'], 'order_status_changed', 'orders', $orderId, ['from' => $order['status'], 'to' => $next]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function commercial_context(int $listingId, int $orderId, array $actor, int $peerId = 0): array
{
    if (!in_array($actor['role_slug'], ['farmer', 'buyer'], true)) {
        throw new RuntimeException('Commercial messaging is available to farmers and buyers.');
    }
    if ($orderId > 0) {
        $order = order_for_actor($orderId, $actor);
        if ($order === null) { throw new RuntimeException('That order conversation is unavailable.'); }
        return ['listing_id' => null, 'order_id' => $orderId, 'recipient_id' => (int) $actor['id'] === (int) $order['buyer_id'] ? (int) $order['farmer_id'] : (int) $order['buyer_id'], 'label' => 'Order ' . $order['reference_code']];
    }
    if ($actor['role_slug'] === 'buyer') {
        $listing = fetch_one('SELECT pl.id,pl.title,pl.farmer_id,o.buyer_id FROM produce_listings pl JOIN offers o ON o.listing_id=pl.id WHERE pl.id=:listing AND o.buyer_id=:actor LIMIT 1', ['actor' => (int) $actor['id'], 'listing' => $listingId]);
    } else {
        $peerScope = $peerId > 0 ? ' AND o.buyer_id=:peer' : '';
        $params = ['actor' => (int) $actor['id'], 'listing' => $listingId];
        if ($peerId > 0) { $params['peer'] = $peerId; }
        $listing = fetch_one('SELECT pl.id,pl.title,pl.farmer_id,o.buyer_id FROM produce_listings pl JOIN offers o ON o.listing_id=pl.id WHERE pl.id=:listing AND pl.farmer_id=:actor' . $peerScope . ' ORDER BY o.updated_at DESC LIMIT 1', $params);
    }
    if ($listing === null) { throw new RuntimeException('Start a listing conversation after a buyer has submitted an offer.'); }
    $recipient = $actor['role_slug'] === 'farmer' ? (int) $listing['buyer_id'] : (int) $listing['farmer_id'];
    if ($recipient < 1) { throw new RuntimeException('No buyer is associated with that listing conversation.'); }
    return ['listing_id' => $listingId, 'order_id' => null, 'recipient_id' => $recipient, 'label' => $listing['title']];
}

function commercial_conversations(array $actor): array
{
    if (!in_array($actor['role_slug'], ['farmer', 'buyer'], true)) {
        return [];
    }

    $userId = (int) $actor['id'];
    $orders = fetch_all(
        'SELECT o.id AS order_id,NULL AS listing_id,IF(o.buyer_id=:order_peer_actor,o.farmer_id,o.buyer_id) AS peer_id,' .
        'CONCAT("Order ",o.reference_code) AS label,' .
        'GREATEST(o.updated_at,COALESCE(MAX(m.created_at),o.updated_at)) AS activity_at,' .
        'COALESCE(SUM(CASE WHEN m.recipient_id=:order_unread_actor AND m.read_at IS NULL THEN 1 ELSE 0 END),0) AS unread_count,' .
        'COALESCE(MAX(m.id),0) AS latest_message_id ' .
        'FROM orders o LEFT JOIN messages m ON m.order_id=o.id ' .
        'WHERE o.buyer_id=:order_buyer_actor OR o.farmer_id=:order_farmer_actor ' .
        'GROUP BY o.id,o.buyer_id,o.farmer_id,o.reference_code,o.updated_at',
        [
            'order_peer_actor' => $userId,
            'order_unread_actor' => $userId,
            'order_buyer_actor' => $userId,
            'order_farmer_actor' => $userId,
        ]
    );

    $listings = fetch_all(
        'SELECT NULL AS order_id,c.listing_id,IF(c.buyer_id=:listing_peer_actor,c.farmer_id,c.buyer_id) AS peer_id,' .
        'CONCAT(pl.title," · ",peer.full_name) AS label,' .
        'GREATEST(c.offer_updated_at,COALESCE(MAX(m.created_at),c.offer_updated_at)) AS activity_at,' .
        'COALESCE(SUM(CASE WHEN m.recipient_id=:listing_unread_actor AND m.read_at IS NULL THEN 1 ELSE 0 END),0) AS unread_count,' .
        'COALESCE(MAX(m.id),0) AS latest_message_id ' .
        'FROM (SELECT listing_id,buyer_id,farmer_id,MAX(updated_at) AS offer_updated_at FROM offers ' .
        'WHERE buyer_id=:listing_buyer_actor OR farmer_id=:listing_farmer_actor GROUP BY listing_id,buyer_id,farmer_id) c ' .
        'JOIN produce_listings pl ON pl.id=c.listing_id ' .
        'JOIN users peer ON peer.id=IF(c.buyer_id=:listing_peer_join_actor,c.farmer_id,c.buyer_id) ' .
        'LEFT JOIN messages m ON m.listing_id=c.listing_id AND ' .
        '((m.sender_id=:listing_sender_actor AND m.recipient_id=peer.id) OR (m.sender_id=peer.id AND m.recipient_id=:listing_recipient_actor)) ' .
        'GROUP BY c.listing_id,c.buyer_id,c.farmer_id,c.offer_updated_at,pl.title,peer.full_name',
        [
            'listing_peer_actor' => $userId,
            'listing_unread_actor' => $userId,
            'listing_buyer_actor' => $userId,
            'listing_farmer_actor' => $userId,
            'listing_peer_join_actor' => $userId,
            'listing_sender_actor' => $userId,
            'listing_recipient_actor' => $userId,
        ]
    );

    $conversations = array_merge($orders, $listings);
    foreach ($conversations as &$conversation) {
        $conversation['order_id'] = (int) ($conversation['order_id'] ?? 0);
        $conversation['listing_id'] = (int) ($conversation['listing_id'] ?? 0);
        $conversation['peer_id'] = (int) $conversation['peer_id'];
        $conversation['unread_count'] = (int) $conversation['unread_count'];
        $conversation['latest_message_id'] = (int) $conversation['latest_message_id'];
        $conversation['key'] = $conversation['order_id'] > 0
            ? 'order-' . $conversation['order_id']
            : 'listing-' . $conversation['listing_id'] . '-peer-' . $conversation['peer_id'];
        $query = $conversation['order_id'] > 0
            ? 'order_id=' . $conversation['order_id']
            : 'listing_id=' . $conversation['listing_id'] . '&peer_id=' . $conversation['peer_id'];
        $conversation['url'] = app_url('messages.php?' . $query);
    }
    unset($conversation);

    usort($conversations, static function (array $left, array $right): int {
        $activity = strcmp((string) $right['activity_at'], (string) $left['activity_at']);
        return $activity !== 0 ? $activity : $right['latest_message_id'] <=> $left['latest_message_id'];
    });

    return $conversations;
}

function send_commercial_message(array $actor, int $listingId, int $orderId, string $body, int $peerId = 0): int
{
    $body = trim($body);
    $length = function_exists('mb_strlen') ? mb_strlen($body) : strlen($body);
    if ($length < 1 || $length > 2000) { throw new RuntimeException('Enter a message of up to 2,000 characters.'); }
    $context = commercial_context($listingId, $orderId, $actor, $peerId);
    $statement = db()->prepare('INSERT INTO messages (sender_id,recipient_id,listing_id,order_id,body) VALUES (:sender,:recipient,:listing,:order_id,:body)');
    $statement->execute(['sender' => (int) $actor['id'], 'recipient' => $context['recipient_id'], 'listing' => $context['listing_id'], 'order_id' => $context['order_id'], 'body' => $body]);
    $messageId = (int) db()->lastInsertId();
    create_notification($context['recipient_id'], 'commercial_message', 'New commercial message', $actor['full_name'] . ' sent a message about ' . $context['label'] . '.', 'messages.php?' . ($context['order_id'] ? 'order_id=' . $context['order_id'] : 'listing_id=' . $context['listing_id'] . '&peer_id=' . (int) $actor['id']), 'message', $messageId);
    audit_log((int) $actor['id'], 'commercial_message_sent', 'messages', $messageId, ['listing_id' => $context['listing_id'], 'order_id' => $context['order_id']]);
    return $messageId;
}

function record_order_review(array $actor, int $orderId, int $rating, string $comment): void
{
    if (!in_array($actor['role_slug'], ['farmer', 'buyer'], true) || $rating < 1 || $rating > 5) { throw new RuntimeException('Choose a rating from 1 to 5.'); }
    $order = order_for_actor($orderId, $actor);
    if ($order === null || $order['status'] !== 'completed') { throw new RuntimeException('Reviews can be recorded only after a completed order.'); }
    $reviewed = (int) $actor['id'] === (int) $order['buyer_id'] ? (int) $order['farmer_id'] : (int) $order['buyer_id'];
    $comment = normalize_text($comment, 1000);
    db()->prepare('INSERT INTO reviews (reviewer_id,reviewed_user_id,order_id,rating,comment,status) VALUES (:reviewer,:reviewed,:order_id,:rating,:comment,"published") ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment),status="published",updated_at=NOW()')->execute(['reviewer' => (int) $actor['id'], 'reviewed' => $reviewed, 'order_id' => $orderId, 'rating' => $rating, 'comment' => $comment !== '' ? $comment : null]);
    audit_log((int) $actor['id'], 'order_review_recorded', 'orders', $orderId, ['rating' => $rating]);
}
