<?php
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/layout.php';

$user = kl_require_admin();
$pdo = kl_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if ($id && in_array($decision, ['approved', 'denied'], true)) {
        $stmt = $pdo->prepare(
            'UPDATE date_open_requests SET status = :status, decided_by = :decided_by, decided_at = :decided_at WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $decision,
            ':decided_by' => $user['id'],
            ':decided_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }
    header('Location: ' . kl_url('admin/date-requests.php'));
    exit;
}

$requests = $pdo->query(
    "SELECT r.*, k.name AS kitchen_name, s.name AS site_name, f.name AS form_name, u.name AS requested_by_name
     FROM date_open_requests r
     JOIN kitchens k ON k.id = r.kitchen_id
     JOIN sites s ON s.id = k.site_id
     JOIN forms f ON f.id = r.form_id
     JOIN users u ON u.id = r.requested_by
     ORDER BY (r.status = 'pending') DESC, r.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

kl_head('בקשות פתיחת תאריך');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);

$statusLabel = ['pending' => 'ממתין', 'approved' => 'אושר', 'denied' => 'נדחה'];
$statusClass = ['pending' => 'neutral', 'approved' => 'safe', 'denied' => 'danger'];
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>בקשות פתיחת תאריך</h1>
  </div>

  <div class="card-list">
    <?php foreach ($requests as $r): ?>
      <div class="form-card" style="align-items:flex-start; flex-direction:column; gap:8px;">
        <span class="form-card__body" style="width:100%;">
          <span class="form-card__title"><?= kl_h($r['requested_by_name']) ?> מבקש/ת <?= kl_h($r['requested_date']) ?></span>
          <span class="form-card__meta">
            <span class="badge <?= $statusClass[$r['status']] ?>"><?= $statusLabel[$r['status']] ?></span>
            <?= kl_h($r['form_name']) ?> · <?= kl_h($r['site_name']) ?> — <?= kl_h($r['kitchen_name']) ?>
          </span>
          <span class="form-card__meta"><?= kl_h($r['reason']) ?></span>
        </span>
        <?php if ($r['status'] === 'pending'): ?>
          <div style="display:flex; gap:8px;">
            <form method="post"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="approved">
              <button type="submit" class="btn btn-primary" style="padding:8px 16px; min-height:auto;">אישור</button>
            </form>
            <form method="post"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="denied">
              <button type="submit" class="btn btn-ghost" style="padding:8px 16px; min-height:auto;">דחייה</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$requests): ?>
      <div class="empty">אין בקשות</div>
    <?php endif; ?>
  </div>
</main>
<?php
kl_foot();
