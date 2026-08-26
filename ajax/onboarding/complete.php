<?php
/** Non-blocking onboarding completion endpoint: account-scoped, CSRF-protected, and safe for standard form fallback. */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_method('POST');
verify_csrf();
$user = require_login();
complete_onboarding((int) $user['id']);
if (is_ajax_request()) {
    json_response(true, 'The getting-started guide is complete.');
}
flash('success', 'The getting-started guide is complete. You can reopen any workspace task from the shortcuts below.');
redirect(dashboard_path($user['role_slug']));
