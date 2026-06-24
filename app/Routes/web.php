<?php

namespace Massango\Routes;

/**
 * Massango Web Routes
 *
 * Centraliza todas as rotas da aplicacao.
 */

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!isset($this->routes[$method][$path])) {
            http_response_code(404);
            require __DIR__ . '/../../public/404.php';
            return;
        }

        $handler = $this->routes[$method][$path];

        if (is_array($handler)) {
            [$controller, $action] = $handler;
            $instance = new $controller();
            $instance->$action();
        } else {
            $handler();
        }
    }
}

return new Router();
