<?php

declare(strict_types=1);

namespace Snaply\Core;

/**
 * Router minimale: registra rotte per metodo+pattern e le dispatcha.
 * I pattern supportano placeholder {nome} (es. /api/events/{id}).
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $params = [];
        // Trasforma {id} in un gruppo regex e memorizza i nomi dei parametri.
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    /**
     * Trova la rotta corrispondente e la esegue.
     * Distingue 404 (path inesistente) da 405 (metodo non ammesso).
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            array_shift($matches); // rimuove il match completo
            $params = array_combine($route['params'], $matches) ?: [];

            ($route['handler'])($request, $params);
            return;
        }

        if ($pathMatched) {
            Response::error('METHOD_NOT_ALLOWED', 'Metodo non consentito', 405);
        }
        Response::error('NOT_FOUND', 'Risorsa non trovata', 404);
    }
}
