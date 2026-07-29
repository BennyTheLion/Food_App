<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/layout.php';

$pdo = kl_db();

$forms = $pdo->query('SELECT * FROM forms WHERE active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$today = (new DateTime())->format('Y-m-d');
$doneToday = [];
$stmt = $pdo->prepare("SELECT DISTINCT form_id FROM submissions WHERE substr(submitted_at, 1, 10) = :today");
$stmt->execute([':today' => $today]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
    $doneToday[(int) $fid] = true;
}

$grouped = [];
foreach ($forms as $f) {
    $grouped[$f['category']][] = $f;
}

kl_head('רשימת טפסים');
kl_topbar();
kl_station_bar();
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">בקרת בטיחות מזון</div>
    <h1>אילו בדיקות נותרו היום?</h1>
    <p>בחר/י תחנה למעלה, ואז טופס לביצוע. טפסים שמולאו היום מסומנים בירוק.</p>
  </div>

  <?php foreach ($grouped as $category => $items): ?>
    <div class="section-label"><?= kl_h($category) ?></div>
    <div class="card-list">
      <?php foreach ($items as $f): ?>
        <?php
          $code = null;
          if (preg_match('/R-\d{3}-\d{2}/', $f['description'], $m)) {
              $code = $m[0];
          }
          $isDone = !empty($doneToday[(int) $f['id']]);
        ?>
        <a class="form-card" href="/Food_App/kitchen-log-app/form.php?id=<?= (int) $f['id'] ?>">
          <span class="form-card__status <?= $isDone ? 'done' : '' ?>"></span>
          <span class="form-card__body">
            <span class="form-card__title"><?= kl_h($f['name']) ?></span>
            <span class="form-card__meta">
              <?php if ($code): ?><span class="form-card__code"><?= kl_h($code) ?></span><?php endif; ?>
              <?= $isDone ? 'בוצע היום' : 'טרם בוצע היום' ?>
            </span>
          </span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="section-label">ניהול</div>
  <div class="card-list">
    <a class="form-card" href="/Food_App/kitchen-log-app/dashboard.php">
      <span class="form-card__status done"></span>
      <span class="form-card__body">
        <span class="form-card__title">לוח בקרה למנהל</span>
        <span class="form-card__meta">סטטיסטיקות ורישומים אחרונים</span>
      </span>
      <span class="form-card__chevron">‹</span>
    </a>
  </div>
</main>
<?php
kl_foot();
