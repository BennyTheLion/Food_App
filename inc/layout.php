<?php
declare(strict_types=1);

function kl_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Base URL path of the app, detected from the request so it works from any deploy folder (root or subfolder) and from inside admin/. */
function kl_base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if (substr($dir, -6) === '/admin') {
            $dir = substr($dir, 0, -6);
        }
        $base = $dir;
    }
    return $base;
}

function kl_url(string $path): string
{
    return kl_base_path() . '/' . ltrim($path, '/');
}

function kl_head(string $title): void
{
    ?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= kl_h($title) ?> · מערכת ניהול מטבח</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@500;700;800&family=Assistant:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= kl_h(kl_url('assets/css/style.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1' ?>">
</head>
<body>
    <?php
}

function kl_topbar(?string $backHref = null, ?string $backLabel = null): void
{
    ?>
<header class="topbar">
  <div class="topbar__row">
    <a href="<?= kl_h(kl_url('index.php')) ?>" class="topbar__brand"><span class="dot"></span>מערכת ניהול מטבח</a>
    <?php if ($backHref): ?>
      <a class="topbar__back" href="<?= kl_h($backHref) ?>">‹ <?= kl_h($backLabel ?? 'חזרה') ?></a>
    <?php endif; ?>
  </div>
</header>
    <?php
}

/** Shows who's logged in and (if selected) their current site/kitchen, with links to switch or log out. */
function kl_context_bar(array $user, ?array $kitchen = null): void
{
    ?>
<div class="station-bar">
  <div class="station-bar__row">
    <span>שלום, <?= kl_h($user['name']) ?><?= kl_is_admin($user) ? ' (מנהל)' : '' ?></span>
    <?php if ($kitchen): ?>
      <span>· <?= kl_h($kitchen['site_name']) ?> — <?= kl_h($kitchen['dining_room_name']) ?> — <?= kl_h($kitchen['name']) ?></span>
      <a href="<?= kl_h(kl_url('select-site.php')) ?>">יציאה מהמטבח</a>
    <?php endif; ?>
    <a href="<?= kl_h(kl_url('logout.php')) ?>" style="margin-inline-start:auto;">התנתקות</a>
  </div>
</div>
    <?php
}

function kl_foot(): void
{
    ?>
<script src="<?= kl_h(kl_url('assets/js/app.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: '1' ?>"></script>
</body>
</html>
    <?php
}
