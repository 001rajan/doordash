<?php
declare(strict_types=1);

/**
 * Smart ETA: prep_time + (distance_km * 2) + traffic_delay (minutes).
 */
function calculate_smart_eta(int $prepMinutes, float $distanceKm, int $trafficDelayMinutes): int
{
    $travel = (int) round($distanceKm * 2);
    return max(10, $prepMinutes + $travel + $trafficDelayMinutes);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_name(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

function is_logged_in(): bool
{
    return current_user_id() !== null;
}

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $out = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $out;
}

function random_group_code(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function log_group_activity(
    mysqli $mysqli,
    int $groupOrderId,
    ?int $userId,
    string $actionType,
    string $message,
    ?string $meta = null
): void {
    $metaVal = $meta ?? '';
    $stmt = $mysqli->prepare(
        'INSERT INTO group_activity (group_order_id, user_id, action_type, message, meta) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iisss', $groupOrderId, $userId, $actionType, $message, $metaVal);
    $stmt->execute();
}
