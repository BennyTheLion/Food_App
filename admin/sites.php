<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/activity_log.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';
    if ($op === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO sites (name, created_at) VALUES (:name, :created_at)');
            $stmt->execute([':name' => $name, ':created_at' => (new DateTime())->format('Y-m-d H:i:s')]);
            kl_log_activity((int) $user['id'], 'site_created', $name);
        }
    } elseif ($op === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id && $name !== '') {
            $stmt = $pdo->prepare('UPDATE sites SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $id]);
            kl_log_activity((int) $user['id'], 'site_renamed', $name);
        }
    } elseif ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dining_rooms WHERE site_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $error = 'לא ניתן למחוק אתר שיש בו חדרי אוכל — יש למחוק קודם את חדרי האוכל.';
        } else {
            $nameStmt = $pdo->prepare('SELECT name FROM sites WHERE id = :id');
            $nameStmt->execute([':id' => $id]);
            $del = $pdo->prepare('DELETE FROM sites WHERE id = :id');
            $del->execute([':id' => $id]);
            kl_log_activity((int) $user['id'], 'site_deleted', (string) $nameStmt->fetchColumn());
        }
    }
    if (!$error) {
        header('Location: ' . kl_url('admin/sites.php'));
        exit;
    }
}

$sites = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM dining_rooms dr WHERE dr.site_id = s.id) AS room_count
     FROM sites s ORDER BY s.name'
)->fetchAll(PDO::FETCH_ASSOC);

kl_head('אתרים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>אתרים</h1>
  </div>

  <?php if ($error): ?>
    <div class="banner banner--danger"><?= kl_h($error) ?></div>
  <?php endif; ?>

  <div class="card-list">
    <?php foreach ($sites as $site): ?>
      <div class="admin-row">
        <form method="post" class="admin-row__fields">
          <input type="hidden" name="op" value="rename">
          <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
          <input type="text" name="name" value="<?= kl_h($site['name']) ?>">
          <button type="submit" class="btn btn-ghost">שמירה</button>
        </form>
        <span class="admin-row__meta"><?= (int) $site['room_count'] ?> חדרי אוכל</span>
        <form method="post" class="admin-row__actions" onsubmit="return confirm('למחוק את האתר?');">
          <input type="hidden" name="op" value="delete">
          <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
          <button type="submit" class="row-item__remove">×</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$sites): ?>
      <div class="empty">אין אתרים עדיין</div>
    <?php endif; ?>
  </div>

  <div class="section-label">הוספת אתר</div>
  <form method="post" class="admin-add">
    <input type="hidden" name="op" value="create">
    <input type="text" name="name" placeholder="שם האתר, לדוגמה: מגדל העמק" required>
    <button type="submit" class="btn btn-primary">הוספה</button>
  </form>
</main>
<?php
kl_foot();
