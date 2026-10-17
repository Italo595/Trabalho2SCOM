<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\{AppException, Logger, Request, Session};

/**
 * Exige o cabecalho X-CSRF-Token em metodos que alteram estado.
 */
final class CsrfMiddleware
{
    public static function verify(string $method): void
    {
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $sent  = Request::header('X-CSRF-Token') ?? '';
        $saved = $_SESSION['csrf'] ?? '';
        if ($saved === '' || !hash_equals($saved, $sent)) {
            Logger::warning('Falha de CSRF', ['metodo' => $method]);
            throw new AppException('Token CSRF inválido ou ausente', 403);
        }
    }
}