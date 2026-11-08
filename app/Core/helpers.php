<?php
/**
 * INSOPHINIA POS v7 — Helper functions
 * Globally available utility functions.
 */

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Read an environment variable with a default fallback.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? getenv($key);
        if ($val === false || $val === null) {
            return $default;
        }
        return $val;
    }
}

if (!function_exists('config')) {
    /**
     * Get a config value via dot notation. e.g. config('db.host')
     */
    function config(string $path, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../../config/config.php';
        }
        $keys = explode('.', $path);
        $cur = $config;
        foreach ($keys as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return $default;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }
}

if (!function_exists('e')) {
    /** HTML-escape a string for output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('json_response')) {
    /** Send a JSON response and exit. */
    function json_response(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('client_ip')) {
    /** Get the client IP, respecting X-Forwarded-For. */
    function client_ip(): string
    {
        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd !== '') {
            $parts = explode(',', $fwd);
            return trim($parts[0]);
        }
        return $_SERVER['HTTP_X_REAL_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}

if (!function_exists('user_agent')) {
    function user_agent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('now_ms')) {
    /** Current time in milliseconds. */
    function now_ms(): int
    {
        return (int)(microtime(true) * 1000);
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_starts_with($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
