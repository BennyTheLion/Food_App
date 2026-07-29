<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/kitchen_lock.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();
kl_release_user_locks((int) $user['id']); // leaving whatever kitchen they were in, if any
kl_clear_current_kitchen();
$sites = kl_accessible_sites($user);

if (count($sites) === 1) {
    header('Location: ' . kl_url('select-dining-room.php') . '?site=' . $sites[0]['id']);
    exit;
}

kl_head('בחירת אתר');
kl_topbar();
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">שלום, <?= kl_h($user['name']) ?></div>
    <h1>בחר/י אתר</h1>
  </div>

  <?php if (!$sites): ?>
    <div class="empty">
      טרם הוגדרו אתרים במערכת. פני/ה למנהל המערכת.
    </div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($sites as $site): ?>
        <a class="form-card" href="<?= kl_h(kl_url('select-dining-room.php')) ?>?site=<?= (int) $site['id'] ?>">
          <span class="form-card__body">
            <span class="form-card__title"><?= kl_h($site['name']) ?></span>
          </span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (kl_is_admin($user)): ?>
    <div class="section-label">ניהול</div>
    <div class="card-list">
      <a class="form-card" href="<?= kl_h(kl_url('admin/index.php')) ?>">
        <span class="form-card__body"><span class="form-card__title">פאנל ניהול</span></span>
        <span class="form-card__chevron">‹</span>
      </a>
    </div>
  <?php endif; ?>
</main>
<?php
kl_foot();
