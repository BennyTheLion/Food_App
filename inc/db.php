<?php
declare(strict_types=1);

/** Change this if you want a different initial admin login (only takes effect on a fresh database). */
const KL_DEFAULT_ADMIN_EMAIL = 'admin@kitchenlog.local';
const KL_DEFAULT_ADMIN_PASSWORD = 'ChangeMe123!';

/** From-address used for "send to admin" emails. Must match a domain actually hosted on this Hostinger account. */
const KL_MAIL_FROM = 'no-reply@darkorange-octopus-158387.hostingersite.com';

/** Public URL of this deployment -- update if you attach a real domain. Used by cron/watcher.php to check the live site from the outside. */
const KL_SITE_URL = 'https://darkorange-octopus-158387.hostingersite.com';

/** Shared secret the watcher must present if triggered over HTTP instead of CLI (see cron/watcher.php). Feel free to regenerate this. */
const KL_WATCHER_SECRET = '5701ddaba6305c3ee9eae250d1794883';

function kl_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../data/kitchen_log.sqlite';
    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS forms (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS form_fields (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL REFERENCES forms(id),
        field_key TEXT NOT NULL,
        label TEXT NOT NULL,
        field_type TEXT NOT NULL,
        required INTEGER NOT NULL DEFAULT 0,
        order_index INTEGER NOT NULL DEFAULT 0,
        options TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS dining_rooms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id INTEGER NOT NULL REFERENCES sites(id),
        name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    kl_migrate_legacy_kitchens($pdo);

    $pdo->exec('CREATE TABLE IF NOT EXISTS kitchens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dining_room_id INTEGER NOT NULL REFERENCES dining_rooms(id),
        name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS kitchen_locks (
        kitchen_id INTEGER PRIMARY KEY REFERENCES kitchens(id),
        user_id INTEGER NOT NULL REFERENCES users(id),
        locked_at TEXT NOT NULL,
        last_seen_at TEXT NOT NULL
    )');

    // Permanent audit trail of every connect/disconnect, unlike kitchen_locks
    // which only reflects current state.
    $pdo->exec('CREATE TABLE IF NOT EXISTS kitchen_connection_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        kitchen_id INTEGER NOT NULL REFERENCES kitchens(id),
        connected_at TEXT NOT NULL,
        disconnected_at TEXT,
        disconnected_reason TEXT
    )');

    // General action log: logins and every admin CRUD action (sites,
    // dining rooms, kitchens, users, date-request decisions). Form
    // submissions already have their own permanent record in `submissions`
    // (who/where/when); this covers everything else.
    $pdo->exec('CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER REFERENCES users(id),
        action TEXT NOT NULL,
        details TEXT,
        created_at TEXT NOT NULL
    )');

    // Singleton row (id always 1) tracking uptime-watcher state, so it only
    // emails on a status *change* rather than every single check.
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_health (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        status TEXT NOT NULL DEFAULT ' . "'unknown'" . ',
        last_checked_at TEXT,
        last_status_change_at TEXT,
        last_error TEXT
    )');

    // Every watcher run, not just the latest state -- so you can confirm the
    // cron job itself is actually firing on schedule and see the full history.
    $pdo->exec('CREATE TABLE IF NOT EXISTS watcher_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ran_at TEXT NOT NULL,
        status TEXT NOT NULL,
        status_changed INTEGER NOT NULL DEFAULT 0,
        alert_sent INTEGER NOT NULL DEFAULT 0,
        error TEXT,
        duration_ms INTEGER
    )');

    // users.site_id predates "every user can access every site/kitchen" -- no
    // longer used for access control, left in place (unused) to avoid an
    // unnecessary schema change on databases that already have it.
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT,
        google_id TEXT,
        role TEXT NOT NULL DEFAULT ' . "'user'" . ',
        site_id INTEGER REFERENCES sites(id),
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS date_open_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kitchen_id INTEGER NOT NULL REFERENCES kitchens(id),
        form_id INTEGER NOT NULL REFERENCES forms(id),
        requested_by INTEGER NOT NULL REFERENCES users(id),
        requested_date TEXT NOT NULL,
        reason TEXT,
        status TEXT NOT NULL DEFAULT ' . "'pending'" . ',
        decided_by INTEGER REFERENCES users(id),
        decided_at TEXT,
        created_at TEXT NOT NULL
    )');

    kl_migrate_legacy_submissions($pdo);

    $pdo->exec('CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL REFERENCES forms(id),
        kitchen_id INTEGER NOT NULL REFERENCES kitchens(id),
        filled_by INTEGER NOT NULL REFERENCES users(id),
        submitted_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS submission_values (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        submission_id INTEGER NOT NULL REFERENCES submissions(id),
        field_key TEXT NOT NULL,
        value TEXT
    )');

    // Forms are code-seeded from data/forms-data.json, mirroring the source
    // system: this JSON is the single source of truth and always wins.
    kl_seed_forms($pdo);
    kl_seed_default_admin($pdo);

    return $pdo;
}

function kl_seed_forms(PDO $pdo): void
{
    $json = json_decode(file_get_contents(__DIR__ . '/../data/forms-data.json'), true);

    $pdo->beginTransaction();

    $upsertForm = $pdo->prepare(
        'INSERT INTO forms (id, name, description, category, active) VALUES (:id, :name, :description, :category, :active)
         ON CONFLICT(id) DO UPDATE SET name = excluded.name, description = excluded.description,
             category = excluded.category, active = excluded.active'
    );
    foreach ($json['forms'] as $form) {
        $upsertForm->execute([
            ':id' => $form['id'],
            ':name' => $form['name'],
            ':description' => $form['description'],
            ':category' => $form['category'],
            ':active' => array_key_exists('active', $form) && $form['active'] === false ? 0 : 1,
        ]);
    }

    $pdo->exec('DELETE FROM form_fields');
    $insertField = $pdo->prepare(
        'INSERT INTO form_fields (form_id, field_key, label, field_type, required, order_index, options)
         VALUES (:form_id, :field_key, :label, :field_type, :required, :order_index, :options)'
    );
    foreach ($json['fields'] as $field) {
        $insertField->execute([
            ':form_id' => $field['formId'],
            ':field_key' => $field['fieldKey'],
            ':label' => $field['label'],
            ':field_type' => $field['fieldType'],
            ':required' => !empty($field['required']) ? 1 : 0,
            ':order_index' => $field['orderIndex'],
            ':options' => $field['options'] ?? null,
        ]);
    }

    $pdo->commit();
}

/** Seeds exactly one admin login so the app is reachable on a fresh database. Change the password after first login. */
function kl_seed_default_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, site_id, created_at)
         VALUES (:name, :email, :password_hash, :role, NULL, :created_at)'
    );
    $stmt->execute([
        ':name' => 'מנהל מערכת',
        ':email' => KL_DEFAULT_ADMIN_EMAIL,
        ':password_hash' => password_hash(KL_DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);
}

/**
 * Self-heal: some early deployments created `submissions` before the
 * multi-site/kitchen schema existed (kitchen_name/station_name/filler_name
 * columns instead of kitchen_id/filled_by). CREATE TABLE IF NOT EXISTS is a
 * no-op against that stale table, so every query referencing kitchen_id
 * would fail. Anything in that old table can only be pre-auth test data
 * (no real kitchens/users existed yet to attribute rows to), so it's safe
 * to drop and let it recreate with the current schema.
 */
function kl_migrate_legacy_submissions(PDO $pdo): void
{
    $tableExists = $pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='submissions'"
    )->fetchColumn();
    if (!$tableExists) {
        return;
    }

    $columns = array_column($pdo->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (in_array('kitchen_id', $columns, true)) {
        return; // already current schema
    }

    $pdo->exec('DROP TABLE IF EXISTS submission_values');
    $pdo->exec('DROP TABLE IF EXISTS submissions');
}

/**
 * Self-heal: kitchens used to belong directly to a site (site_id). Now they
 * belong to a dining_room, which belongs to a site. Unlike the submissions
 * migration, existing kitchens here may be real data someone already
 * created (not disposable test rows), so this preserves them: add the new
 * column, create one default dining room per site that had kitchens, and
 * attach those kitchens to it. Must run after dining_rooms exists and
 * before the kitchens CREATE TABLE IF NOT EXISTS (which only fires for a
 * truly fresh install where the table doesn't exist yet).
 */
function kl_migrate_legacy_kitchens(PDO $pdo): void
{
    $tableExists = $pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='kitchens'"
    )->fetchColumn();
    if (!$tableExists) {
        return;
    }

    $columns = array_column($pdo->query('PRAGMA table_info(kitchens)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (in_array('dining_room_id', $columns, true)) {
        return; // already migrated
    }
    if (!in_array('site_id', $columns, true)) {
        return; // unexpected shape, nothing sensible to migrate
    }

    $pdo->exec('ALTER TABLE kitchens ADD COLUMN dining_room_id INTEGER REFERENCES dining_rooms(id)');

    $siteIds = $pdo->query('SELECT DISTINCT site_id FROM kitchens WHERE site_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    $insertRoom = $pdo->prepare('INSERT INTO dining_rooms (site_id, name, created_at) VALUES (:site_id, :name, :created_at)');
    $attachKitchens = $pdo->prepare('UPDATE kitchens SET dining_room_id = :room_id WHERE site_id = :site_id AND dining_room_id IS NULL');

    foreach ($siteIds as $siteId) {
        $insertRoom->execute([
            ':site_id' => $siteId,
            ':name' => 'חדר אוכל ראשי',
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
        $attachKitchens->execute([':room_id' => $pdo->lastInsertId(), ':site_id' => $siteId]);
    }
}
