<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador simples. Rotas com parametros: /api/pokemons/{id}
 */
final class Router
{
    /** @var array<int, array{string,string,callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Remove a pasta base (ex.: /Trabalho2SCOM/public)
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');

        $pathExists = false;
        foreach ($this->routes as [$m, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $pathExists = true;
                if ($m === strtoupper($method)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $handler($params);
                    return;
                }
            }
        }

        if ($pathExists) {
            Response::error('Metodo HTTP nao permitido', 405);
        }
        Response::error('Rota nao encontrada', 404);
    }
}
