<?php
declare(strict_types=1);

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

    $pdo->exec('CREATE TABLE IF NOT EXISTS submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL REFERENCES forms(id),
        kitchen_name TEXT,
        station_name TEXT,
        filler_name TEXT,
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
