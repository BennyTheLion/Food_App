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

const KL_REMEMBER_COOKIE = 'kl_remember';
const KL_REMEMBER_DAYS = 30;

/** Issues a fresh remember-me cookie + DB token (selector/validator pattern — only a hash of the validator is stored). */
function kl_issue_remember_token(int $userId): void
{
    $pdo = kl_db();
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));

    $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (:user_id, :selector, :hash, :expires_at)'
    )->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':hash' => hash('sha256', $validator),
        ':expires_at' => (new DateTime('+' . KL_REMEMBER_DAYS . ' days'))->format('Y-m-d H:i:s'),
    ]);

    setcookie(KL_REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires' => time() + KL_REMEMBER_DAYS * 86400,
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
}

function kl_clear_remember_token(): void
{
    if (!empty($_COOKIE[KL_REMEMBER_COOKIE])) {
        $selector = explode(':', $_COOKIE[KL_REMEMBER_COOKIE], 2)[0];
        kl_db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector')->execute([':selector' => $selector]);
    }
    setcookie(KL_REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[KL_REMEMBER_COOKIE]);
}

/** Logs the user in from a valid remember-me cookie when there's no active session, rotating the token on use. */
function kl_try_remember_login(): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE[KL_REMEMBER_COOKIE])) {
        return;
    }
    $parts = explode(':', $_COOKIE[KL_REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) {
        return;
    }
    [$selector, $validator] = $parts;

    $pdo = kl_db();
    $stmt = $pdo->prepare('SELECT * FROM remember_tokens WHERE selector = :selector');
    $stmt->execute([':selector' => $selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    $valid = $token
        && hash_equals($token['validator_hash'], hash('sha256', $validator))
        && $token['expires_at'] >= (new DateTime())->format('Y-m-d H:i:s');

    if (!$valid) {
        kl_clear_remember_token();
        return;
    }

    $pdo->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute([':id' => $token['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $token['user_id'];
    kl_issue_remember_token((int) $token['user_id']);
}

function kl_current_user(): ?array
{
    kl_start_session();
    $pdo = kl_db(); // guarantees schema/seed exist even before any login has happened
    kl_try_remember_login();

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
    kl_clear_remember_token();
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

/** Every registered user can access every site -- there's no per-user site assignment anymore. */
function kl_accessible_sites(array $user): array
{
    $pdo = kl_db();
    return $pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

/** Every dining room within a site -- open to any logged-in user. */
function kl_dining_rooms_for_site(int $siteId): array
{
    $pdo = kl_db();
    $stmt = $pdo->prepare('SELECT * FROM dining_rooms WHERE site_id = :site_id ORDER BY name');
    $stmt->execute([':site_id' => $siteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function kl_clear_current_kitchen(): void
{
    kl_start_session();
    unset($_SESSION['kitchen_id']);
}

/** The currently selected kitchen (session), with its dining room and site names, or null if none/invalid. Any logged-in user may hold any kitchen. */
function kl_require_kitchen(array $user): ?array
{
    $kitchenId = kl_current_kitchen_id();
    if (!$kitchenId) {
        return null;
    }
    $pdo = kl_db();
    $stmt = $pdo->prepare(
        'SELECT k.*, dr.name AS dining_room_name, dr.site_id AS site_id, s.name AS site_name
         FROM kitchens k
         JOIN dining_rooms dr ON dr.id = k.dining_room_id
         JOIN sites s ON s.id = dr.site_id
         WHERE k.id = :id'
    );
    $stmt->execute([':id' => $kitchenId]);
    $kitchen = $stmt->fetch(PDO::FETCH_ASSOC);
    return $kitchen ?: null;
}
