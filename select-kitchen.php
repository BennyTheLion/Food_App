<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/kitchen_lock.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();

$roomId = (int) ($_GET['room'] ?? 0);
$pdo = kl_db();
$stmt = $pdo->prepare(
    'SELECT dr.*, s.name AS site_name FROM dining_rooms dr JOIN sites s ON s.id = dr.site_id WHERE dr.id = :id'
);
$stmt->execute([':id' => $roomId]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM kitchens WHERE dining_room_id = :room_id ORDER BY name');
$stmt->execute([':room_id' => $roomId]);
$kitchens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deniedBy = null;

if (isset($_GET['pick'])) {
    $pickId = (int) $_GET['pick'];
    foreach ($kitchens as $k) {
        if ((int) $k['id'] === $pickId) {
            $result = kl_try_acquire_kitchen($user, $pickId);
            if ($result['ok']) {
                kl_set_current_kitchen($pickId);
                header('Location: ' . kl_url('index.php'));
                exit;
            }
            $deniedBy = $result['held_by'];
            break;
        }
    }
} elseif (count($kitchens) === 1) {
    $result = kl_try_acquire_kitchen($user, (int) $kitchens[0]['id']);
    if ($result['ok']) {
        kl_set_current_kitchen((int) $kitchens[0]['id']);
        header('Location: ' . kl_url('index.php'));
        exit;
    }
    $deniedBy = $result['held_by'];
}

kl_head('בחירת מטבח');
kl_topbar(kl_url('select-dining-room.php') . '?site=' . (int) $room['site_id'], 'לבחירת חדר אוכל');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow"><?= kl_h($room['site_name']) ?> · <?= kl_h($room['name']) ?></div>
    <h1>בחר/י מטבח</h1>
  </div>

  <?php if ($deniedBy): ?>
    <div class="banner banner--danger">מטבח זה תפוס כרגע על ידי <?= kl_h($deniedBy) ?>. נסה/י שוב מאוחר יותר או בחר/י מטבח אחר.</div>
  <?php endif; ?>

  <?php if (!$kitchens): ?>
    <div class="empty">טרם הוגדרו מטבחים לחדר אוכל זה. פני/ה למנהל המערכת.</div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($kitchens as $k):
        $lock = kl_is_admin($user) ? null : kl_active_lock((int) $k['id']);
        $isSelf = $lock && (int) $lock['user_id'] === (int) $user['id'];
      ?>
        <a class="form-card" href="<?= kl_h(kl_url('select-kitchen.php')) ?>?room=<?= $roomId ?>&pick=<?= (int) $k['id'] ?>">
          <span class="form-card__status <?= (!$lock || $isSelf) ? 'done' : '' ?>"></span>
          <span class="form-card__body">
            <span class="form-card__title"><?= kl_h($k['name']) ?></span>
            <?php if ($lock && !$isSelf): ?>
              <span class="form-card__meta">תפוס על ידי <?= kl_h($lock['user_name']) ?></span>
            <?php endif; ?>
          </span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
kl_foot();
