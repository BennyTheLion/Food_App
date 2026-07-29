<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();

$siteId = (int) ($_GET['site'] ?? 0);
$pdo = kl_db();
$stmt = $pdo->prepare('SELECT * FROM sites WHERE id = :id');
$stmt->execute([':id' => $siteId]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$rooms = kl_dining_rooms_for_site($siteId);

if (count($rooms) === 1) {
    header('Location: ' . kl_url('select-kitchen.php') . '?room=' . $rooms[0]['id']);
    exit;
}

kl_head('בחירת חדר אוכל');
kl_topbar(kl_url('select-site.php'), 'לבחירת אתר');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow"><?= kl_h($site['name']) ?></div>
    <h1>בחר/י חדר אוכל</h1>
  </div>

  <?php if (!$rooms): ?>
    <div class="empty">טרם הוגדרו חדרי אוכל לאתר זה. פני/ה למנהל המערכת.</div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($rooms as $room): ?>
        <a class="form-card" href="<?= kl_h(kl_url('select-kitchen.php')) ?>?room=<?= (int) $room['id'] ?>">
          <span class="form-card__body"><span class="form-card__title"><?= kl_h($room['name']) ?></span></span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
kl_foot();
