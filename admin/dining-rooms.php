<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';
    if ($op === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $siteId = (int) ($_POST['site_id'] ?? 0);
        if ($name !== '' && $siteId) {
            $stmt = $pdo->prepare('INSERT INTO dining_rooms (site_id, name, created_at) VALUES (:site_id, :name, :created_at)');
            $stmt->execute([':site_id' => $siteId, ':name' => $name, ':created_at' => (new DateTime())->format('Y-m-d H:i:s')]);
        }
    } elseif ($op === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $siteId = (int) ($_POST['site_id'] ?? 0);
        if ($id && $name !== '' && $siteId) {
            $stmt = $pdo->prepare('UPDATE dining_rooms SET name = :name, site_id = :site_id WHERE id = :id');
            $stmt->execute([':name' => $name, ':site_id' => $siteId, ':id' => $id]);
        }
    } elseif ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM kitchens WHERE dining_room_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $error = 'לא ניתן למחוק חדר אוכל שיש בו מטבחים — יש למחוק קודם את המטבחים.';
        } else {
            $del = $pdo->prepare('DELETE FROM dining_rooms WHERE id = :id');
            $del->execute([':id' => $id]);
        }
    }
    if (!$error) {
        header('Location: ' . kl_url('admin/dining-rooms.php'));
        exit;
    }
}

$sites = $pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query(
    'SELECT dr.*, s.name AS site_name, (SELECT COUNT(*) FROM kitchens k WHERE k.dining_room_id = dr.id) AS kitchen_count
     FROM dining_rooms dr JOIN sites s ON s.id = dr.site_id ORDER BY s.name, dr.name'
)->fetchAll(PDO::FETCH_ASSOC);

kl_head('חדרי אוכל');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>חדרי אוכל</h1>
  </div>

  <?php if ($error): ?>
    <div class="banner banner--danger"><?= kl_h($error) ?></div>
  <?php endif; ?>

  <?php if (!$sites): ?>
    <div class="empty">יש ליצור אתר קודם ב<a href="<?= kl_h(kl_url('admin/sites.php')) ?>">ניהול אתרים</a>.</div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($rooms as $room): ?>
        <div class="admin-row">
          <form method="post" class="admin-row__fields">
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
            <input type="text" name="name" value="<?= kl_h($room['name']) ?>">
            <select name="site_id">
              <?php foreach ($sites as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $room['site_id'] ? 'selected' : '' ?>><?= kl_h($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost">שמירה</button>
          </form>
          <span class="admin-row__meta"><?= (int) $room['kitchen_count'] ?> מטבחים</span>
          <form method="post" class="admin-row__actions" onsubmit="return confirm('למחוק את חדר האוכל?');">
            <input type="hidden" name="op" value="delete">
            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
            <button type="submit" class="row-item__remove">×</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$rooms): ?>
        <div class="empty">אין חדרי אוכל עדיין</div>
      <?php endif; ?>
    </div>

    <div class="section-label">הוספת חדר אוכל</div>
    <form method="post" class="admin-add">
      <input type="hidden" name="op" value="create">
      <input type="text" name="name" placeholder="שם חדר האוכל" required>
      <select name="site_id" required>
        <option value="">בחר/י אתר…</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= (int) $s['id'] ?>"><?= kl_h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">הוספה</button>
    </form>
  <?php endif; ?>
</main>
<?php
kl_foot();
