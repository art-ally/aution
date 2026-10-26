<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionDir = STORAGE_PATH . '/sessions';
            if (is_dir($sessionDir) && is_writable($sessionDir)) {
                session_save_path($sessionDir);
            }
            session_set_cookie_params([
                'lifetime' => 86400 * 7,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('ATELIER_NOIR_SESS');
            session_start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function setFlash(string $key, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        self::start();
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }
        $msg = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        self::start();
        if (empty($_SESSION['_csrf_token']) || $token === '') {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
