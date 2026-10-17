<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response, Validator};
use App\Middleware\AuthMiddleware;
use App\Services\PokemonService;

final class PokemonController
{
    public function __construct(private PokemonService $service)
    {
    }

    public function index(): never
    {
        Response::success($this->service->list($_GET));
    }

    public function show(array $p): never
    {
        Response::success($this->service->get(Validator::id($p['id'])));
    }

    public function store(): never
    {
        $user = AuthMiddleware::role('admin');
        $pokemon = $this->service->create(Request::json(), $user['id']);
        Response::success($pokemon, 'Pokémon criado com sucesso', 201);
    }

    public function update(array $p): never
    {
        $user = AuthMiddleware::role('admin');
        $pokemon = $this->service->update(Validator::id($p['id']), Request::json(), $user['id']);
        Response::success($pokemon, 'Pokémon atualizado com sucesso');
    }

    public function destroy(array $p): never
    {
        $user = AuthMiddleware::role('admin');
        $this->service->delete(Validator::id($p['id']), $user['id']);
        Response::success(null, 'Pokémon excluído com sucesso');
    }
}