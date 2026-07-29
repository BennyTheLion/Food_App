<?php
declare(strict_types=1);

/**
 * Google login is wired up structurally (see login.php) but disabled until real
 * OAuth credentials exist. Fill these in (via env vars, not hardcoded/committed)
 * once you've created a Google Cloud OAuth Client ID/Secret, and the button on
 * the login page will activate automatically.
 */
const KL_GOOGLE_CLIENT_ID = '';
const KL_GOOGLE_CLIENT_SECRET = '';

function kl_google_login_enabled(): bool
{
    return KL_GOOGLE_CLIENT_ID !== '' && KL_GOOGLE_CLIENT_SECRET !== '';
}

function kl_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function kl_current_user(): ?array
{
    kl_start_session();
    $pdo = kl_db(); // guarantees schema/seed exist even before any login has happened

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached !== null && $cached['id'] === $_SESSION['user_id']) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        kl_logout();
        return null;
    }

    $cached = $user;
    return $user;
}

function kl_login(array $user): void
{
    kl_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function kl_logout(): void
{
    kl_start_session();
    $_SESSION = [];
    session_destroy();
}

function kl_require_login(): array
{
    $user = kl_current_user();
    if (!$user) {
        header('Location: ' . kl_url('login.php'));
        exit;
    }
    return $user;
}

function kl_require_admin(): array
{
    $user = kl_require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        kl_head('אין הרשאה');
        kl_topbar(kl_url('index.php'), 'לדף הבית');
        echo '<main class="container"><div class="empty">אין לך הרשאה לצפות בעמוד זה.</div></main>';
        kl_foot();
        exit;
    }
    return $user;
}

function kl_is_admin(array $user): bool
{
    return $user['role'] === 'admin';
}

/** Sites/kitchens a user may access: their assigned site for regular users, every site for admins. */
function kl_accessible_sites(array $user): array
{
    $pdo = kl_db();
    if (kl_is_admin($user)) {
        return $pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!$user['site_id']) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM sites WHERE id = :id');
    $stmt->execute([':id' => $user['site_id']]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    return $site ? [$site] : [];
}

function kl_current_kitchen_id(): ?int
{
    kl_start_session();
    return isset($_SESSION['kitchen_id']) ? (int) $_SESSION['kitchen_id'] : null;
}

function kl_set_current_kitchen(int $kitchenId): void
{
    kl_start_session();
    $_SESSION['kitchen_id'] = $kitchenId;
}

/** Verifies the currently selected kitchen (session) belongs to a site the user can access. Returns it, or null if none/invalid. */
function kl_require_kitchen(array $user): ?array
{
    $kitchenId = kl_current_kitchen_id();
    if (!$kitchenId) {
        return null;
    }
    $pdo = kl_db();
    $stmt = $pdo->prepare('SELECT k.*, s.name AS site_name FROM kitchens k JOIN sites s ON s.id = k.site_id WHERE k.id = :id');
    $stmt->execute([':id' => $kitchenId]);
    $kitchen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$kitchen) {
        return null;
    }
    if (!kl_is_admin($user) && (int) $kitchen['site_id'] !== (int) $user['site_id']) {
        return null;
    }
    return $kitchen;
}
