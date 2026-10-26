<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function user(): ?array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }
        $db = JsonDatabase::getInstance();
        return $db->find('users', (int)$userId);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $roles = $user['roles'] ?? [$user['role'] ?? 'buyer'];
        if (is_string($roles)) {
            $roles = [$roles];
        }
        return in_array($role, $roles, true) || in_array('admin', $roles, true);
    }

    public static function requireAuth(): array
    {
        $user = self::user();
        if (!$user) {
            flash('error', 'Please sign in to access your Atelier Noir account.');
            redirect('/login');
        }
        return $user;
    }

    public static function requireRole(string $role): array
    {
        $user = self::requireAuth();
        if (!self::hasRole($role)) {
            http_response_code(403);
            flash('error', 'You do not have permission to access that section.');
            redirect('/');
        }
        return $user;
    }
}
