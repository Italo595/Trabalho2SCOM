<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sessao segura: cookie HttpOnly + SameSite, id regenerado no login.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('SCOMSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true); // evita session fixation
        $_SESSION['user'] = [
            'id'     => (int) $user['id'],
            'nome'   => $user['nome'],
            'perfil' => $user['perfil'],
        ];
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}