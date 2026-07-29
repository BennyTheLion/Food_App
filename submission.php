<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();
$pdo = kl_db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, f.name AS form_name, f.category, k.name AS kitchen_name, k.site_id, si.name AS site_name, u.name AS filler_name
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     JOIN kitchens k ON k.id = s.kitchen_id
     JOIN sites si ON si.id = k.site_id
     JOIN users u ON u.id = s.filled_by
     WHERE s.id = :id'
);
$stmt->execute([':id' => $id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission || (!kl_is_admin($user) && (int) $submission['site_id'] !== (int) $user['site_id'])) {
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

/** Shared by the on-screen table, the CSV export, and the admin email so all three always agree. */
function kl_build_submission_rows(array $values, array $fieldMeta): array
{
    $rows = [];
    foreach ($values as $key => $val) {
        $meta = $fieldMeta[$key] ?? null;
        $label = $meta ? $meta['label'] : $key;
        if (in_array($label, ['__units__', '__dishes__', '__workers__'], true)) {
            $label = 'קריאות מפורטות';
        }
        $display = $val;
        if ($meta && $meta['field_type'] === 'checkbox') {
            $display = $val === '1' ? 'כן' : 'לא';
        }
        if ($val !== '' && $val[0] === '[') {
            $decoded = json_decode($val, true);
            if (is_array($decoded) && $decoded) {
                $display = implode(' · ', array_map(function ($row) {
                    return is_array($row) ? implode(' / ', $row) : (string) $row;
                }, $decoded));
            } elseif (is_array($decoded)) {
                $display = '—';
            }
        }
        $rows[] = ['label' => $label, 'value' => (string) $display !== '' ? (string) $display : '—'];
    }
    return $rows;
}

$rows = kl_build_submission_rows($values, $fieldMeta);
$action = $_GET['action'] ?? null;

if ($action === 'export') {
    require __DIR__ . '/inc/export.php';
    kl_export_submission_csv($submission, $rows);
    exit;
}

$emailStatus = null;
if ($action === 'send') {
    require __DIR__ . '/inc/mailer.php';
    $emailStatus = kl_send_submission_email($submission, $rows) ? 'ok' : 'fail';
}

kl_head($submission['form_name']);
kl_topbar(kl_url('dashboard.php'), 'ללוח הבקרה');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow"><?= kl_h($submission['category']) ?></div>
    <h1><?= kl_h($submission['form_name']) ?></h1>
    <p class="mono" style="font-family:var(--font-mono);"><?= kl_h($submission['submitted_at']) ?></p>
  </div>

  <?php if ($emailStatus === 'ok'): ?>
    <p class="badge safe" style="margin-bottom:14px;">נשלח בהצלחה למנהל</p>
  <?php elseif ($emailStatus === 'fail'): ?>
    <p class="badge danger" style="margin-bottom:14px;">שליחת המייל נכשלה — ודא/י שהוגדר מנהל עם כתובת מייל</p>
  <?php endif; ?>

  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px;">
    <a class="btn btn-ghost" href="?id=<?= $id ?>&action=export">ייצוא ל-CSV</a>
    <a class="btn btn-ghost" href="?id=<?= $id ?>&action=send">שליחה למנהל במייל</a>
  </div>

  <div class="table-scroll">
    <table class="log-table">
      <tbody>
        <tr><th>אתר</th><td><?= kl_h($submission['site_name']) ?></td></tr>
        <tr><th>מטבח</th><td><?= kl_h($submission['kitchen_name']) ?></td></tr>
        <tr><th>ממלא</th><td><?= kl_h($submission['filler_name']) ?></td></tr>
        <?php foreach ($rows as $row): ?>
          <tr><th><?= kl_h($row['label']) ?></th><td><?= kl_h($row['value']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
