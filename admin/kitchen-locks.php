<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/kitchen_lock.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'disconnect') {
    $kitchenId = (int) ($_POST['kitchen_id'] ?? 0);
    if ($kitchenId) {
        kl_force_release_kitchen($kitchenId);
    }
    header('Location: ' . kl_url('admin/kitchen-locks.php'));
    exit;
}

$locks = kl_all_active_locks();

kl_head('מטבחים מחוברים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>מטבחים מחוברים כרגע</h1>
    <p>ניתוק משתמש משחרר את המטבח באופן מיידי כדי שמשתמש אחר יוכל להיכנס אליו. <a href="<?= kl_h(kl_url('admin/connection-log.php')) ?>">יומן חיבורים מלא ‹</a></p>
  </div>

  <div class="card-list">
    <?php foreach ($locks as $l): ?>
      <div class="admin-row">
        <span class="admin-row__fields">
          <strong><?= kl_h($l['user_name']) ?></strong>
          <span class="admin-row__meta">
            <?= kl_h($l['site_name']) ?> — <?= kl_h($l['room_name']) ?> — <?= kl_h($l['kitchen_name']) ?>
            · מחובר/ת מאז <?= kl_h($l['locked_at']) ?>
          </span>
        </span>
        <form method="post" class="admin-row__actions" onsubmit="return confirm('לנתק את <?= kl_h(addslashes($l['user_name'])) ?> מהמטבח?');">
          <input type="hidden" name="op" value="disconnect">
          <input type="hidden" name="kitchen_id" value="<?= (int) $l['kitchen_id'] ?>">
          <button type="submit" class="btn btn-ghost btn-ghost--danger">ניתוק</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$locks): ?>
      <div class="empty">אין כרגע משתמשים מחוברים לאף מטבח</div>
    <?php endif; ?>
  </div>
</main>
<?php
kl_foot();
