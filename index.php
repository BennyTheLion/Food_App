<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/kitchen_lock.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();
$kitchen = kl_require_kitchen($user);
if (!$kitchen) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$pdo = kl_db();

$forms = $pdo->query('SELECT * FROM forms WHERE active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$today = (new DateTime())->format('Y-m-d');
$doneToday = [];
$stmt = $pdo->prepare(
    "SELECT DISTINCT form_id FROM submissions WHERE kitchen_id = :kitchen_id AND substr(submitted_at, 1, 10) = :today"
);
$stmt->execute([':kitchen_id' => $kitchen['id'], ':today' => $today]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
    $doneToday[(int) $fid] = true;
}

$grouped = [];
foreach ($forms as $f) {
    $grouped[$f['category']][] = $f;
}

kl_head('רשימת טפסים');
kl_topbar();
kl_context_bar($user, $kitchen);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">בקרת בטיחות מזון</div>
    <h1>אילו בדיקות נותרו היום?</h1>
    <p>טפסים שמולאו היום ב<?= kl_h($kitchen['name']) ?> מסומנים בירוק.</p>
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
        <a class="form-card" href="<?= kl_h(kl_url('form.php')) ?>?id=<?= (int) $f['id'] ?>">
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
    <a class="form-card" href="<?= kl_h(kl_url('dashboard.php')) ?>">
      <span class="form-card__status done"></span>
      <span class="form-card__body">
        <span class="form-card__title">לוח בקרה</span>
        <span class="form-card__meta">סטטיסטיקות ורישומים אחרונים</span>
      </span>
      <span class="form-card__chevron">‹</span>
    </a>
    <?php if (kl_is_admin($user)): ?>
      <a class="form-card" href="<?= kl_h(kl_url('admin/index.php')) ?>">
        <span class="form-card__status done"></span>
        <span class="form-card__body"><span class="form-card__title">פאנל ניהול</span></span>
        <span class="form-card__chevron">‹</span>
      </a>
    <?php endif; ?>
  </div>
</main>
<?php
kl_foot();
