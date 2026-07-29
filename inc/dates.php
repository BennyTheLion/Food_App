<?php
declare(strict_types=1);

/**
 * Which date fields represent "when this record happened" (restricted to today
 * for non-admins) versus a genuinely future/independent date like an expiry
 * date, which is never restricted.
 */
function kl_is_restricted_date_field(string $fieldKey): bool
{
    return $fieldKey !== 'expiry_date';
}

/** Whether `$user` may submit `$date` (Y-m-d) for `$kitchenId`, either because it's today, they're admin, or a request was approved. */
function kl_date_allowed(array $user, int $kitchenId, string $date): bool
{
    if (kl_is_admin($user)) {
        return true;
    }
    if ($date === (new DateTime())->format('Y-m-d')) {
        return true;
    }
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM date_open_requests
         WHERE kitchen_id = :kitchen_id AND requested_date = :date AND status = 'approved'"
    );
    $stmt->execute([':kitchen_id' => $kitchenId, ':date' => $date]);
    return (int) $stmt->fetchColumn() > 0;
}

function kl_create_date_request(int $kitchenId, int $formId, int $userId, string $date, string $reason): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'INSERT INTO date_open_requests (kitchen_id, form_id, requested_by, requested_date, reason, status, created_at)
         VALUES (:kitchen_id, :form_id, :user_id, :date, :reason, ' . "'pending'" . ', :created_at)'
    );
    $stmt->execute([
        ':kitchen_id' => $kitchenId,
        ':form_id' => $formId,
        ':user_id' => $userId,
        ':date' => $date,
        ':reason' => $reason,
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);
}
