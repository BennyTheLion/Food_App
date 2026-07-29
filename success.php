<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();
$pdo = kl_db();
$formId = (int) ($_GET['form'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forms WHERE id = :id');
$stmt->execute([':id' => $formId]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

kl_head('נשמר בהצלחה');
kl_topbar(kl_url('index.php'), 'לרשימת הטפסים');
?>
<main class="container">
  <div class="success-card">
    <div class="success-check">✓</div>
    <h1>הרישום נשמר</h1>
    <p>
      <?= $form ? kl_h($form['name']) . ' נשמר בהצלחה.' : 'הטופס נשמר בהצלחה.' ?>
    </p>
    <div class="success-card__actions">
      <?php if ($form): ?>
        <a class="btn btn-primary" href="<?= kl_h(kl_url('form.php')) ?>?id=<?= (int) $form['id'] ?>">מילוי נוסף של אותו טופס</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= kl_h(kl_url('index.php')) ?>">חזרה לרשימת הטפסים</a>
    </div>
  </div>
</main>
<?php
kl_foot();
