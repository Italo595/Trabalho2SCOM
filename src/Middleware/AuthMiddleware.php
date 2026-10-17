<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\{AppException, Logger, Session};

final class AuthMiddleware
{
    /** Exige usuario logado (401 caso contrario) e devolve seus dados. */
    public static function require(): array
    {
        $user = Session::user();
        if ($user === null) {
            Logger::warning('Acesso sem autenticação', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            throw new AppException('Autenticação necessária', 401);
        }
        return $user;
    }

    /** Exige um dos perfis informados (403 caso contrario). */
    public static function role(string ...$perfis): array
    {
        $user = self::require();
        if (!in_array($user['perfil'], $perfis, true)) {
            Logger::warning('Acesso negado por perfil', ['usuario' => $user['id'], 'perfil' => $user['perfil']]);
            throw new AppException('Você não tem permissão para esta ação', 403);
        }
        return $user;
    }
}