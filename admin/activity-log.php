<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/activity_log.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$log = kl_activity_log(200);

$actionLabel = [
    'login' => 'התחברות',
    'site_created' => 'אתר נוצר',
    'site_renamed' => 'אתר עודכן',
    'site_deleted' => 'אתר נמחק',
    'dining_room_created' => 'חדר אוכל נוצר',
    'dining_room_updated' => 'חדר אוכל עודכן',
    'dining_room_deleted' => 'חדר אוכל נמחק',
    'kitchen_created' => 'מטבח נוצר',
    'kitchen_updated' => 'מטבח עודכן',
    'kitchen_deleted' => 'מטבח נמחק',
    'user_created' => 'משתמש נוצר',
    'user_role_updated' => 'הרשאת משתמש עודכנה',
    'user_password_reset' => 'סיסמת משתמש אופסה',
    'user_deleted' => 'משתמש נמחק',
    'date_request_approved' => 'בקשת תאריך אושרה',
    'date_request_denied' => 'בקשת תאריך נדחתה',
];

kl_head('יומן פעולות');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>יומן פעולות מנהל</h1>
    <p>200 הפעולות האחרונות: התחברויות ופעולות ניהול (יצירה, עריכה, מחיקה), מהחדש לישן.</p>
  </div>

  <div class="table-scroll">
    <table class="log-table">
      <thead><tr><th>משתמש</th><th>פעולה</th><th>פרטים</th><th>מועד</th></tr></thead>
      <tbody>
        <?php foreach ($log as $entry): ?>
          <tr>
            <td><?= kl_h($entry['user_name'] ?? '—') ?></td>
            <td><?= kl_h($actionLabel[$entry['action']] ?? $entry['action']) ?></td>
            <td><?= kl_h($entry['details'] ?? '') ?></td>
            <td class="mono"><?= kl_h($entry['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$log): ?>
          <tr><td colspan="4"><div class="empty">אין עדיין רישומי פעולות</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
