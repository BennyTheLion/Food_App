<?php
declare(strict_types=1);

/**
 * A kitchen can only be actively worked in by one regular user at a time.
 * Admins are exempt entirely -- they never block anyone and are never
 * blocked, and can be in the same kitchen as a regular user or another
 * admin at once. A lock is released only by an explicit exit action (or
 * logout) -- never automatically by a timer, so a user stepping away
 * mid-task doesn't get silently kicked out or let someone else take over
 * without them choosing to leave.
 */

/** Current holder of a lock on this kitchen, or null if free. */
function kl_active_lock(int $kitchenId): ?array
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'SELECT l.*, u.name AS user_name FROM kitchen_locks l
         JOIN users u ON u.id = l.user_id
         WHERE l.kitchen_id = :kitchen_id'
    );
    $stmt->execute([':kitchen_id' => $kitchenId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Attempts to enter a kitchen. Admins always succeed without taking a lock.
 * A regular user succeeds if the kitchen is free or already theirs;
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

/** Releases every lock held by this user -- call this on an explicit "exit kitchen" action, switching kitchens, or logout. */
function kl_release_user_locks(int $userId): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare('DELETE FROM kitchen_locks WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}
