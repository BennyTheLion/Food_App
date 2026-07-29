<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

$user = kl_require_login();

$siteId = (int) ($_GET['site'] ?? 0);
$accessibleSites = kl_accessible_sites($user);
$site = null;
foreach ($accessibleSites as $s) {
    if ((int) $s['id'] === $siteId) {
        $site = $s;
        break;
    }
}

if (!$site) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$pdo = kl_db();
$stmt = $pdo->prepare('SELECT * FROM kitchens WHERE site_id = :site_id ORDER BY name');
$stmt->execute([':site_id' => $siteId]);
$kitchens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['pick'])) {
    $pickId = (int) $_GET['pick'];
    foreach ($kitchens as $k) {
        if ((int) $k['id'] === $pickId) {
            kl_set_current_kitchen($pickId);
            header('Location: ' . kl_url('index.php'));
            exit;
        }
    }
}

if (count($kitchens) === 1 && count($accessibleSites) === 1) {
    kl_set_current_kitchen((int) $kitchens[0]['id']);
    header('Location: ' . kl_url('index.php'));
    exit;
}

kl_head('בחירת מטבח');
kl_topbar(kl_url('select-site.php'), 'לבחירת אתר');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow"><?= kl_h($site['name']) ?></div>
    <h1>בחר/י מטבח</h1>
  </div>

  <?php if (!$kitchens): ?>
    <div class="empty">טרם הוגדרו מטבחים לאתר זה. פני/ה למנהל המערכת.</div>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($kitchens as $k): ?>
        <a class="form-card" href="<?= kl_h(kl_url('select-kitchen.php')) ?>?site=<?= $siteId ?>&pick=<?= (int) $k['id'] ?>">
          <span class="form-card__body"><span class="form-card__title"><?= kl_h($k['name']) ?></span></span>
          <span class="form-card__chevron">‹</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
kl_foot();
