<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/kitchen_lock.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM date_open_requests WHERE status = 'pending'")->fetchColumn();
$activeLockCount = count(kl_all_active_locks());

kl_head('פאנל ניהול');
kl_topbar(kl_url('select-site.php'), 'לדף הבית');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>פאנל ניהול</h1>
  </div>

  <div class="card-list">
    <a class="form-card" href="<?= kl_h(kl_url('admin/sites.php')) ?>">
      <span class="form-card__body"><span class="form-card__title">אתרים</span><span class="form-card__meta">הוספה, עריכה ומחיקה</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/dining-rooms.php')) ?>">
      <span class="form-card__body"><span class="form-card__title">חדרי אוכל</span><span class="form-card__meta">שיוך חדרי אוכל לאתרים</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/kitchens.php')) ?>">
      <span class="form-card__body"><span class="form-card__title">מטבחים</span><span class="form-card__meta">שיוך מטבחים לחדרי אוכל</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/users.php')) ?>">
      <span class="form-card__body"><span class="form-card__title">משתמשים</span><span class="form-card__meta">הרשאות מערכת</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/date-requests.php')) ?>">
      <span class="form-card__status <?= $pendingCount > 0 ? '' : 'done' ?>"></span>
      <span class="form-card__body"><span class="form-card__title">בקשות פתיחת תאריך</span><span class="form-card__meta"><?= $pendingCount ?> ממתינות</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/kitchen-locks.php')) ?>">
      <span class="form-card__status done"></span>
      <span class="form-card__body"><span class="form-card__title">מטבחים מחוברים</span><span class="form-card__meta"><?= $activeLockCount ?> מחוברים כרגע · ניתוק ידני</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
    <a class="form-card" href="<?= kl_h(kl_url('admin/connection-log.php')) ?>">
      <span class="form-card__status done"></span>
      <span class="form-card__body"><span class="form-card__title">יומן חיבורים</span><span class="form-card__meta">היסטוריית התחברויות עם חותמות זמן</span></span>
      <span class="form-card__chevron">‹</span>
    </a>
  </div>
</main>
<?php
kl_foot();
