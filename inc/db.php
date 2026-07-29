<?php
declare(strict_types=1);

/** Change this if you want a different initial admin login (only takes effect on a fresh database). */
const KL_DEFAULT_ADMIN_EMAIL = 'admin@kitchenlog.local';
const KL_DEFAULT_ADMIN_PASSWORD = 'ChangeMe123!';

/** From-address used for "send to admin" emails. Must match a domain actually hosted on this Hostinger account. */
const KL_MAIL_FROM = 'no-reply@darkorange-octopus-158387.hostingersite.com';

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

    $pdo->exec('CREATE TABLE IF NOT EXISTS kitchens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id INTEGER NOT NULL REFERENCES sites(id),
        name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

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
