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
    try {
        if ($op === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $roomId = (int) ($_POST['dining_room_id'] ?? 0);
            if ($name !== '' && $roomId) {
                $stmt = $pdo->prepare('INSERT INTO kitchens (dining_room_id, name, created_at) VALUES (:room_id, :name, :created_at)');
                $stmt->execute([':room_id' => $roomId, ':name' => $name, ':created_at' => (new DateTime())->format('Y-m-d H:i:s')]);
                kl_log_activity((int) $user['id'], 'kitchen_created', $name);
            }
        } elseif ($op === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $roomId = (int) ($_POST['dining_room_id'] ?? 0);
            if ($id && $name !== '' && $roomId) {
                $stmt = $pdo->prepare('UPDATE kitchens SET name = :name, dining_room_id = :room_id WHERE id = :id');
                $stmt->execute([':name' => $name, ':room_id' => $roomId, ':id' => $id]);
                kl_log_activity((int) $user['id'], 'kitchen_updated', $name);
            }
        } elseif ($op === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE kitchen_id = :id');
            $countStmt->execute([':id' => $id]);
            $submissionCount = (int) $countStmt->fetchColumn();

            if ($submissionCount > 0) {
                $error = 'לא ניתן למחוק מטבח שיש בו רישומי טפסים — הנתונים חייבים להישמר.';
            } else {
                // Connection log / date-request history is just operational
                // log data tied to this kitchen's existence, not a
                // compliance record — safe to clear along with the kitchen.
                $pdo->prepare('DELETE FROM kitchen_locks WHERE kitchen_id = :id')->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM kitchen_connection_log WHERE kitchen_id = :id')->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM date_open_requests WHERE kitchen_id = :id')->execute([':id' => $id]);
                $nameStmt = $pdo->prepare('SELECT name FROM kitchens WHERE id = :id');
                $nameStmt->execute([':id' => $id]);
                $name = (string) $nameStmt->fetchColumn();
                $stmt = $pdo->prepare('DELETE FROM kitchens WHERE id = :id');
                $stmt->execute([':id' => $id]);
                kl_log_activity((int) $user['id'], 'kitchen_deleted', $name);
            }
        }
    } catch (PDOException $e) {
        $error = 'שגיאה בשמירה: ' . $e->getMessage();
    }
    if (!$error) {
        header('Location: ' . kl_url('admin/kitchens.php'));
        exit;
    }
}

$rooms = $pdo->query(
    'SELECT dr.*, s.name AS site_name FROM dining_rooms dr JOIN sites s ON s.id = dr.site_id ORDER BY s.name, dr.name'
)->fetchAll(PDO::FETCH_ASSOC);
$kitchens = $pdo->query(
    'SELECT k.*, dr.name AS room_name, dr.site_id AS site_id, s.name AS site_name
     FROM kitchens k
     JOIN dining_rooms dr ON dr.id = k.dining_room_id
     JOIN sites s ON s.id = dr.site_id
     ORDER BY s.name, dr.name, k.name'
)->fetchAll(PDO::FETCH_ASSOC);

kl_head('מטבחים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>מטבחים</h1>
  </div>

  <?php if ($error): ?>
    <div class="banner banner--danger"><?= kl_h($error) ?></div>
  <?php endif; ?>

  <?php if (!$rooms): ?>
    <div class="empty">יש ליצור חדר אוכל קודם ב<a href="<?= kl_h(kl_url('admin/dining-rooms.php')) ?>">ניהול חדרי אוכל</a>.</div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($kitchens as $k): ?>
        <div class="admin-row">
          <form method="post" class="admin-row__fields">
            <input type="hidden" name="op" value="update">
            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <input type="text" name="name" value="<?= kl_h($k['name']) ?>">
            <select name="dining_room_id">
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === (int) $k['dining_room_id'] ? 'selected' : '' ?>>
                  <?= kl_h($r['site_name']) ?> — <?= kl_h($r['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-ghost">שמירה</button>
          </form>
          <span class="admin-row__meta"><?= kl_h($k['site_name']) ?> · <?= kl_h($k['room_name']) ?></span>
          <form method="post" class="admin-row__actions" onsubmit="return confirm('למחוק את המטבח?');">
            <input type="hidden" name="op" value="delete">
            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <button type="submit" class="row-item__remove">×</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$kitchens): ?>
        <div class="empty">אין מטבחים עדיין</div>
      <?php endif; ?>
    </div>

    <div class="section-label">הוספת מטבח</div>
    <form method="post" class="admin-add">
      <input type="hidden" name="op" value="create">
      <input type="text" name="name" placeholder="שם המטבח" required>
      <select name="dining_room_id" required>
        <option value="">בחר/י חדר אוכל…</option>
        <?php foreach ($rooms as $r): ?>
          <option value="<?= (int) $r['id'] ?>"><?= kl_h($r['site_name']) ?> — <?= kl_h($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">הוספה</button>
    </form>
  <?php endif; ?>
</main>
<?php
kl_foot();
