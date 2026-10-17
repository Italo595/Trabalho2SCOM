<?php
declare(strict_types=1);

// Autoload simples: App\Core\Router -> src/Core/Router.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\{Database, Logger, Response, Router};

$config = require __DIR__ . '/../config/config.php';
Logger::init($config['log_file']);

// Tratamento centralizado de erros: detalhes tecnicos so em desenvolvimento
set_exception_handler(function (Throwable $e) use ($config): void {
    Logger::error('Erro interno: ' . $e->getMessage(), ['arquivo' => basename($e->getFile()), 'linha' => $e->getLine()]);
    Response::error(
        'Erro interno do servidor',
        500,
        [],
        $config['debug'] ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : null
    );
});

$router = new Router();

// ---- Rotas ----
$router->add('GET', '/api/health', function () use ($config) {
    $pdo = Database::connection($config['db']);
    $total = (int) $pdo->query('SELECT COUNT(*) FROM pokemons')->fetchColumn();
    Response::success(['status' => 'online', 'banco' => 'conectado', 'pokemons' => $total]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
