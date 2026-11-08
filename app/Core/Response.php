<?php
/**
 * Response — Uniform JSON response builder matching the Next.js API shape:
 *   { success: bool, message?: string, data?: ..., errors?: {...}, pagination?: {...} }
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

class Response
{
    private array $headers = [];
    private ?string $cookieHeader = null;

    public static function success(mixed $data = null, string $message = '', int $status = 200): self
    {
        $payload = ['success' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        return new self($payload, $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null, mixed $data = null): self
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        return new self($payload, $status);
    }

    public static function paginated(array $items, int $page, int $limit, int $total, string $message = ''): self
    {
        $pages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        $payload = [
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => $pages,
                ],
            ],
        ];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        return new self($payload, 200);
    }

    public function __construct(
        public array $payload,
        public int $status = 200
    ) {}

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $setCookieValue): self
    {
        $this->cookieHeader = $setCookieValue;
        return $this;
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        if ($this->cookieHeader !== null) {
            header('Set-Cookie: ' . $this->cookieHeader);
        }
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Return payload array (for testing or composition). */
    public function toArray(): array
    {
        return $this->payload;
    }
}
