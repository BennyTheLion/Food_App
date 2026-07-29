<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/layout.php';

$pdo = kl_db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, f.name AS form_name, f.category FROM submissions s JOIN forms f ON f.id = s.form_id WHERE s.id = :id'
);
$stmt->execute([':id' => $id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    http_response_code(404);
    kl_head('רישום לא נמצא');
    kl_topbar(kl_url('dashboard.php'), 'ללוח הבקרה');
    echo '<main class="container"><div class="empty">הרישום המבוקש לא נמצא.</div></main>';
    kl_foot();
    exit;
}

$labelStmt = $pdo->prepare('SELECT field_key, label, field_type FROM form_fields WHERE form_id = :fid ORDER BY order_index');
$labelStmt->execute([':fid' => $submission['form_id']]);
$fieldMeta = [];
foreach ($labelStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $fieldMeta[$row['field_key']] = $row;
}

$valStmt = $pdo->prepare('SELECT field_key, value FROM submission_values WHERE submission_id = :id');
$valStmt->execute([':id' => $id]);
$values = $valStmt->fetchAll(PDO::FETCH_KEY_PAIR);

kl_head($submission['form_name']);
kl_topbar(kl_url('dashboard.php'), 'ללוח הבקרה');
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow"><?= kl_h($submission['category']) ?></div>
    <h1><?= kl_h($submission['form_name']) ?></h1>
    <p class="mono" style="font-family:var(--font-mono);"><?= kl_h($submission['submitted_at']) ?></p>
  </div>

  <div class="table-scroll">
    <table class="log-table">
      <tbody>
        <tr><th>מטבח / אתר</th><td><?= kl_h($submission['kitchen_name'] ?: '—') ?></td></tr>
        <tr><th>תחנה</th><td><?= kl_h($submission['station_name'] ?: '—') ?></td></tr>
        <tr><th>ממלא</th><td><?= kl_h($submission['filler_name'] ?: '—') ?></td></tr>
        <?php foreach ($values as $key => $val):
          $meta = $fieldMeta[$key] ?? null;
          $label = $meta ? $meta['label'] : $key;
          if (in_array($label, ['__units__', '__dishes__', '__workers__'], true)) {
              $label = 'קריאות מפורטות';
          }
          $display = $val;
          if ($meta && $meta['field_type'] === 'checkbox') {
              $display = $val === '1' ? 'כן' : 'לא';
          }
          if ($val !== '' && $val[0] === '[' ) {
              $decoded = json_decode($val, true);
              if (is_array($decoded) && $decoded) {
                  $display = implode(' · ', array_map(function ($row) {
                      return is_array($row) ? implode(' / ', $row) : (string) $row;
                  }, $decoded));
              } elseif (is_array($decoded)) {
                  $display = '—';
              }
          }
        ?>
          <tr><th><?= kl_h($label) ?></th><td><?= kl_h((string) $display) ?: '—' ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
