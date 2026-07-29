<?php
declare(strict_types=1);

/**
 * Deliberately outside the normal require chain (no auth.php/layout.php) --
 * this needs to work even if something else in the app is broken, and
 * respond fast for cron/watcher.php to poll. Checks that PHP is running
 * AND that the database is actually reachable and queryable, not just
 * that the web server answers.
 */
header('Content-Type: application/json; charset=UTF-8');

try {
    require __DIR__ . '/inc/db.php';
    $pdo = kl_db();
    $formCount = (int) $pdo->query('SELECT COUNT(*) FROM forms')->fetchColumn();

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'forms' => $formCount, 'time' => date('c')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'time' => date('c')]);
}
