<?php
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/layout.php';

if (kl_current_user()) {
    header('Location: ' . kl_url('select-site.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $pdo = kl_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {
        kl_login($user);
        header('Location: ' . kl_url('select-site.php'));
        exit;
    }

    $error = 'אימייל או סיסמה שגויים';
}

kl_head('התחברות');
kl_topbar();
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">בקרת בטיחות מזון</div>
    <h1>התחברות</h1>
  </div>

  <?php if ($error): ?>
    <div class="field" style="background:var(--danger-100); color:var(--danger-600); border-radius:var(--radius-control); padding:12px 14px; font-weight:600;">
      <?= kl_h($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="field">
      <label class="field__label" for="email">אימייל</label>
      <input type="email" id="email" name="email" required autofocus value="<?= kl_h($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label class="field__label" for="password">סיסמה</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div class="submit-bar">
      <div class="submit-bar__inner">
        <button type="submit" class="btn btn-primary">התחבר/י</button>
      </div>
    </div>
  </form>

  <div style="text-align:center; margin-top:8px;">
    <button type="button" class="btn btn-ghost" disabled title="<?= kl_google_login_enabled() ? '' : 'טרם הוגדר על ידי מנהל המערכת' ?>" style="opacity:0.55; cursor:not-allowed;">
      התחברות עם Google
    </button>
  </div>
</main>
<?php
kl_foot();
