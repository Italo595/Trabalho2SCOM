<?php
declare(strict_types=1);

/**
 * Le o arquivo .env (fora do codigo-fonte versionado) e devolve a configuracao.
 */
$envFile = __DIR__ . '/../.env';
if (!is_file($envFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Arquivo .env nao encontrado. Copie .env.example para .env.']);
    exit;
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];

return [
    'env'    => $env['APP_ENV'] ?? 'production',
    'debug'  => ($env['APP_ENV'] ?? 'production') === 'development',
    'db'     => [
        'host' => $env['DB_HOST'] ?? 'localhost',
        'port' => $env['DB_PORT'] ?? '3306',
        'name' => $env['DB_NAME'] ?? '',
        'user' => $env['DB_USER'] ?? '',
        'pass' => $env['DB_PASS'] ?? '',
    ],
    'log_file' => __DIR__ . '/../logs/app.log',
];
