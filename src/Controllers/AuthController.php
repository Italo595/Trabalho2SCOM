<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response, Session};
use App\Middleware\AuthMiddleware;
use App\Repositories\UsuarioRepositoryInterface;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(
        private AuthService $service,
        private UsuarioRepositoryInterface $usuarios
    ) {
    }

    public function csrf(): never
    {
        Response::success(['csrf_token' => Session::csrfToken()]);
    }

    public function register(): never
    {
        $user = $this->service->register(Request::json());
        Response::success($user, 'Usuário cadastrado com sucesso', 201);
    }

    public function login(): never
    {
        $user = $this->service->login(Request::json());
        Session::login($user);
        Response::success($user, 'Login realizado com sucesso');
    }

    public function logout(): never
    {
        AuthMiddleware::require();
        Session::destroy();
        Response::success(null, 'Logout realizado com sucesso');
    }

    public function me(): never
    {
        $sess = AuthMiddleware::require();
        Response::success($this->usuarios->findById($sess['id']));
    }

    /** Informa se ha sessao ativa (sempre 200; usado pelo front-end ao abrir a pagina). */
    public function session(): never
    {
        $sess    = Session::user();
        $usuario = $sess !== null ? $this->usuarios->findById($sess['id']) : null;
        if ($sess !== null && $usuario === null) { // usuario removido do banco
            Session::destroy();
        }
        Response::success(['autenticado' => $usuario !== null, 'usuario' => $usuario]);
    }
}