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

use App\Controllers\{AdminController, AuthController, PokemonController, TimeController};
use App\Core\{AppException, Database, Logger, Response, Router, Session};
use App\Middleware\CsrfMiddleware;
use App\Repositories\{PokemonRepository, TimeRepository, UsuarioRepository};
use App\Services\{AuthService, PokemonService, TimeService};

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set('America/Sao_Paulo');
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
$pdo = Database::connection($config['db']);

$usuariosRepo = new UsuarioRepository($pdo);
$pokemonRepo  = new PokemonRepository($pdo);
$timeRepo     = new TimeRepository($pdo);

$auth    = new AuthController(new AuthService($usuariosRepo), $usuariosRepo);
$admin   = new AdminController($usuariosRepo);
$pokemon = new PokemonController(new PokemonService($pokemonRepo));
$time    = new TimeController(new TimeService($timeRepo, $pokemonRepo));

// ---- Rotas ----
$router = new Router();

$router->add('GET', '/api/health', function () use ($pdo) {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM pokemons')->fetchColumn();
    Response::success(['status' => 'online', 'banco' => 'conectado', 'pokemons' => $total]);
});

// Autenticacao
$router->add('GET',  '/api/csrf',           fn() => $auth->csrf());
$router->add('POST', '/api/auth/register',  fn() => $auth->register());
$router->add('POST', '/api/auth/login',     fn() => $auth->login());
$router->add('POST', '/api/auth/logout',    fn() => $auth->logout());
$router->add('GET',  '/api/auth/me',        fn() => $auth->me());
$router->add('GET',  '/api/admin/usuarios', fn() => $admin->listarUsuarios());

// Pokemon (leitura publica; escrita so admin)
$router->add('GET',    '/api/pokemons',      fn() => $pokemon->index());
$router->add('GET',    '/api/pokemons/{id}', fn($p) => $pokemon->show($p));
$router->add('POST',   '/api/pokemons',      fn() => $pokemon->store());
$router->add('PUT',    '/api/pokemons/{id}', fn($p) => $pokemon->update($p));
$router->add('DELETE', '/api/pokemons/{id}', fn($p) => $pokemon->destroy($p));

// Times (usuario logado, somente os proprios)
$router->add('GET',    '/api/times',                              fn() => $time->index());
$router->add('POST',   '/api/times',                              fn() => $time->store());
$router->add('GET',    '/api/times/{id}',                         fn($p) => $time->show($p));
$router->add('PUT',    '/api/times/{id}',                         fn($p) => $time->update($p));
$router->add('DELETE', '/api/times/{id}',                         fn($p) => $time->destroy($p));
$router->add('POST',   '/api/times/{id}/pokemons',                fn($p) => $time->addPokemon($p));
$router->add('DELETE', '/api/times/{id}/pokemons/{pokemon_id}',   fn($p) => $time->removePokemon($p));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);