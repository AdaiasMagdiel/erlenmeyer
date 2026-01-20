<?php

namespace AdaiasMagdiel\Erlenmeyer;

use AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use InvalidArgumentException;
use stdClass;

class Router
{
    private array $routes = [];
    private array $redirects = [];
    private string $routePattern = '/\/\[[a-zA-Z0-9\.\-_]+\]/';
    private string $paramPattern = '/([a-zA-Z0-9\.\-_]+)';
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function add(string $method, string $route, callable $action, array $middlewares = []): void
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'], true)) {
            $this->logger->log(LogLevel::ERROR, "Invalid HTTP method: $method for route: $route");
            throw new InvalidArgumentException("Invalid HTTP method: $method");
        }

        $formattedRoute = $this->parseRoute($route);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        preg_match_all('/\[([a-zA-Z0-9\.\-_]+)\]/', $route, $matches);
        $paramNames = $matches[1] ?? [];

        $this->routes[$method][$formattedRoute] = [
            'handler' => $action, // Stores the raw action, middleware application happens in App
            'middlewares' => $middlewares,
            'paramNames' => $paramNames
        ];
    }

    public function redirect(string $from, string $to, bool $permanent = false): void
    {
        // Normalize the 'from' URI to match request logic
        $from = strlen($from) > 1 ? rtrim($from, '/') : $from;

        $this->redirects[] = [
            'from' => $from,
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

        // Check Redirects
        foreach ($this->redirects as $redirect) {
            if ($normalizedUri === $redirect['from']) {
                return [
                    'type' => 'redirect',
                    'to' => $redirect['to'],
                    'status' => $redirect['permanent'] ? 301 : 302
                ];
            }
        }

        if (!isset($this->routes[$method])) {
            return null;
        }

        // Check Routes
        foreach ($this->routes[$method] as $routePattern => $routeData) {
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
