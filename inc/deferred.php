<?php
declare(strict_types=1);

/**
 * Forms whose workflow has an "entry" phase and a later "exit" phase that
 * can't be known yet at entry time (e.g. fast-chiller exit temp/time, or
 * defrost completion) — these field keys are saved empty on first submit
 * and get a timer + a completion screen instead of blocking the initial save.
 * Value is whether the field is required in order to consider the record complete.
 */
function kl_deferred_fields(int $formId): array
{
    return match ($formId) {
        2 => ['temp_exit' => true, 'time_exit' => true],
        13 => ['exit_date' => true, 'exit_time' => true, 'temp_end' => true, 'temp_status' => true, 'exit_notes' => false],
        default => [],
    };
}

function kl_deferred_field_keys(int $formId): array
{
    return array_keys(kl_deferred_fields($formId));
}

function kl_deferred_required_keys(int $formId): array
{
    return array_keys(array_filter(kl_deferred_fields($formId)));
}

function kl_is_deferred_form(int $formId): bool
{
    return kl_deferred_fields($formId) !== [];
}

/** Whether every required-to-complete deferred field for this submission has been filled in. */
function kl_submission_is_complete(PDO $pdo, int $submissionId, array $requiredKeys): bool
{
    if (!$requiredKeys) {
        return true;
    }
    $placeholders = implode(',', array_fill(0, count($requiredKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT field_key, value FROM submission_values WHERE submission_id = ? AND field_key IN ($placeholders)"
    );
    $stmt->execute([$submissionId, ...$requiredKeys]);
    $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($requiredKeys as $key) {
        if (trim((string) ($values[$key] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

/** Open (not-yet-completed) deferred submissions for a kitchen, newest entry first. */
function kl_open_deferred_submissions(PDO $pdo, int $kitchenId): array
{
    $open = [];
    $formIds = [];
    foreach ([2, 13] as $formId) {
        if (kl_is_deferred_form($formId)) {
            $formIds[] = $formId;
        }
    }
    if (!$formIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($formIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT s.id, s.form_id, s.submitted_at, f.name AS form_name
         FROM submissions s JOIN forms f ON f.id = s.form_id
         WHERE s.kitchen_id = ? AND s.form_id IN ($placeholders)
         ORDER BY s.submitted_at DESC"
    );
    $stmt->execute([$kitchenId, ...$formIds]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $requiredKeys = kl_deferred_required_keys((int) $row['form_id']);
        if (!kl_submission_is_complete($pdo, (int) $row['id'], $requiredKeys)) {
            $open[] = $row;
        }
    }
    return $open;
}
