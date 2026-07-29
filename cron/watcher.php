<?php
declare(strict_types=1);

/**
 * Uptime watcher -- meant to be triggered on a schedule (e.g. every 5-15
 * minutes) by Hostinger's Cron Jobs feature, either as a CLI command
 * (php cron/watcher.php -- preferred) or as a URL fetch if your plan only
 * supports that (https://yoursite/cron/watcher.php?secret=...).
 *
 * Checks the site from the OUTSIDE (an HTTP request to its own public URL),
 * not just internal PHP/DB state -- this is deliberate: a script running on
 * the same server can't tell you the web server itself is unreachable, but
 * it CAN tell you whether a real HTTP request to your own site succeeds,
 * which is what an actual visitor experiences.
 *
 * Real limitation, stated plainly: this cron job runs on the same
 * Hostinger account it's checking. If the entire hosting account or server
 * goes down, cron stops running too, so this can't detect a total outage --
 * only application-level failures (a broken page, a database error, a bug)
 * while the server itself is still up. True "is the whole server down"
 * monitoring needs a genuinely external service (e.g. UptimeRobot's free
 * tier) pointed at this same site -- that's a five-minute signup on their
 * end, not something achievable from inside this codebase.
 */

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/mailer.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $secret = $_GET['secret'] ?? '';
    if (!hash_equals(KL_WATCHER_SECRET, (string) $secret)) {
        http_response_code(403);
        exit('forbidden');
    }
}

$pdo = kl_db();

$target = rtrim(KL_SITE_URL, '/') . '/healthcheck.php';
$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$decoded = $body !== false ? json_decode($body, true) : null;
$isUp = $httpCode === 200 && is_array($decoded) && ($decoded['status'] ?? null) === 'ok';

$error = null;
if (!$isUp) {
    if ($curlError) {
        $error = "Connection failed: $curlError";
    } elseif ($httpCode !== 200) {
        $error = "HTTP $httpCode";
    } else {
        $error = 'Unexpected response: ' . substr((string) $body, 0, 200);
    }
}

$previous = $pdo->query('SELECT * FROM site_health WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$previousStatus = $previous['status'] ?? 'unknown';
$newStatus = $isUp ? 'up' : 'down';
$now = (new DateTime())->format('Y-m-d H:i:s');
$statusChanged = $previousStatus !== $newStatus;
$changedAt = $statusChanged || !$previous ? $now : $previous['last_status_change_at'];

$pdo->prepare(
    'INSERT INTO site_health (id, status, last_checked_at, last_status_change_at, last_error)
     VALUES (1, :status, :now, :changed_at, :error)
     ON CONFLICT(id) DO UPDATE SET status = excluded.status, last_checked_at = excluded.last_checked_at,
         last_status_change_at = excluded.last_status_change_at, last_error = excluded.last_error'
)->execute([
    ':status' => $newStatus,
    ':now' => $now,
    ':changed_at' => $changedAt,
    ':error' => $error,
]);

if ($statusChanged && $previousStatus !== 'unknown') {
    if ($newStatus === 'down') {
        kl_send_alert_email(
            '🔴 האתר אינו זמין',
            "האתר בכתובת " . KL_SITE_URL . " אינו מגיב כראוי.\n\nשגיאה: $error\n\nזמן: $now"
        );
    } else {
        kl_send_alert_email(
            '🟢 האתר חזר לפעול',
            "האתר בכתובת " . KL_SITE_URL . " חזר לפעול כרגיל.\n\nזמן: $now"
        );
    }
}

echo "status=$newStatus" . ($error ? " error=\"$error\"" : '') . ($statusChanged ? ' (changed)' : '') . "\n";
