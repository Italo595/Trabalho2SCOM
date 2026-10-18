<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\AppException;
use PDO;
use PDOException;

/**
 * Acesso as tabelas times e time_pokemon (somente prepared statements).
 */
class TimeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Times do usuario, cada um com a lista de Pokemon. */
    public function listByUser(int $userId): array
    {
        $st = $this->pdo->prepare('SELECT id, nome, criado_em FROM times WHERE usuario_id = :u ORDER BY id');
        $st->execute(['u' => $userId]);
        $times = $st->fetchAll();
        if (!$times) {
            return [];
        }

        $st = $this->pdo->prepare(
            'SELECT tp.time_id, p.id, p.nome, p.tipo, p.imagem_url
             FROM time_pokemon tp
             JOIN pokemons p ON p.id = tp.pokemon_id
             JOIN times t ON t.id = tp.time_id
             WHERE t.usuario_id = :u
             ORDER BY tp.adicionado_em, p.id'
        );
        $st->execute(['u' => $userId]);
        $porTime = [];
        foreach ($st->fetchAll() as $linha) {
            $timeId = $linha['time_id'];
            unset($linha['time_id']);
            $porTime[$timeId][] = $linha;
        }

        foreach ($times as &$time) {
            $time['pokemons'] = $porTime[$time['id']] ?? [];
        }
        return $times;
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nome, usuario_id, criado_em FROM times WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function pokemonsDoTime(int $timeId): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.id, p.nome, p.tipo, p.imagem_url
             FROM time_pokemon tp JOIN pokemons p ON p.id = tp.pokemon_id
             WHERE tp.time_id = :t ORDER BY tp.adicionado_em, p.id'
        );
        $st->execute(['t' => $timeId]);
        return $st->fetchAll();
    }

    public function create(int $userId, string $nome): int
    {
        try {
            $st = $this->pdo->prepare('INSERT INTO times (nome, usuario_id) VALUES (:nome, :u)');
            $st->execute(['nome' => $nome, 'u' => $userId]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->tratarDuplicado($e);
            throw $e;
        }
    }

    public function rename(int $id, string $nome): void
    {
        try {
            $st = $this->pdo->prepare('UPDATE times SET nome = :nome WHERE id = :id');
            $st->execute(['nome' => $nome, 'id' => $id]);
        } catch (PDOException $e) {
            $this->tratarDuplicado($e);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM times WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    public function countPokemons(int $timeId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM time_pokemon WHERE time_id = :t');
        $st->execute(['t' => $timeId]);
        return (int) $st->fetchColumn();
    }

    public function hasPokemon(int $timeId, int $pokemonId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM time_pokemon WHERE time_id = :t AND pokemon_id = :p');
        $st->execute(['t' => $timeId, 'p' => $pokemonId]);
        return (bool) $st->fetchColumn();
    }

    public function addPokemon(int $timeId, int $pokemonId): void
    {
        $st = $this->pdo->prepare('INSERT INTO time_pokemon (time_id, pokemon_id) VALUES (:t, :p)');
        $st->execute(['t' => $timeId, 'p' => $pokemonId]);
    }

    /** Retorna quantas linhas foram removidas (0 = o Pokemon nao estava no time). */
    public function removePokemon(int $timeId, int $pokemonId): int
    {
        $st = $this->pdo->prepare('DELETE FROM time_pokemon WHERE time_id = :t AND pokemon_id = :p');
        $st->execute(['t' => $timeId, 'p' => $pokemonId]);
        return $st->rowCount();
    }

    private function tratarDuplicado(PDOException $e): void
    {
        if ($e->getCode() === '23000') { // UNIQUE (usuario_id, nome)
            throw new AppException('Você já tem um time com este nome', 409);
        }
    }
}