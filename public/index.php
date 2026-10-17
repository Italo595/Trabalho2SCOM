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

use App\Controllers\{AdminController, AuthController};
use App\Core\{AppException, Database, Logger, Response, Router, Session};
use App\Middleware\CsrfMiddleware;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;

$config = require __DIR__ . '/../config/config.php';
Logger::init($config['log_file']);

// ---- Tratamento centralizado de erros ----
set_exception_handler(function (Throwable $e) use ($config): void {
    if ($e instanceof AppException) {
        Response::error($e->getMessage(), $e->status(), $e->errors());
    }
    Logger::error('Erro interno: ' . $e->getMessage(), ['arquivo' => basename($e->getFile()), 'linha' => $e->getLine()]);
    Response::error(
        'Erro interno do servidor',
        500,
        [],
        $config['debug'] ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : null
    );
});

// ---- Cabecalhos de seguranca ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

Session::start();
CsrfMiddleware::verify($_SERVER['REQUEST_METHOD']); // todo POST/PUT/PATCH/DELETE exige token

// ---- Montagem das dependencias ----
$pdo      = Database::connection($config['db']);
$usuarios = new UsuarioRepository($pdo);
$auth     = new AuthController(new AuthService($usuarios), $usuarios);
$admin    = new AdminController($usuarios);

// ---- Rotas ----
$router = new Router();

$router->add('GET', '/api/health', function () use ($pdo) {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM pokemons')->fetchColumn();
    Response::success(['status' => 'online', 'banco' => 'conectado', 'pokemons' => $total]);
});

$router->add('GET',  '/api/csrf',           fn() => $auth->csrf());
$router->add('POST', '/api/auth/register',  fn() => $auth->register());
$router->add('POST', '/api/auth/login',     fn() => $auth->login());
$router->add('POST', '/api/auth/logout',    fn() => $auth->logout());
$router->add('GET',  '/api/auth/me',        fn() => $auth->me());
$router->add('GET',  '/api/admin/usuarios', fn() => $admin->listarUsuarios());

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);