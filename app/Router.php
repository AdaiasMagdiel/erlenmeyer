<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;
use stdClass;

class Router
{
    private array $routes = [];
    private array $staticRoutes = [];
    private array $redirects = [];
    private string $routePattern = '/\/\[[a-zA-Z0-9\.\-_]+\]/';
    private string $paramPattern = '/([a-zA-Z0-9\.\-_]+)';

    public function add(string $method, string $route, callable $action, array $middlewares = []): void
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'], true)) {
            throw new InvalidArgumentException("Invalid HTTP method: $method");
        }

        preg_match_all('/\[([a-zA-Z0-9\.\-_]+)\]/', $route, $matches);
        $paramNames = $matches[1] ?? [];

        $normalizedRoute = strlen($route) > 1 ? rtrim($route, '/') : $route;

        if (empty($paramNames)) {
            $this->staticRoutes[$method][$normalizedRoute] = [
                'handler' => $action,
                'middlewares' => $middlewares,
            ];
        } else {
            $formattedRoute = $this->parseRoute($route);
            $parts = explode('/', ltrim($normalizedRoute, '/'));
            $prefix = str_contains($parts[0], '[') ? '' : $parts[0];
            $this->routes[$method][$prefix][$formattedRoute] = [
                'handler' => $action,
                'middlewares' => $middlewares,
                'paramNames' => $paramNames
            ];
        }
    }

    public function redirect(string $from, string $to, bool $permanent = false): void
    {
        $from = strlen($from) > 1 ? rtrim($from, '/') : $from;

        $this->redirects[$from] = [
            'to' => $to,
            'permanent' => $permanent
        ];
    }

    /**
     * Tries to match a method and URI to a registered route or redirect.
     *
     * @return array|null Returns array with ['type' => 'route'|'redirect', ...] or null if not found.
     */
    public function match(string $method, string $uri): ?array
    {
        $normalizedUri = strlen($uri) > 1 ? rtrim($uri, '/') : $uri;

        // Check Redirects — O(1) direct lookup
        if (isset($this->redirects[$normalizedUri])) {
            $redirect = $this->redirects[$normalizedUri];
            return [
                'type' => 'redirect',
                'to' => $redirect['to'],
                'status' => $redirect['permanent'] ? 301 : 302
            ];
        }

        // Check Static Routes — O(1) direct lookup
        if (isset($this->staticRoutes[$method][$normalizedUri])) {
            $data = $this->staticRoutes[$method][$normalizedUri];
            return [
                'type' => 'route',
                'handler' => $data['handler'],
                'middlewares' => $data['middlewares'],
                'params' => new stdClass()
            ];
        }

        if (!isset($this->routes[$method])) {
            return null;
        }

        // Check Dynamic Routes — scan only matching prefix group + fallback
        $uriPrefix = explode('/', ltrim($normalizedUri, '/'))[0];
        $candidates = $this->routes[$method][$uriPrefix] ?? [];
        if ($uriPrefix !== '') {
            $candidates += $this->routes[$method][''] ?? [];
        }

        foreach ($candidates as $routePattern => $routeData) {
            $params = [];
            if (preg_match($routePattern, $normalizedUri, $params)) {
                array_shift($params); // Remove full match

                $paramObj = new stdClass();
                foreach ($routeData['paramNames'] as $i => $name) {
                    $paramObj->$name = $params[$i] ?? null;
                }

                return [
                    'type' => 'route',
                    'handler' => $routeData['handler'],
                    'middlewares' => $routeData['middlewares'],
                    'params' => $paramObj
                ];
            }
        }

        return null;
    }

    private function parseRoute(string $route): string
    {
        $route = strlen($route) > 1 ? rtrim($route, '/') : $route;
        $route = preg_replace($this->routePattern, $this->paramPattern, $route);
        $route = str_replace('/', '\/', $route);
        return "/^{$route}$/";
    }
}
