<?php
declare(strict_types=1);

function kl_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
<link rel="stylesheet" href="/Food_App/kitchen-log-app/assets/css/style.css">
</head>
<body>
    <?php
}

function kl_topbar(?string $backHref = null, ?string $backLabel = null): void
{
    ?>
<header class="topbar">
  <div class="topbar__row">
    <a href="/Food_App/kitchen-log-app/index.php" class="topbar__brand"><span class="dot"></span>יומן מטבח</a>
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
<script src="/Food_App/kitchen-log-app/assets/js/app.js"></script>
</body>
</html>
    <?php
}
