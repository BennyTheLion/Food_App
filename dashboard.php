<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/layout.php';

$pdo = kl_db();
$today = (new DateTime())->format('Y-m-d');

$totalForms = (int) $pdo->query('SELECT COUNT(*) FROM forms WHERE active = 1')->fetchColumn();

$doneTodayStmt = $pdo->prepare("SELECT COUNT(DISTINCT form_id) FROM submissions WHERE substr(submitted_at, 1, 10) = :today");
$doneTodayStmt->execute([':today' => $today]);
$doneTodayCount = (int) $doneTodayStmt->fetchColumn();

$submissionsTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE substr(submitted_at, 1, 10) = :today");
$submissionsTodayStmt->execute([':today' => $today]);
$submissionsToday = (int) $submissionsTodayStmt->fetchColumn();

$totalSubmissions = (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();

$recent = $pdo->query(
    'SELECT s.id, s.submitted_at, s.filler_name, s.station_name, s.kitchen_name, f.name AS form_name, f.category
     FROM submissions s JOIN forms f ON f.id = s.form_id
     ORDER BY s.id DESC LIMIT 25'
)->fetchAll(PDO::FETCH_ASSOC);

$missingStmt = $pdo->query(
    "SELECT f.id, f.name FROM forms f WHERE f.active = 1 AND f.id NOT IN (
        SELECT form_id FROM submissions WHERE substr(submitted_at, 1, 10) = '" . $today . "'
     ) ORDER BY f.id"
);
$missing = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

kl_head('לוח בקרה');
kl_topbar(kl_url('index.php'), 'לרשימת הטפסים');
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">לוח בקרה למנהל</div>
    <h1>מצב התאמה — <?= kl_h((new DateTime())->format('d.m.Y')) ?></h1>
  </div>

  <div class="stat-grid">
    <div class="stat-tile">
      <div class="stat-tile__label">טפסים שמולאו היום</div>
      <div class="stat-tile__value <?= $doneTodayCount === $totalForms ? 'safe' : ($doneTodayCount === 0 ? 'danger' : '') ?>"><?= $doneTodayCount ?>/<?= $totalForms ?></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile__label">רישומים היום</div>
      <div class="stat-tile__value"><?= $submissionsToday ?></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile__label">טפסים חסרים היום</div>
      <div class="stat-tile__value <?= count($missing) > 0 ? 'danger' : 'safe' ?>"><?= count($missing) ?></div>
    </div>
    <div class="stat-tile">
      <div class="stat-tile__label">סה״כ רישומים</div>
      <div class="stat-tile__value"><?= $totalSubmissions ?></div>
    </div>
  </div>

  <?php if ($missing): ?>
    <div class="section-label">טרם בוצעו היום</div>
    <div class="card-list">
      <?php foreach ($missing as $f): ?>
        <a class="form-card" href="<?= kl_h(kl_url('form.php')) ?>?id=<?= (int) $f['id'] ?>">
          <span class="form-card__status"></span>
          <span class="form-card__body"><span class="form-card__title"><?= kl_h($f['name']) ?></span></span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="section-label">רישומים אחרונים</div>
  <div class="table-scroll">
    <table class="log-table">
      <thead>
        <tr><th>טופס</th><th>תחנה</th><th>ממלא</th><th>מועד</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= kl_h($r['form_name']) ?></td>
            <td><?= kl_h($r['station_name'] ?: '—') ?></td>
            <td><?= kl_h($r['filler_name'] ?: '—') ?></td>
            <td class="mono"><?= kl_h($r['submitted_at']) ?></td>
            <td><a href="<?= kl_h(kl_url('submission.php')) ?>?id=<?= (int) $r['id'] ?>">פרטים ‹</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
          <tr><td colspan="5"><div class="empty">אין רישומים עדיין</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
