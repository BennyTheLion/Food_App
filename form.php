<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/gauge.php';
require __DIR__ . '/inc/layout.php';

$pdo = kl_db();

$formId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forms WHERE id = :id');
$stmt->execute([':id' => $formId]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    http_response_code(404);
    kl_head('טופס לא נמצא');
    kl_topbar('/Food_App/kitchen-log-app/index.php', 'לרשימת הטפסים');
    echo '<main class="container"><div class="empty">הטופס המבוקש לא נמצא.</div></main>';
    kl_foot();
    exit;
}

$fieldStmt = $pdo->prepare('SELECT * FROM form_fields WHERE form_id = :id ORDER BY order_index');
$fieldStmt->execute([':id' => $formId]);
$fields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kitchenName = trim((string) ($_POST['kitchen_name'] ?? ''));
    $stationName = trim((string) ($_POST['station_name'] ?? ''));
    $fillerName = trim((string) ($_POST['filler_name'] ?? ''));

    $values = [];
    foreach ($fields as $field) {
        $key = $field['field_key'];
        $type = $field['field_type'];
        if ($type === 'checklist') {
            $val = isset($_POST[$key]) && is_array($_POST[$key]) ? implode(', ', $_POST[$key]) : '';
        } elseif ($type === 'checkbox') {
            $val = isset($_POST[$key]) ? '1' : '0';
        } else {
            $val = trim((string) ($_POST[$key] ?? ''));
        }
        if ($field['required'] && $val === '') {
            $errors[$key] = 'שדה חובה';
        }
        $values[$key] = $val;
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $insSub = $pdo->prepare(
            'INSERT INTO submissions (form_id, kitchen_name, station_name, filler_name, submitted_at)
             VALUES (:form_id, :kitchen_name, :station_name, :filler_name, :submitted_at)'
        );
        $insSub->execute([
            ':form_id' => $formId,
            ':kitchen_name' => $kitchenName,
            ':station_name' => $stationName,
            ':filler_name' => $fillerName,
            ':submitted_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
        $submissionId = (int) $pdo->lastInsertId();

        $insVal = $pdo->prepare('INSERT INTO submission_values (submission_id, field_key, value) VALUES (:sid, :key, :val)');
        foreach ($values as $key => $val) {
            $insVal->execute([':sid' => $submissionId, ':key' => $key, ':val' => $val]);
        }
        $pdo->commit();

        header('Location: /Food_App/kitchen-log-app/success.php?form=' . $formId . '&sid=' . $submissionId);
        exit;
    }
}

/** Renders one dynamic-rows block (units / dishes / workers). */
function kl_render_dynamic_rows(string $fieldKey, string $kind): void
{
    $configs = [
        'units' => ['add' => '+ הוסף יחידה', 'cols' => [
            ['col' => 'name', 'placeholder' => 'שם המקרר/מקפיא'],
            ['col' => 'temp_start', 'placeholder' => 'טמפ׳ תחילת יום (°C)'],
            ['col' => 'temp_end', 'placeholder' => 'טמפ׳ סיום יום (°C)'],
        ]],
        'dishes' => ['add' => '+ הוסף מנה', 'cols' => [
            ['col' => 'name', 'placeholder' => 'שם המנה'],
            ['col' => 'temp', 'placeholder' => 'טמפרטורה (°C)'],
        ]],
        'workers' => ['add' => '+ הוסף עובד', 'cols' => [
            ['col' => 'name', 'placeholder' => 'שם העובד'],
            ['col' => 'status', 'placeholder' => 'תקין / לא תקין'],
        ]],
    ];
    $cfg = $configs[$kind];
    ?>
    <div data-dynamic-rows data-field-key="<?= kl_h($fieldKey) ?>">
      <div class="rows"></div>
      <template>
        <div class="row-item">
          <?php foreach ($cfg['cols'] as $c): ?>
            <input type="text" data-col="<?= kl_h($c['col']) ?>" placeholder="<?= kl_h($c['placeholder']) ?>">
          <?php endforeach; ?>
          <button type="button" class="row-item__remove" aria-label="הסר">×</button>
        </div>
      </template>
      <button type="button" class="btn btn-ghost add-row-btn"><?= kl_h($cfg['add']) ?></button>
    </div>
    <?php
}

kl_head($form['name']);
kl_topbar('/Food_App/kitchen-log-app/index.php', 'לרשימת הטפסים');
kl_station_bar();
?>
<main class="container">
  <div class="hero">
    <?php if (preg_match('/R-\d{3}-\d{2}/', $form['description'], $m)): ?>
      <div class="hero__eyebrow"><?= kl_h($m[0]) ?></div>
    <?php endif; ?>
    <h1><?= kl_h($form['name']) ?></h1>
    <p><?= kl_h($form['description']) ?></p>
  </div>

  <form method="post" data-serialize-rows novalidate>
    <div class="field">
      <label class="field__label" for="kitchen_name">שם המטבח / אתר</label>
      <input type="text" id="kitchen_name" name="kitchen_name" placeholder="לדוגמה: מגדל העמק - לוין">
    </div>
    <div class="field">
      <label class="field__label">תחנה</label>
      <input type="text" name="station_name" class="js-station-value" readonly>
    </div>
    <div class="field">
      <label class="field__label">שם הממלא</label>
      <input type="text" name="filler_name" class="js-filler-value" placeholder="שם מלא" required>
    </div>

    <?php foreach ($fields as $field):
      $key = $field['field_key'];
      $label = $field['label'];
      $type = $field['field_type'];
      $required = (bool) $field['required'];
      $options = $field['options'] ? json_decode($field['options'], true) : null;
    ?>

      <?php if (in_array($label, ['__units__', '__dishes__', '__workers__'], true)): ?>
        <div class="field">
          <label class="field__label"><?= $label === '__units__' ? 'קריאות טמפרטורה למקררים/מקפיאים' : ($label === '__dishes__' ? 'קריאות טמפרטורה למנות' : 'בדיקת עובדים') ?></label>
          <?php kl_render_dynamic_rows($key, $label === '__units__' ? 'units' : ($label === '__dishes__' ? 'dishes' : 'workers')); ?>
          <input type="hidden" name="<?= kl_h($key) ?>" value="[]">
        </div>

      <?php elseif ($type === 'temp_slider' || $type === 'ppm_slider'):
        $g = kl_gauge_config($formId, $key, $type);
        $mid = (int) round(($g['safeMin'] + $g['safeMax']) / 2 / $g['step']) * $g['step'];
        $bandLeft = ($g['safeMin'] - $g['min']) / ($g['max'] - $g['min']) * 100;
        $bandWidth = ($g['safeMax'] - $g['safeMin']) / ($g['max'] - $g['min']) * 100;
      ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?><?php if ($required): ?><span class="req">*</span><?php endif; ?></label>
          <div class="gauge" data-safe-min="<?= $g['safeMin'] ?>" data-safe-max="<?= $g['safeMax'] ?>"
               <?php if (isset($g['altSafeMin'])): ?>data-alt-safe-min="<?= $g['altSafeMin'] ?>" data-alt-safe-max="<?= $g['altSafeMax'] ?>"<?php endif; ?>>
            <div class="gauge__readout"><span class="value"><?= $mid ?></span><span class="unit"><?= kl_h($g['unit']) ?></span></div>
            <div class="gauge__track-wrap">
              <div class="gauge__band" style="right: <?= $bandLeft ?>%; width: <?= $bandWidth ?>%;"></div>
              <input type="range" id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>"
                     min="<?= $g['min'] ?>" max="<?= $g['max'] ?>" step="<?= $g['step'] ?>" value="<?= $mid ?>">
            </div>
            <div class="gauge__scale"><span><?= $g['min'] ?></span><span><?= $g['max'] ?></span></div>
            <div class="gauge__hint">טווח תקין: <?= $g['safeMin'] ?>–<?= $g['safeMax'] ?> <?= kl_h($g['unit']) ?></div>
          </div>
        </div>

      <?php elseif ($type === 'checklist'): ?>
        <div class="field">
          <label class="field__label"><?= kl_h($label) ?></label>
          <div class="checklist-grid">
            <?php foreach ((array) $options as $opt): ?>
              <label class="chip-toggle">
                <input type="checkbox" name="<?= kl_h($key) ?>[]" value="<?= kl_h($opt) ?>">
                <span><?= kl_h($opt) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

      <?php elseif ($type === 'select'): ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?><?php if ($required): ?><span class="req">*</span><?php endif; ?></label>
          <select id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>" <?= $required ? 'required' : '' ?>>
            <option value="">בחר/י…</option>
            <?php foreach ((array) $options as $opt): ?>
              <option value="<?= kl_h($opt) ?>"><?= kl_h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      <?php elseif ($type === 'checkbox'): ?>
        <div class="field">
          <label class="field-check">
            <input type="checkbox" id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>">
            <span><?= kl_h($label) ?></span>
          </label>
        </div>

      <?php elseif ($type === 'textarea'): ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?></label>
          <textarea id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>"></textarea>
        </div>

      <?php else:
        $inputType = in_array($type, ['text', 'number', 'date', 'time'], true) ? $type : 'text';
      ?>
        <div class="field">
          <label class="field__label" for="<?= kl_h($key) ?>"><?= kl_h($label) ?><?php if ($required): ?><span class="req">*</span><?php endif; ?></label>
          <input type="<?= $inputType ?>" id="<?= kl_h($key) ?>" name="<?= kl_h($key) ?>" <?= $required ? 'required' : '' ?>
                 <?php if (isset($errors[$key])): ?>aria-invalid="true"<?php endif; ?>>
        </div>
      <?php endif; ?>

    <?php endforeach; ?>

    <div class="submit-bar">
      <div class="submit-bar__inner">
        <button type="submit" class="btn btn-primary">שמור רישום</button>
      </div>
    </div>
  </form>
</main>
<?php
kl_foot();
