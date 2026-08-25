<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_method('POST');
verify_csrf();
$user = current_user();
if ($user !== null) {
    audit_log((int) $user['id'], 'logout', 'users', (int) $user['id']);
}
clear_authenticated_session();
session_start();
flash('success', 'You have been signed out.');
redirect('auth/login.php');

