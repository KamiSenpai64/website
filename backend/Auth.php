<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth
{
    private const SESSION_KEY = 'astrotarot_user';

    public static function login(array $account): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'id' => $account['id'],
            'email' => $account['email'],
            'name' => $account['name'] ?? null,
            'role' => $account['role'] ?? 'user',
            'status' => $account['status'] ?? 'active'
        ];
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);
        session_destroy();
    }

    public static function currentUser(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function isAdmin(): bool
    {
        $user = self::currentUser();
        return $user !== null && ($user['role'] === 'admin' || $user['role'] === 'superadmin');
    }

    public static function requireLogin(): void
    {
        if (self::currentUser() === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Autentificare necesară.']);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Acces interzis. Admini doar.']);
            exit;
        }
    }
}
