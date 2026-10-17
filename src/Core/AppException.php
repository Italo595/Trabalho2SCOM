<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Erro "esperado" da aplicacao (validacao, nao autenticado, conflito...).
 * Services lancam, o tratador central em public/index.php converte em JSON.
 */
final class AppException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 400,
        private array $errors = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}