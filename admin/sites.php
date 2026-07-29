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
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO sites (name, created_at) VALUES (:name, :created_at)');
            $stmt->execute([':name' => $name, ':created_at' => (new DateTime())->format('Y-m-d H:i:s')]);
        }
    } elseif ($op === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id && $name !== '') {
            $stmt = $pdo->prepare('UPDATE sites SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $id]);
        }
    } elseif ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM kitchens WHERE site_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $error = 'לא ניתן למחוק אתר שיש בו מטבחים — יש למחוק קודם את המטבחים.';
        } else {
            $del = $pdo->prepare('DELETE FROM sites WHERE id = :id');
            $del->execute([':id' => $id]);
        }
    }
    if (!$error) {
        header('Location: ' . kl_url('admin/sites.php'));
        exit;
    }
}

$sites = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM kitchens k WHERE k.site_id = s.id) AS kitchen_count
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
    <div class="field" style="background:var(--danger-100); color:var(--danger-600); border-radius:var(--radius-control); padding:12px 14px; font-weight:600;"><?= kl_h($error) ?></div>
  <?php endif; ?>

  <div class="table-scroll">
    <table class="log-table">
      <thead><tr><th>שם האתר</th><th>מטבחים</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($sites as $site): ?>
          <tr>
            <td>
              <form method="post" style="display:flex; gap:8px; align-items:center;">
                <input type="hidden" name="op" value="rename">
                <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
                <input type="text" name="name" value="<?= kl_h($site['name']) ?>" style="border:1px solid var(--steel-300); border-radius:8px; padding:6px 10px;">
                <button type="submit" class="btn btn-ghost" style="padding:6px 12px; min-height:auto;">שמירה</button>
              </form>
            </td>
            <td><?= (int) $site['kitchen_count'] ?></td>
            <td>
              <form method="post" onsubmit="return confirm('למחוק את האתר?');">
                <input type="hidden" name="op" value="delete">
                <input type="hidden" name="id" value="<?= (int) $site['id'] ?>">
                <button type="submit" class="row-item__remove">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$sites): ?>
          <tr><td colspan="3"><div class="empty">אין אתרים עדיין</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="section-label">הוספת אתר</div>
  <form method="post" style="display:flex; gap:10px; flex-wrap:wrap;">
    <input type="hidden" name="op" value="create">
    <input type="text" name="name" placeholder="שם האתר, לדוגמה: מגדל העמק" required
           style="flex:1; min-width:200px; border:1px solid var(--steel-300); border-radius:var(--radius-control); padding:12px 14px;">
    <button type="submit" class="btn btn-primary">הוספה</button>
  </form>
</main>
<?php
kl_foot();
