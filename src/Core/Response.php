<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Respostas JSON padronizadas.
 */
final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = [], ?string $debug = null): never
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors) {
            $body['errors'] = $errors;
        }
        if ($debug !== null) { // so preenchido em modo desenvolvimento
            $body['debug'] = $debug;
        }
        self::json($body, $status);
    }
}
