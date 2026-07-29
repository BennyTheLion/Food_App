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
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            $error = 'יש למלא שם, אימייל, וסיסמה של 8 תווים לפחות.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, created_at)
                     VALUES (:name, :email, :password_hash, :role, :created_at)'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':role' => $role,
                    ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
                ]);
            } catch (PDOException $e) {
                $error = 'כתובת המייל כבר קיימת במערכת.';
            }
        }
    } elseif ($op === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([':role' => $role, ':id' => $id]);

        $newPassword = (string) ($_POST['new_password'] ?? '');
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $error = 'הסיסמה החדשה חייבת להכיל 8 תווים לפחות.';
            } else {
                $pw = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $pw->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $id]);
            }
        }
    } elseif ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            $error = 'לא ניתן למחוק את המשתמש שאיתו את/ה מחובר/ת כרגע.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);
        }
    }

    if (!$error) {
        header('Location: ' . kl_url('admin/users.php'));
        exit;
    }
}

$users = $pdo->query('SELECT * FROM users ORDER BY role DESC, name')->fetchAll(PDO::FETCH_ASSOC);

kl_head('משתמשים');
kl_topbar(kl_url('admin/index.php'), 'לפאנל הניהול');
kl_context_bar($user);
?>
<main class="container">
  <div class="hero">
    <div class="hero__eyebrow">ניהול מערכת</div>
    <h1>משתמשים</h1>
    <p>לכל משתמש רשום גישה לכל האתרים והמטבחים. מנהלים בלבד יכולים לגשת לפאנל הניהול, וניתן להם לעבוד בכמה מטבחים במקביל.</p>
  </div>

  <?php if ($error): ?>
    <div class="banner banner--danger"><?= kl_h($error) ?></div>
  <?php endif; ?>

  <div class="card-list">
    <?php foreach ($users as $u): ?>
      <div class="admin-row" style="flex-direction:column; align-items:stretch;">
        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:8px;">
          <strong><?= kl_h($u['name']) ?></strong>
          <span class="admin-row__meta mono" style="font-family:var(--font-mono);"><?= kl_h($u['email']) ?></span>
        </div>
        <form method="post" class="admin-row__fields">
          <input type="hidden" name="op" value="update">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <select name="role">
            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>משתמש</option>
            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>מנהל</option>
          </select>
          <input type="password" name="new_password" placeholder="סיסמה חדשה (לא חובה)">
          <button type="submit" class="btn btn-ghost">שמירה</button>
        </form>
        <form method="post" onsubmit="return confirm('למחוק את המשתמש?');" style="align-self:flex-end;">
          <input type="hidden" name="op" value="delete">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-ghost--danger">מחיקת משתמש</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="section-label">הוספת משתמש</div>
  <form method="post" class="admin-add">
    <input type="hidden" name="op" value="create">
    <input type="text" name="name" placeholder="שם מלא" required>
    <input type="email" name="email" placeholder="אימייל" required>
    <input type="password" name="password" placeholder="סיסמה (8+ תווים)" required>
    <select name="role">
      <option value="user">משתמש</option>
      <option value="admin">מנהל</option>
    </select>
    <button type="submit" class="btn btn-primary">הוספה</button>
  </form>
</main>
<?php
kl_foot();
