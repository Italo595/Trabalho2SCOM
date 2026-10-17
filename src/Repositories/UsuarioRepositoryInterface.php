<?php
declare(strict_types=1);

namespace App\Repositories;

interface UsuarioRepositoryInterface
{
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
    public function create(string $nome, string $email, string $senhaHash, string $perfil = 'usuario'): int;
    public function all(): array;
}