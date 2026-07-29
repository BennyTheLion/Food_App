<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/kitchen_lock.php';
require __DIR__ . '/inc/activity_log.php';
require __DIR__ . '/inc/layout.php';

$user = kl_current_user();
if ($user) {
    kl_release_user_locks((int) $user['id']);
    kl_log_activity((int) $user['id'], 'logout');
}

kl_logout();
header('Location: ' . kl_url('login.php'));
