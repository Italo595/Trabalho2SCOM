<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** Le o corpo da requisicao como JSON. */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new AppException('Corpo da requisição não é um JSON válido', 400);
        }
        return $data;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}