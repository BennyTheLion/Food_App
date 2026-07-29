<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/layout.php';

$pdo = kl_db();
$formId = (int) ($_GET['form'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forms WHERE id = :id');
$stmt->execute([':id' => $formId]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

kl_head('נשמר בהצלחה');
kl_topbar('/Food_App/kitchen-log-app/index.php', 'לרשימת הטפסים');
?>
<main class="container">
  <div class="success-card">
    <div class="success-check">✓</div>
    <h1>הרישום נשמר</h1>
    <p style="color:var(--graphite-600); margin-top:8px;">
      <?= $form ? kl_h($form['name']) . ' נשמר בהצלחה.' : 'הטופס נשמר בהצלחה.' ?>
    </p>
    <div style="margin-top:28px; display:flex; flex-direction:column; gap:10px; align-items:center;">
      <?php if ($form): ?>
        <a class="btn btn-primary" href="/Food_App/kitchen-log-app/form.php?id=<?= (int) $form['id'] ?>">מילוי נוסף של אותו טופס</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="/Food_App/kitchen-log-app/index.php">חזרה לרשימת הטפסים</a>
    </div>
  </div>
</main>
<?php
kl_foot();
