<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\UsuarioRepositoryInterface;

final class AdminController
{
    public function __construct(private UsuarioRepositoryInterface $usuarios)
    {
    }

    public function listarUsuarios(): never
    {
        AuthMiddleware::role('admin');
        Response::success($this->usuarios->all());
    }
}