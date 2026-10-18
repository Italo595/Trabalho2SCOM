<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response, Validator};
use App\Middleware\AuthMiddleware;
use App\Services\TimeService;

final class TimeController
{
    public function __construct(private TimeService $service)
    {
    }

    public function index(): never
    {
        $user = AuthMiddleware::require();
        Response::success($this->service->list($user['id']));
    }

    public function show(array $p): never
    {
        $user = AuthMiddleware::require();
        Response::success($this->service->get(Validator::id($p['id']), $user['id']));
    }

    public function store(): never
    {
        $user = AuthMiddleware::require();
        Response::success($this->service->create($user['id'], Request::json()), 'Time criado com sucesso', 201);
    }

    public function update(array $p): never
    {
        $user = AuthMiddleware::require();
        $time = $this->service->rename(Validator::id($p['id']), $user['id'], Request::json());
        Response::success($time, 'Time atualizado com sucesso');
    }

    public function destroy(array $p): never
    {
        $user = AuthMiddleware::require();
        $this->service->delete(Validator::id($p['id']), $user['id']);
        Response::success(null, 'Time excluído com sucesso');
    }

    public function addPokemon(array $p): never
    {
        $user = AuthMiddleware::require();
        $time = $this->service->addPokemon(Validator::id($p['id']), $user['id'], Request::json());
        Response::success($time, 'Pokémon adicionado ao time', 201);
    }

    public function removePokemon(array $p): never
    {
        $user = AuthMiddleware::require();
        $time = $this->service->removePokemon(
            Validator::id($p['id']),
            $user['id'],
            Validator::id($p['pokemon_id'], 'ID do Pokémon')
        );
        Response::success($time, 'Pokémon removido do time');
    }
}