<?php
/**
 * HttpException — domain-level exception that maps to a specific HTTP status.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

class HttpException extends \RuntimeException
{
    private ?array $errors;

    public function __construct(string $message, int $statusCode = 400, ?array $errors = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public static function badRequest(string $message = 'Bad request', ?array $errors = null): self
    {
        return new self($message, 400, $errors);
    }

    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = 'Insufficient permissions'): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self($message, 404);
    }

    public static function conflict(string $message = 'Conflict'): self
    {
        return new self($message, 409);
    }

    public static function tooMany(string $message = 'Rate limit exceeded'): self
    {
        return new self($message, 429);
    }

    public static function serverError(string $message = 'Internal server error'): self
    {
        return new self($message, 500);
    }
}
