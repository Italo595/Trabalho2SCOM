<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validacao de tipo/tamanho/formato + sanitizacao basica de texto.
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    /** Texto livre: remove tags HTML e caracteres de controle antes de medir. */
    public static function cleanText(mixed $v): string
    {
        $v = trim(strip_tags((string) $v));
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
    }

    public function text(string $field, int $min, int $max, string $label): self
    {
        $v = $this->data[$field] ?? null;
        if (!is_string($v)) {
            $this->errors[$field] = "$label é obrigatório e deve ser texto";
            return $this;
        }
        $len = mb_strlen(self::cleanText($v));
        if ($len < $min || $len > $max) {
            $this->errors[$field] = "$label deve ter entre $min e $max caracteres";
        }
        return $this;
    }

    public function email(string $field, int $max = 120): self
    {
        $v = $this->data[$field] ?? null;
        if (!is_string($v) || mb_strlen(trim($v)) > $max || !filter_var(trim($v), FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'E-mail inválido';
        }
        return $this;
    }

    public function password(string $field, int $min = 8, int $max = 72): self
    {
        $v = $this->data[$field] ?? null;
        if (!is_string($v)) {
            $this->errors[$field] = 'Senha é obrigatória e deve ser texto';
        } elseif (strlen($v) < $min || strlen($v) > $max) {
            $this->errors[$field] = "Senha deve ter entre $min e $max caracteres";
        } elseif (!preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) {
            $this->errors[$field] = 'Senha deve conter letras e números';
        }
        return $this;
    }

    /** Lanca AppException 422 se houver qualquer erro. */
    public function validate(): void
    {
        if ($this->errors) {
            throw new AppException('Dados inválidos', 422, $this->errors);
        }
    }
}