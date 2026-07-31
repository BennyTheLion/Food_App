<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/mailer.php';
require __DIR__ . '/../inc/whatsapp.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'test_alert') {
    $admins = kl_admin_emails();
    $now = (new DateTime())->format('Y-m-d H:i:s');
    $emailOk = kl_send_alert_email(
        '🧪 בדיקת התראה',
        "זוהי הודעת בדיקה ידנית ממערכת ניהול מטבח, לא נובעת משינוי אמיתי בזמינות האתר.\n\nנשלחה על ידי: {$user['name']}\nזמן: $now"
    );
    $testResult = [
        'email_ok' => $emailOk,
        'email_recipients' => $admins,
        'whatsapp_enabled' => kl_whatsapp_enabled(),
        'whatsapp_ok' => kl_whatsapp_enabled() ? kl_send_whatsapp_alert("🧪 בדיקת התראה\n\nהודעת בדיקה ממערכת ניהול מטבח. זמן: $now") : null,
    ];
}

$health = $pdo->query('SELECT * FROM site_health WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$runs = $pdo->query('SELECT * FROM watcher_runs ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);

kl_head('זמינות האתר');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>זמינות האתר</h1>
  </div>

  <?php if ($testResult): ?>
    <?php if ($testResult['email_ok']): ?>
      <div class="banner banner--safe">
        נשלחה הודעת בדיקה בהצלחה אל: <?= kl_h(implode(', ', $testResult['email_recipients'])) ?>
      </div>
    <?php else: ?>
      <div class="banner banner--danger">
        שליחת הודעת הבדיקה נכשלה
        <?= $testResult['email_recipients'] ? '(אל: ' . kl_h(implode(', ', $testResult['email_recipients'])) . ')' : '— אין משתמשים בתפקיד מנהל עם כתובת מייל' ?>.
      </div>
    <?php endif; ?>
    <?php if ($testResult['whatsapp_enabled']): ?>
      <div class="banner <?= $testResult['whatsapp_ok'] ? 'banner--safe' : 'banner--danger' ?>">
        התראת WhatsApp: <?= $testResult['whatsapp_ok'] ? 'נשלחה בהצלחה' : 'נכשלה' ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" style="margin-bottom:18px;">
    <input type="hidden" name="op" value="test_alert">
    <button type="submit" class="btn btn-ghost">שליחת הודעת בדיקה למנהלים</button>
  </form>

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

  <div class="section-label">היסטוריית הרצות (100 אחרונות)</div>
  <div class="table-scroll">
    <table class="log-table">
      <thead><tr><th>מועד</th><th>תוצאה</th><th>משך</th><th>שינוי?</th><th>נשלחה התראה?</th><th>שגיאה</th></tr></thead>
      <tbody>
        <?php foreach ($runs as $run): ?>
          <tr>
            <td class="mono"><?= kl_h($run['ran_at']) ?></td>
            <td><span class="badge <?= $run['status'] === 'up' ? 'safe' : 'danger' ?>"><?= $run['status'] === 'up' ? 'פעיל' : 'לא זמין' ?></span></td>
            <td class="mono"><?= (int) $run['duration_ms'] ?>ms</td>
            <td><?= $run['status_changed'] ? 'כן' : '—' ?></td>
            <td><?= $run['alert_sent'] ? 'כן' : '—' ?></td>
            <td><?= kl_h($run['error'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$runs): ?>
          <tr><td colspan="6"><div class="empty">עדיין לא הורצה בדיקה</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
