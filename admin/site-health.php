<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();
$health = $pdo->query('SELECT * FROM site_health WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

kl_head('זמינות האתר');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>זמינות האתר</h1>
  </div>

  <?php if (!$health || !$health['last_checked_at']): ?>
    <div class="empty">
      עדיין לא בוצעה בדיקת זמינות. יש להגדיר הרצה מתוזמנת (Cron Job) ב-hPanel שמריצה את cron/watcher.php
      כל 5-15 דקות. ראו הוראות ב-README.
    </div>
  <?php else: ?>
    <div class="stat-grid">
      <div class="stat-tile">
        <div class="stat-tile__label">סטטוס</div>
        <div class="stat-tile__value <?= $health['status'] === 'up' ? 'safe' : 'danger' ?>">
          <?= $health['status'] === 'up' ? 'פעיל' : 'לא זמין' ?>
        </div>
      </div>
      <div class="stat-tile">
        <div class="stat-tile__label">בדיקה אחרונה</div>
        <div class="stat-tile__value mono" style="font-size:15px;"><?= kl_h($health['last_checked_at']) ?></div>
      </div>
      <div class="stat-tile">
        <div class="stat-tile__label">שינוי סטטוס אחרון</div>
        <div class="stat-tile__value mono" style="font-size:15px;"><?= kl_h($health['last_status_change_at'] ?? '—') ?></div>
      </div>
    </div>
    <?php if ($health['status'] !== 'up' && $health['last_error']): ?>
      <div class="banner banner--danger">שגיאה אחרונה: <?= kl_h($health['last_error']) ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <p style="color:var(--graphite-600); font-size:13px; margin-top:24px;">
    הבדיקה רצה מתוזמן (cron) ובודקת גישה חיצונית לכתובת <?= kl_h(KL_SITE_URL) ?>/healthcheck.php.
    מנהלים מקבלים מייל אוטומטי כאשר הסטטוס משתנה (נופל או חוזר לפעול).
  </p>
</main>
<?php
kl_foot();
