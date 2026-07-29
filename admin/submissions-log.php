<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();

$submissions = $pdo->query(
    'SELECT s.id, s.submitted_at, f.name AS form_name, u.name AS filler_name,
            si.name AS site_name, dr.name AS room_name, k.name AS kitchen_name
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     JOIN users u ON u.id = s.filled_by
     JOIN kitchens k ON k.id = s.kitchen_id
     JOIN dining_rooms dr ON dr.id = k.dining_room_id
     JOIN sites si ON si.id = dr.site_id
     ORDER BY s.id DESC LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

kl_head('יומן טפסים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>יומן טפסים — כל האתרים</h1>
    <p>200 הטפסים האחרונים שמולאו בכל האתרים והמטבחים, מהחדש לישן.</p>
  </div>

  <div class="table-scroll">
    <table class="log-table">
      <thead><tr><th>טופס</th><th>ממלא</th><th>אתר — חדר אוכל — מטבח</th><th>מועד</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($submissions as $s): ?>
          <tr>
            <td><?= kl_h($s['form_name']) ?></td>
            <td><?= kl_h($s['filler_name']) ?></td>
            <td><?= kl_h($s['site_name']) ?> — <?= kl_h($s['room_name']) ?> — <?= kl_h($s['kitchen_name']) ?></td>
            <td class="mono"><?= kl_h($s['submitted_at']) ?></td>
            <td><a href="<?= kl_h(kl_url('submission.php')) ?>?id=<?= (int) $s['id'] ?>">פרטים ‹</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$submissions): ?>
          <tr><td colspan="5"><div class="empty">אין עדיין רישומים</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php
kl_foot();
