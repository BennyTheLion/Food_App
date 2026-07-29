<?php
declare(strict_types=1);

/** Email addresses of every admin user — the destination for "send to admin" actions. */
function kl_admin_emails(): array
{
    $pdo = kl_db();
    return $pdo->query("SELECT email FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Plain-text alert to every admin, e.g. from cron/watcher.php. Standalone
 * (doesn't depend on kl_h()/layout.php) since the watcher runs outside the
 * normal page request chain. Content here is always system-generated, never
 * user input, so plain text is fine -- no HTML escaping needed.
 */
function kl_send_alert_email(string $subject, string $bodyText): bool
{
    $admins = kl_admin_emails();
    if (!$admins) {
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'From: ' . KL_MAIL_FROM . "\r\n";

    $ok = true;
    foreach ($admins as $email) {
        $ok = mail($email, $encodedSubject, $bodyText, $headers) && $ok;
    }
    return $ok;
}

/**
 * Sends a submission's field values as an HTML table to every admin.
 * Uses PHP's mail(); Hostinger shared hosting sends this through its own
 * mail transfer agent, no SMTP config needed. Returns true only if every
 * admin recipient's message was handed off successfully.
 */
function kl_send_submission_email(array $submission, array $rows): bool
{
    $admins = kl_admin_emails();
    if (!$admins) {
        return false;
    }

    $subject = '=?UTF-8?B?' . base64_encode($submission['form_name'] . ' - ' . $submission['kitchen_name']) . '?=';

    $body = '<div dir="rtl" style="font-family:Arial,sans-serif;">';
    $body .= '<h2>' . kl_h($submission['form_name']) . '</h2>';
    $body .= '<p>אתר: ' . kl_h($submission['site_name']) . ' | מטבח: ' . kl_h($submission['kitchen_name'])
        . ' | ממלא: ' . kl_h($submission['filler_name']) . ' | ' . kl_h($submission['submitted_at']) . '</p>';
    $body .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">';
    foreach ($rows as $row) {
        $body .= '<tr><th style="text-align:right;background:#f0f0f0;">' . kl_h($row['label']) . '</th>'
            . '<td>' . kl_h($row['value']) . '</td></tr>';
    }
    $body .= '</table></div>';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . KL_MAIL_FROM . "\r\n";

    $ok = true;
    foreach ($admins as $email) {
        $ok = mail($email, $subject, $body, $headers) && $ok;
    }
    return $ok;
}
