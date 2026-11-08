<?php
/**
 * Request — wraps the incoming HTTP request.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $cookies;
    public array $server;
    public array $headers;
    /** @var array<string,mixed> Per-request attributes (set by middleware) */
    private array $attributes = [];
    private ?array $jsonBody = null;
    private ?string $rawBody = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query   = $_GET;
        $this->post    = $_POST;
        $this->cookies = $_COOKIE;
        $this->server  = $_SERVER;

        // Path (supports both /index.php?path=/foo and direct /foo via .htaccess)
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        $this->path = $path;

        // Headers
        $this->headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $this->headers[$name] = $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', strtolower($k));
                $this->headers[$name] = $v;
            }
        }
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        // Also support ?token= for PDF downloads
        return $this->query('token');
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->json();
        return $body[$key] ?? $default;
    }

    /** Parse JSON body (or form body) as an array. */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }
        $raw = $this->rawBody();
        $contentType = $this->header('content-type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $this->jsonBody = is_array($decoded) ? $decoded : [];
        } elseif (str_contains($contentType, 'application/x-www-form-urlencoded') || str_contains($contentType, 'multipart/form-data')) {
            $this->jsonBody = $this->post;
        } else {
            // Try JSON anyway
            $decoded = json_decode($raw, true);
            $this->jsonBody = is_array($decoded) ? $decoded : $this->post;
        }
        return $this->jsonBody;
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    public function contentLength(): int
    {
        $len = $this->header('content-length');
        return $len !== null ? (int)$len : strlen($this->rawBody());
    }

    public function isPost(): bool { return $this->method === 'POST'; }
    public function isGet(): bool  { return $this->method === 'GET'; }
    public function isPut(): bool  { return $this->method === 'PUT'; }
    public function isPatch(): bool{ return $this->method === 'PATCH'; }
    public function isDelete(): bool { return $this->method === 'DELETE'; }

    /** Set a per-request attribute (used by middleware). */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /** Get a per-request attribute. */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
