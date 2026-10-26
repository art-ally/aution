<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;
use App\Core\JsonDatabase;

/**
 * Escape HTML output to prevent XSS
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format cents or numeric amount into luxury currency display
 */
function format_money(int|float|string $amount, string $currency = 'USD'): string
{
    $num = (float) $amount;
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CHF' => 'CHF ',
    ];
    $symbol = $symbols[$currency] ?? ($currency . ' ');
    return $symbol . number_format($num, 0, '.', ',');
}

/**
 * Format exact money with decimals
 */
function format_money_exact(int|float|string $amount, string $currency = 'USD'): string
{
    $num = (float) $amount;
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CHF' => 'CHF ',
    ];
    $symbol = $symbols[$currency] ?? ($currency . ' ');
    return $symbol . number_format($num, 2, '.', ',');
}

/**
 * Generate URL path
 */
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '/' ? '/' : rtrim($path, '/');
}

/**
 * Redirect to a path and exit
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Return CSRF hidden input field
 */
function csrf_field(): string
{
    $token = Session::csrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf(): bool
{
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return Session::verifyCsrf((string) $token);
}

/**
 * Get current authenticated user array or null
 */
function current_user(): ?array
{
    return Auth::user();
}

/**
 * Get unread notification count for current user
 */
function unread_notifications_count(): int
{
    $user = Auth::user();
    if (!$user) {
        return 0;
    }
    $db = JsonDatabase::getInstance();
    $notifications = $db->where('notifications', fn($n) => (int)($n['user_id'] ?? 0) === (int)$user['id'] && empty($n['read_at']));
    return count($notifications);
}

/**
 * Get watchlist artwork IDs for current user
 */
function user_watchlist_ids(): array
{
    $user = Auth::user();
    if (!$user) {
        return $_SESSION['guest_watchlist'] ?? [];
    }
    $db = JsonDatabase::getInstance();
    $items = $db->where('watchlists', fn($w) => (int)($w['user_id'] ?? 0) === (int)$user['id']);
    return array_values(array_map(fn($w) => (int)$w['artwork_id'], $items));
}

/**
 * Generate URL-friendly slug
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . uniqid();
}

/**
 * Calculate minimum next bid increment according to institutional auction tiers
 */
function calculate_bid_increment(float $currentBid): float
{
    $db = JsonDatabase::getInstance();
    $settings = $db->find('platform_settings', 1);
    if ($currentBid < 1000) {
        return 100.0;
    }
    if ($currentBid < 5000) {
        return 250.0;
    }
    if ($currentBid < 10000) {
        return 500.0;
    }
    if ($currentBid < 25000) {
        return 1000.0;
    }
    if ($currentBid < 100000) {
        return 2500.0;
    }
    return 5000.0;
}

/**
 * Calculate minimum valid next bid for an auction
 */
function minimum_next_bid(array $auction): float
{
    $bidCount = (int)($auction['bid_count'] ?? 0);
    $startingBid = (float)($auction['starting_bid'] ?? 0);
    $currentBid = (float)($auction['current_bid'] ?? 0);

    if ($bidCount === 0 || $currentBid < $startingBid) {
        return $startingBid;
    }

    return $currentBid + calculate_bid_increment($currentBid);
}

/**
 * Render a view template inside a layout
 */
function view(string $template, array $data = [], ?string $layout = 'layouts/main'): void
{
    extract($data, EXTR_SKIP);
    $templateFile = VIEWS_PATH . '/' . str_replace('.', '/', $template) . '.php';
    if (!is_file($templateFile)) {
        http_response_code(500);
        echo "View [{$template}] not found.";
        return;
    }

    if ($layout === null) {
        require $templateFile;
        return;
    }

    ob_start();
    require $templateFile;
    $content = ob_get_clean();

    $layoutFile = VIEWS_PATH . '/' . str_replace('.', '/', $layout) . '.php';
    if (is_file($layoutFile)) {
        require $layoutFile;
    } else {
        echo $content;
    }
}

/**
 * Format human-readable time remaining
 */
function time_remaining_label(string $endTime): string
{
    $now = time();
    $end = strtotime($endTime);
    if ($end === false || $end <= $now) {
        return 'Auction Closed';
    }
    $diff = $end - $now;
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) {
        return "{$days}d {$hours}h remaining";
    }
    if ($hours > 0) {
        return "{$hours}h {$minutes}m remaining";
    }
    $seconds = $diff % 60;
    return "{$minutes}m {$seconds}s remaining";
}

/**
 * Flash message helper
 */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        Session::setFlash($key, $message);
        return null;
    }
    return Session::getFlash($key);
}

/**
 * Platform settings accessor
 */
function platform_setting(string $key, mixed $default = null): mixed
{
    $db = JsonDatabase::getInstance();
    $settings = $db->find('platform_settings', 1) ?? [];
    return $settings[$key] ?? $default;
}
