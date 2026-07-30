<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/kitchen_lock.php';
require __DIR__ . '/inc/deferred.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();
$kitchen = kl_require_kitchen($user);
if (!$kitchen) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$pdo = kl_db();

$submissionId = (int) ($_GET['sid'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT s.*, f.name AS form_name, f.description AS form_description
     FROM submissions s JOIN forms f ON f.id = s.form_id WHERE s.id = :id'
);
$stmt->execute([':id' => $submissionId]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission || (int) $submission['kitchen_id'] !== (int) $kitchen['id']) {
    http_response_code(404);
    kl_head('רשומה לא נמצאה');
    kl_topbar(kl_url('index.php'), 'לרשימת הטפסים');
    echo '<main class="container"><div class="empty">הרשומה המבוקשת לא נמצאה עבור מטבח זה.</div></main>';
    kl_foot();
    exit;
}

$deferredKeys = kl_deferred_field_keys((int) $submission['form_id']);
$requiredKeys = kl_deferred_required_keys((int) $submission['form_id']);
if (!$deferredKeys || kl_submission_is_complete($pdo, $submissionId, $requiredKeys)) {
    header('Location: ' . kl_url('index.php'));
    exit;
}

$fieldStmt = $pdo->prepare(
    'SELECT * FROM form_fields WHERE form_id = ? AND field_key IN (' .
    implode(',', array_fill(0, count($deferredKeys), '?')) . ') ORDER BY order_index'
);
$fieldStmt->execute([$submission['form_id'], ...$deferredKeys]);
$deferredFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);

// context: show the already-saved entry-phase values alongside the completion form
$valStmt = $pdo->prepare('SELECT field_key, value FROM submission_values WHERE submission_id = :id');
$valStmt->execute([':id' => $submissionId]);
$existingValues = $valStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$allFieldsStmt = $pdo->prepare('SELECT * FROM form_fields WHERE form_id = :id ORDER BY order_index');
$allFieldsStmt->execute([':id' => $submission['form_id']]);
$entryFields = array_filter(
    $allFieldsStmt->fetchAll(PDO::FETCH_ASSOC),
    fn($f) => !in_array($f['field_key'], $deferredKeys, true)
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    foreach ($deferredFields as $field) {
        $key = $field['field_key'];
        $val = trim((string) ($_POST[$key] ?? ''));
        if (in_array($key, $requiredKeys, true) && $val === '') {
            $errors[$key] = 'שדה חובה';
        }
        $updates[$key] = $val;
    }

    if (!$errors) {
        $updStmt = $pdo->prepare(
            'UPDATE submission_values SET value = :val WHERE submission_id = :sid AND field_key = :key'
        );
        foreach ($updates as $key => $val) {
            $updStmt->execute([':val' => $val, ':sid' => $submissionId, ':key' => $key]);
            if ($updStmt->rowCount() === 0) {
                $pdo->prepare('INSERT INTO submission_values (submission_id, field_key, value) VALUES (:sid, :key, :val)')
                    ->execute([':sid' => $submissionId, ':key' => $key, ':val' => $val]);
            }
        }
        header('Location: ' . kl_url('success.php') . '?form=' . (int) $submission['form_id'] . '&sid=' . $submissionId);
        exit;
    }
}

kl_head('השלמת רישום · ' . $submission['form_name']);
kl_topbar(kl_url('index.php'), 'לרשימת הטפסים');
kl_context_bar($user, $kitchen);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">השלמת שלב יציאה</div>
    <h1><?= kl_h($submission['form_name']) ?></h1>
    <p>הרשומה נפתחה לפני <span data-elapsed-since="<?= kl_h(str_replace(' ', 'T', $submission['submitted_at'])) ?>">…</span></p>
  </div>

  <div class="request-card" style="margin-bottom:18px;">
    <?php foreach ($entryFields as $f):
      $v = $existingValues[$f['field_key']] ?? '';
      if ($v === '') continue;
    ?>
      <div><strong><?= kl_h($f['label']) ?>:</strong> <?= kl_h($v) ?></div>
    <?php endforeach; ?>
  </div>

  <form method="post" novalidate>
    <?php foreach ($deferredFields as $field):
      $key = $field['field_key'];
      $label = $field['label'];
      $type = $field['field_type'];
      $inputType = in_array($type, ['text', 'number', 'date', 'time'], true) ? $type : 'text';
    ?>
      <?php if ($type === 'textarea'): ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?></label>
          <textarea id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>"><?= kl_h($existingValues[$key] ?? '') ?></textarea>
        </div>
      <?php else: ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?><?php if (in_array($key, $requiredKeys, true)): ?><span class="req">*</span><?php endif; ?></label>
          <input type="<?= $inputType ?>" id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>"
                 value="<?= kl_h($existingValues[$key] ?? '') ?>"
                 <?php if (isset($errors[$key])): ?>aria-invalid="true"<?php endif; ?>>
          <?php if (isset($errors[$key])): ?>
            <p style="color:var(--danger-600); font-size:13px; margin-top:6px;"><?= kl_h($errors[$key]) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="submit-bar">
      <div class="submit-bar__inner">
        <button type="submit" class="btn btn-primary btn-block">שמירת השלמה</button>
      </div>
    </div>
  </form>
</main>
<?php
kl_foot();
