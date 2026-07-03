<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $action): void
    {
        $this->routes['GET'][$path] = $action;
    }

    public function post(string $path, callable|array $action): void
    {
        $this->routes['POST'][$path] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $base = '/rd.intranet';

        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = preg_replace('#^/index2\.php#', '', $uri);
        $uri = preg_replace('#^/index\.php#', '', $uri);

        $uri = $uri ?: '/';

        $action = $this->routes[$method][$uri] ?? null;

        if (!$action) {
            http_response_code(404);
            echo "404 - Página não encontrada<br>";
            echo "URI recebida: " . htmlspecialchars($uri);
            return;
        }

        if (is_array($action)) {
            [$controller, $methodName] = $action;
            $controller = new $controller();
            $controller->$methodName();
            return;
        }

        $action();
    }
}
