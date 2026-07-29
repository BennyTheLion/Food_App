<?php
declare(strict_types=1);

/**
 * A kitchen can only be actively worked in by one regular user at a time.
 * Admins are exempt entirely -- they never block anyone and are never
 * blocked, and can be in the same kitchen as a regular user or another
 * admin at once. A lock goes stale after KL_LOCK_TIMEOUT_MINUTES of
 * inactivity (no heartbeat), so a closed browser/tab doesn't permanently
 * strand a kitchen as "occupied".
 */
const KL_LOCK_TIMEOUT_MINUTES = 15;

function kl_lock_cutoff(): string
{
    return (new DateTime('-' . KL_LOCK_TIMEOUT_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
}

/** Current holder of an active (non-stale) lock on this kitchen, or null if free. */
function kl_active_lock(int $kitchenId): ?array
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'SELECT l.*, u.name AS user_name FROM kitchen_locks l
         JOIN users u ON u.id = l.user_id
         WHERE l.kitchen_id = :kitchen_id AND l.last_seen_at >= :cutoff'
    );
    $stmt->execute([':kitchen_id' => $kitchenId, ':cutoff' => kl_lock_cutoff()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Attempts to enter a kitchen. Admins always succeed without taking a lock.
 * A regular user succeeds if the kitchen is free, stale, or already theirs;
 * otherwise fails with who currently holds it.
 */
function kl_try_acquire_kitchen(array $user, int $kitchenId): array
{
    if (kl_is_admin($user)) {
        return ['ok' => true];
    }

    $pdo = kl_db();
    $lock = kl_active_lock($kitchenId);
    if ($lock && (int) $lock['user_id'] !== (int) $user['id']) {
        return ['ok' => false, 'held_by' => $lock['user_name']];
    }

    $now = (new DateTime())->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO kitchen_locks (kitchen_id, user_id, locked_at, last_seen_at) VALUES (:kitchen_id, :user_id, :now, :now)
         ON CONFLICT(kitchen_id) DO UPDATE SET user_id = excluded.user_id, locked_at = excluded.locked_at, last_seen_at = excluded.last_seen_at'
    );
    $stmt->execute([':kitchen_id' => $kitchenId, ':user_id' => $user['id'], ':now' => $now]);
    return ['ok' => true];
}

/** Keeps a regular user's lock alive while they're actively browsing a kitchen's pages. No-op for admins. */
function kl_heartbeat_kitchen(array $user, int $kitchenId): void
{
    if (kl_is_admin($user)) {
        return;
    }
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'UPDATE kitchen_locks SET last_seen_at = :now WHERE kitchen_id = :kitchen_id AND user_id = :user_id'
    );
    $stmt->execute([':now' => (new DateTime())->format('Y-m-d H:i:s'), ':kitchen_id' => $kitchenId, ':user_id' => $user['id']]);
}

/** Releases every lock held by this user (e.g. on logout or switching to a different kitchen). */
function kl_release_user_locks(int $userId): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare('DELETE FROM kitchen_locks WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}
