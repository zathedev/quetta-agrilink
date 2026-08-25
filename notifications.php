<?php
/** Orchard Ledger notifications: owner-scoped in-app alerts with an explicit read state. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/workspace.php';
$user=require_login();$notifications=fetch_all('SELECT * FROM notifications WHERE user_id=:user ORDER BY created_at DESC LIMIT 50',['user'=>$user['id']]);
workspace_open('Notifications','notifications');
?>
<section class="workspace-section"><div class="workspace-section-header"><div><h2>Operational alerts</h2><p>Alerts are created when an offer, booking, order, transport request, or announcement changes your next action.</p></div><span class="status-pill"><?= count($notifications) ?> recent</span></div><div class="notification-list"><?php if($notifications===[]):?><div class="listing-empty"><h2>No notifications yet.</h2><p>New platform updates will appear here.</p></div><?php else:foreach($notifications as $notification):?><article class="notification-item <?= $notification['read_at']===null?'is-unread':'' ?>"><span class="notification-dot" aria-hidden="true"></span><div><h2><?= e($notification['title']) ?></h2><p><?= e($notification['body']) ?></p><?php if($notification['action_url']):?><a class="button button-quiet" style="padding:6px 0;min-height:auto" href="<?= e(app_url($notification['action_url'])) ?>">Open related work</a><?php endif;?></div><time datetime="<?= e($notification['created_at']) ?>"><?= e(date('j M, H:i',strtotime($notification['created_at']))) ?></time></article><?php endforeach;endif;?></div></section>
<?php workspace_close(); ?>
