<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\AppException;
use PDO;
use PDOException;

/**
 * Unica camada que conversa com a tabela usuarios (somente prepared statements).
 */
final class UsuarioRepository implements UsuarioRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $st->execute(['email' => $email]);
        return $st->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nome, email, perfil, criado_em FROM usuarios WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function create(string $nome, string $email, string $senhaHash, string $perfil = 'usuario'): int
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, :hash, :perfil)'
            );
            $st->execute(['nome' => $nome, 'email' => $email, 'hash' => $senhaHash, 'perfil' => $perfil]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // violacao de UNIQUE
                throw new AppException('E-mail já cadastrado', 409);
            }
            throw $e;
        }
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT id, nome, email, perfil, criado_em FROM usuarios ORDER BY id')
            ->fetchAll();
    }
}