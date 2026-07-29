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
<main class="auth-shell">
  <div class="auth-card">
    <div class="hero" style="padding-top:0;">
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
        <div class="password-field">
          <input type="password" id="password" name="password" required>
          <button type="button" class="password-toggle" data-target="password" aria-label="הצג סיסמה" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="icon-eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" hidden>
              <path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M6.4 6.7C3.6 8.4 1.5 12 1.5 12s3.5 7 10.5 7c1.9 0 3.5-.5 4.9-1.2M17.9 17.5C20.4 15.8 22.5 12 22.5 12s-1.2-2.4-3.5-4.4C17.1 6.2 14.7 5 12 5c-.7 0-1.4.08-2 .23"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">התחבר/י</button>
    </form>

    <div style="text-align:center; margin-top:8px;">
      <button type="button" class="btn btn-ghost" disabled title="<?= kl_google_login_enabled() ? '' : 'טרם הוגדר על ידי מנהל המערכת' ?>" style="opacity:0.55; cursor:not-allowed;">
        התחברות עם Google
      </button>
    </div>
  </div>
</main>
<?php
kl_foot();
