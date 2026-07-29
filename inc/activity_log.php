<?php
declare(strict_types=1);

/** Records a login, or an admin action (create/edit/delete on sites, dining rooms, kitchens, users, date requests). */
function kl_log_activity(?int $userId, string $action, string $details = ''): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (user_id, action, details, created_at) VALUES (:user_id, :action, :details, :now)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':details' => $details,
        ':now' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);
}

/** Activity history (most recent first) for the admin panel. */
function kl_activity_log(int $limit = 200): array
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'SELECT al.*, u.name AS user_name FROM activity_log al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.id DESC LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
