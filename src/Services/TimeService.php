<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{AppException, Logger, Validator};
use App\Repositories\{PokemonRepository, TimeRepository};

/**
 * Regras de negocio dos times: dono, limite de 6, sem repetidos.
 */
final class TimeService
{
    public const MAX_POKEMON = 6;

    public function __construct(
        private TimeRepository $times,
        private PokemonRepository $pokemons
    ) {
    }

    public function list(int $userId): array
    {
        return $this->times->listByUser($userId);
    }

    public function get(int $timeId, int $userId): array
    {
        $time = $this->exigirDono($timeId, $userId);
        unset($time['usuario_id']);
        $time['pokemons'] = $this->times->pokemonsDoTime($timeId);
        return $time;
    }

    public function create(int $userId, array $input): array
    {
        $nome = $this->validarNome($input);
        $id = $this->times->create($userId, $nome);
        Logger::info('Time criado', ['id' => $id, 'usuario' => $userId]);
        return ['id' => $id, 'nome' => $nome, 'pokemons' => []];
    }

    public function rename(int $timeId, int $userId, array $input): array
    {
        $this->exigirDono($timeId, $userId);
        $this->times->rename($timeId, $this->validarNome($input));
        Logger::info('Time renomeado', ['id' => $timeId, 'usuario' => $userId]);
        return $this->get($timeId, $userId);
    }

    public function delete(int $timeId, int $userId): void
    {
        $this->exigirDono($timeId, $userId);
        $this->times->delete($timeId);
        Logger::info('Time excluído', ['id' => $timeId, 'usuario' => $userId]);
    }

    public function addPokemon(int $timeId, int $userId, array $input): array
    {
        $this->exigirDono($timeId, $userId);

        (new Validator($input))->int('pokemon_id', 1, 4294967295, 'pokemon_id')->validate();
        $pokemonId = (int) $input['pokemon_id'];

        if ($this->pokemons->findById($pokemonId) === null) {
            throw new AppException('Pokémon não encontrado', 404);
        }
        if ($this->times->hasPokemon($timeId, $pokemonId)) {
            throw new AppException('Este Pokémon já está no time', 409);
        }
        if ($this->times->countPokemons($timeId) >= self::MAX_POKEMON) {
            throw new AppException('O time já tem o máximo de ' . self::MAX_POKEMON . ' Pokémon', 422);
        }

        $this->times->addPokemon($timeId, $pokemonId);
        Logger::info('Pokémon adicionado ao time', ['time' => $timeId, 'pokemon' => $pokemonId, 'usuario' => $userId]);
        return $this->get($timeId, $userId);
    }

    public function removePokemon(int $timeId, int $userId, int $pokemonId): array
    {
        $this->exigirDono($timeId, $userId);
        if ($this->times->removePokemon($timeId, $pokemonId) === 0) {
            throw new AppException('Este Pokémon não está no time', 404);
        }
        Logger::info('Pokémon removido do time', ['time' => $timeId, 'pokemon' => $pokemonId, 'usuario' => $userId]);
        return $this->get($timeId, $userId);
    }

    /** 404 se o time nao existe; 403 se pertence a outro usuario. */
    private function exigirDono(int $timeId, int $userId): array
    {
        $time = $this->times->findById($timeId);
        if ($time === null) {
            throw new AppException('Time não encontrado', 404);
        }
        if ((int) $time['usuario_id'] !== $userId) {
            Logger::warning('Tentativa de acesso a time de outro usuário', ['time' => $timeId, 'usuario' => $userId]);
            throw new AppException('Você não tem permissão sobre este time', 403);
        }
        return $time;
    }

    private function validarNome(array $input): string
    {
        (new Validator($input))->text('nome', 2, 60, 'Nome do time')->validate();
        return Validator::cleanText($input['nome']);
    }
}