<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\AppException;
use PDO;
use PDOException;

/**
 * Acesso a tabela pokemons (somente prepared statements).
 */
class PokemonRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Lista paginada com filtros opcionais. Retorna ['items' => [...], 'total' => N]. */
    public function paginate(int $page, int $limit, ?string $tipo, ?string $busca): array
    {
        $where  = [];
        $params = [];
        if ($tipo !== null) {
            $where[] = 'tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($busca !== null) {
            $where[] = 'nome LIKE :busca';
            $params[':busca'] = '%' . addcslashes($busca, '%_\\') . '%';
        }
        $filtro = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM pokemons' . $filtro);
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            'SELECT id, nome, tipo, descricao, imagem_url, criado_em FROM pokemons'
            . $filtro . ' ORDER BY id LIMIT :limite OFFSET :deslocamento'
        );
        foreach ($params as $chave => $valor) {
            $st->bindValue($chave, $valor);
        }
        $st->bindValue(':limite', $limit, PDO::PARAM_INT);
        $st->bindValue(':deslocamento', ($page - 1) * $limit, PDO::PARAM_INT);
        $st->execute();

        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, nome, tipo, descricao, imagem_url, criado_em FROM pokemons WHERE id = :id'
        );
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function create(string $nome, string $tipo, ?string $descricao, ?string $imagemUrl, int $criadoPor): int
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO pokemons (nome, tipo, descricao, imagem_url, criado_por)
                 VALUES (:nome, :tipo, :descricao, :imagem, :criado_por)'
            );
            $st->execute([
                'nome' => $nome, 'tipo' => $tipo, 'descricao' => $descricao,
                'imagem' => $imagemUrl, 'criado_por' => $criadoPor,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->tratarDuplicado($e);
            throw $e;
        }
    }

    public function update(int $id, string $nome, string $tipo, ?string $descricao, ?string $imagemUrl): void
    {
        try {
            $st = $this->pdo->prepare(
                'UPDATE pokemons SET nome = :nome, tipo = :tipo, descricao = :descricao, imagem_url = :imagem
                 WHERE id = :id'
            );
            $st->execute([
                'nome' => $nome, 'tipo' => $tipo, 'descricao' => $descricao,
                'imagem' => $imagemUrl, 'id' => $id,
            ]);
        } catch (PDOException $e) {
            $this->tratarDuplicado($e);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM pokemons WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    private function tratarDuplicado(PDOException $e): void
    {
        if ($e->getCode() === '23000') { // violacao de UNIQUE (nome)
            throw new AppException('Já existe um Pokémon com este nome', 409);
        }
    }
}