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
 *
 * Every connect/disconnect (by anyone, including admins, who don't take a
 * lock but are still logged) is recorded permanently in
 * kitchen_connection_log for the admin panel's audit view -- separate from
 * kitchen_locks, which only reflects who currently holds what.
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
 * otherwise fails with who currently holds it. Every successful entry is
 * logged to kitchen_connection_log.
 */
function kl_try_acquire_kitchen(array $user, int $kitchenId): array
{
    if (kl_is_admin($user)) {
        kl_log_connection((int) $user['id'], $kitchenId);
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
    kl_log_connection((int) $user['id'], $kitchenId);
    return ['ok' => true];
}

/** Releases every lock held by this user -- call this on an explicit "exit kitchen" action, switching kitchens, or logout. */
function kl_release_user_locks(int $userId): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare('DELETE FROM kitchen_locks WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    kl_close_connection_log_for_user($userId, 'exit');
}

/**
 * Admin safety valve: force-releases whoever currently holds this specific
 * kitchen (e.g. an abandoned session). Only closes that specific user's log
 * entry for this kitchen -- an admin viewing/entering the same kitchen
 * (which doesn't take a lock) must not have their own session closed as a
 * side effect of disconnecting someone else.
 */
function kl_force_release_kitchen(int $kitchenId): void
{
    $lock = kl_active_lock($kitchenId);
    $pdo = kl_db();
    $stmt = $pdo->prepare('DELETE FROM kitchen_locks WHERE kitchen_id = :kitchen_id');
    $stmt->execute([':kitchen_id' => $kitchenId]);
    if ($lock) {
        kl_close_connection_log_entry((int) $lock['user_id'], $kitchenId, 'admin_disconnect');
    }
}

/** All kitchens currently held by someone, with who and since when, for the admin panel. */
function kl_all_active_locks(): array
{
    $pdo = kl_db();
    return $pdo->query(
        'SELECT l.kitchen_id, l.locked_at, u.name AS user_name, k.name AS kitchen_name, dr.name AS room_name, s.name AS site_name
         FROM kitchen_locks l
         JOIN users u ON u.id = l.user_id
         JOIN kitchens k ON k.id = l.kitchen_id
         JOIN dining_rooms dr ON dr.id = k.dining_room_id
         JOIN sites s ON s.id = dr.site_id
         ORDER BY l.locked_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Records a new connection event. */
function kl_log_connection(int $userId, int $kitchenId): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'INSERT INTO kitchen_connection_log (user_id, kitchen_id, connected_at) VALUES (:user_id, :kitchen_id, :now)'
    );
    $stmt->execute([':user_id' => $userId, ':kitchen_id' => $kitchenId, ':now' => (new DateTime())->format('Y-m-d H:i:s')]);
}

/** Closes any still-open log entries for this user (they exited, switched, or logged out). */
function kl_close_connection_log_for_user(int $userId, string $reason): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'UPDATE kitchen_connection_log SET disconnected_at = :now, disconnected_reason = :reason
         WHERE user_id = :user_id AND disconnected_at IS NULL'
    );
    $stmt->execute([':now' => (new DateTime())->format('Y-m-d H:i:s'), ':reason' => $reason, ':user_id' => $userId]);
}

/** Closes one specific user's open log entry for one specific kitchen (used when an admin force-disconnects its occupant). */
function kl_close_connection_log_entry(int $userId, int $kitchenId, string $reason): void
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'UPDATE kitchen_connection_log SET disconnected_at = :now, disconnected_reason = :reason
         WHERE user_id = :user_id AND kitchen_id = :kitchen_id AND disconnected_at IS NULL'
    );
    $stmt->execute([
        ':now' => (new DateTime())->format('Y-m-d H:i:s'),
        ':reason' => $reason,
        ':user_id' => $userId,
        ':kitchen_id' => $kitchenId,
    ]);
}

/** Connection history (most recent first) for the admin panel's audit log. */
function kl_connection_log(int $limit = 200): array
{
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'SELECT cl.*, u.name AS user_name, k.name AS kitchen_name, dr.name AS room_name, s.name AS site_name
         FROM kitchen_connection_log cl
         JOIN users u ON u.id = cl.user_id
         JOIN kitchens k ON k.id = cl.kitchen_id
         JOIN dining_rooms dr ON dr.id = k.dining_room_id
         JOIN sites s ON s.id = dr.site_id
         ORDER BY cl.connected_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
