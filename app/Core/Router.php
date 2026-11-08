<?php
/**
 * Router — Lightweight pattern-based router with parameter extraction.
 *
 * Supports:
 *   - Static routes:  /api/items
 *   - Parameter routes: /api/items/{id}, /api/items/{id}/variants/{variantId}
 *   - Method-specific handlers
 *   - Per-route middleware (called before the handler)
 *
 * Routes are matched in the order they are registered, so register more specific
 * paths before wildcards.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable, middleware:array<int,callable>}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $middleware = []): self
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function get(string $p, callable $h, array $m = []): self    { return $this->add('GET', $p, $h, $m); }
    public function post(string $p, callable $h, array $m = []): self   { return $this->add('POST', $p, $h, $m); }
    public function put(string $p, callable $h, array $m = []): self    { return $this->add('PUT', $p, $h, $m); }
    public function patch(string $p, callable $h, array $m = []): self  { return $this->add('PATCH', $p, $h, $m); }
    public function delete(string $p, callable $h, array $m = []): self { return $this->add('DELETE', $p, $h, $m); }
    public function any(string $p, callable $h, array $m = []): self    { return $this->add('*', $p, $h, $m); }

    /**
     * Match the current request path against registered routes.
     * Returns [handler, params, middleware] or null on no match.
     */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }
            $params = $this->matchPattern($route['pattern'], $path);
            if ($params !== false) {
                return [
                    'handler'    => $route['handler'],
                    'params'     => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }
        return null;
    }

    /**
     * Match a pattern like "/api/items/{id}/variants/{variantId}" against a path.
     * Returns associative array of params on success, false on no match.
     */
    private function matchPattern(string $pattern, string $path): array|false
    {
        // Normalize: ensure leading slash, no trailing slash (except root)
        $pattern = '/' . ltrim($pattern, '/');
        $path    = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($pattern !== '/') {
            $pattern = rtrim($pattern, '/');
        }

        $patternParts = explode('/', $pattern);
        $pathParts    = explode('/', $path);

        if (count($patternParts) !== count($pathParts)) {
            return false;
        }

        $params = [];
        for ($i = 0; $i < count($patternParts); $i++) {
            $pp = $patternParts[$i];
            $ap = $pathParts[$i];

            if (preg_match('/^\{(\w+)\??\}$/', $pp, $m)) {
                // {param} or {param?} (optional - though optional not really useful here)
                $params[$m[1]] = $ap;
            } elseif ($pp !== $ap) {
                return false;
            }
        }
        return $params;
    }
}
