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
        // Sem isso, o navegador podia servir uma versao antiga de uma
        // pagina (ex: lista de Ativos) direto do cache local em vez de
        // buscar a atual no servidor -- confirmado ao vivo: a URL sem
        // query string ficava presa numa versao anterior enquanto a
        // mesma tela com "?tipo=&status=&busca=" (outra chave de cache
        // pro navegador) mostrava o estado certo. Todo o painel mostra
        // dado dinamico/sensivel, nunca deveria ser cacheado.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

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
