<?php
declare(strict_types=1);

function kl_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Base URL path of the app, detected from the request so it works from any deploy folder (root or subfolder). */
function kl_base_path(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
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
<title><?= kl_h($title) ?> · יומן מטבח</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@500;700;800&family=Assistant:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= kl_h(kl_url('assets/css/style.css')) ?>">
</head>
<body>
    <?php
}

function kl_topbar(?string $backHref = null, ?string $backLabel = null): void
{
    ?>
<header class="topbar">
  <div class="topbar__row">
    <a href="<?= kl_h(kl_url('index.php')) ?>" class="topbar__brand"><span class="dot"></span>יומן מטבח</a>
    <?php if ($backHref): ?>
      <a class="topbar__back" href="<?= kl_h($backHref) ?>">‹ <?= kl_h($backLabel ?? 'חזרה') ?></a>
    <?php endif; ?>
  </div>
</header>
    <?php
}

function kl_station_bar(): void
{
    ?>
<div class="station-bar">
  <div class="station-bar__row">
    <label for="stationSelect">תחנה:</label>
    <select id="stationSelect"></select>
    <label for="fillerName">שם הממלא:</label>
    <input type="text" id="fillerName" placeholder="הקלד/י שם" autocomplete="off">
  </div>
</div>
    <?php
}

function kl_foot(): void
{
    ?>
<script src="<?= kl_h(kl_url('assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}
