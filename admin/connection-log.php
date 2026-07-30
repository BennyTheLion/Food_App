<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/kitchen_lock.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$log = kl_connection_log(200);

$reasonLabel = [
    'exit' => 'יציאה עצמית',
    'admin_disconnect' => 'נותק ע"י מנהל',
];

kl_head('יומן חיבורים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>יומן חיבורים למטבחים</h1>
    <p>200 החיבורים האחרונים, מהחדש לישן. <a href="<?= kl_h(kl_url('admin/kitchen-locks.php')) ?>">מטבחים מחוברים כרגע ‹</a></p>
  </div>

  <div class="table-scroll">
    <table class="log-table">
      <thead><tr><th>משתמש</th><th>אתר — חדר אוכל — מטבח</th><th>התחברות</th><th>ניתוק</th></tr></thead>
      <tbody>
        <?php foreach ($log as $entry): ?>
          <tr>
            <td><?= kl_h($entry['user_name']) ?></td>
            <td><?= kl_location_breadcrumb($entry['site_name'], $entry['room_name'], $entry['kitchen_name']) ?></td>
            <td class="mono"><?= kl_h($entry['connected_at']) ?></td>
            <td class="mono">
              <?php if ($entry['disconnected_at']): ?>
                <?= kl_h($entry['disconnected_at']) ?>
                <span class="badge neutral"><?= kl_h($reasonLabel[$entry['disconnected_reason']] ?? $entry['disconnected_reason']) ?></span>
              <?php else: ?>
                <span class="badge safe">מחובר/ת כרגע</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$log): ?>
          <tr><td colspan="4"><div class="empty">אין עדיין רישומי חיבור</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
