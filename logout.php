<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

kl_logout();
header('Location: ' . kl_url('login.php'));
