<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';
    if ($op === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $siteId = (int) ($_POST['site_id'] ?? 0);
        if ($name !== '' && $siteId) {
            $stmt = $pdo->prepare('INSERT INTO kitchens (site_id, name, created_at) VALUES (:site_id, :name, :created_at)');
            $stmt->execute([':site_id' => $siteId, ':name' => $name, ':created_at' => (new DateTime())->format('Y-m-d H:i:s')]);
        }
    } elseif ($op === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $siteId = (int) ($_POST['site_id'] ?? 0);
        if ($id && $name !== '' && $siteId) {
            $stmt = $pdo->prepare('UPDATE kitchens SET name = :name, site_id = :site_id WHERE id = :id');
            $stmt->execute([':name' => $name, ':site_id' => $siteId, ':id' => $id]);
        }
    } elseif ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM kitchens WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
    header('Location: ' . kl_url('admin/kitchens.php'));
    exit;
}

$sites = $pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$kitchens = $pdo->query(
    'SELECT k.*, s.name AS site_name FROM kitchens k JOIN sites s ON s.id = k.site_id ORDER BY s.name, k.name'
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

  <?php if (!$sites): ?>
    <div class="empty">יש ליצור אתר קודם ב<a href="<?= kl_h(kl_url('admin/sites.php')) ?>">ניהול אתרים</a>.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="log-table">
        <thead><tr><th>שם המטבח</th><th>אתר</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($kitchens as $k): ?>
            <tr>
              <td>
                <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                  <input type="hidden" name="op" value="update">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <input type="text" name="name" value="<?= kl_h($k['name']) ?>" style="border:1px solid var(--steel-300); border-radius:8px; padding:6px 10px;">
                  <select name="site_id" style="border:1px solid var(--steel-300); border-radius:8px; padding:6px 10px;">
                    <?php foreach ($sites as $s): ?>
                      <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $k['site_id'] ? 'selected' : '' ?>><?= kl_h($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-ghost" style="padding:6px 12px; min-height:auto;">שמירה</button>
                </form>
              </td>
              <td><?= kl_h($k['site_name']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('למחוק את המטבח?');">
                  <input type="hidden" name="op" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button type="submit" class="row-item__remove">×</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$kitchens): ?>
            <tr><td colspan="3"><div class="empty">אין מטבחים עדיין</div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="section-label">הוספת מטבח</div>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap;">
      <input type="hidden" name="op" value="create">
      <input type="text" name="name" placeholder="שם המטבח, לדוגמה: מטבח ראשי (לוין)" required
             style="flex:1; min-width:200px; border:1px solid var(--steel-300); border-radius:var(--radius-control); padding:12px 14px;">
      <select name="site_id" required style="border:1px solid var(--steel-300); border-radius:var(--radius-control); padding:12px 14px;">
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
