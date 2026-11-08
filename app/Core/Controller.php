<?php
/**
 * Controller — base class with shared helpers for API controllers.
 */

declare(strict_types=1);

namespace Insophinia\POS\Core;

use Insophinia\POS\Lib\Auth;
use Insophinia\POS\Lib\Security;

abstract class Controller
{
    protected Request $request;
    /** Authenticated user payload (set by AuthMiddleware) */
    protected ?array $user = null;
    protected ?string $token = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setUser(?array $user, ?string $token = null): void
    {
        $this->user = $user;
        $this->token = $token;
    }

    /** Pull auth user/token from Request attributes (set by AuthMiddleware). */
    public function applyAuth(Request $request): void
    {
        $this->user  = $request->getAttribute('auth_user');
        $this->token = $request->getAttribute('auth_token');
    }

    /** Called by App::run after instantiation to apply auth from request attributes. */
    public function setPropertyIfAuth(Request $request): void
    {
        $this->applyAuth($request);
    }

    /** Helper: get current user ID. */
    protected function userId(): ?string
    {
        return $this->user['user_id'] ?? null;
    }

    /** Helper: get current user role. */
    protected function userRole(): ?string
    {
        return $this->user['role'] ?? null;
    }

    /** Helper: get current username. */
    protected function username(): ?string
    {
        return $this->user['username'] ?? null;
    }

    /** Helper: check role permission. */
    protected function can(string $permission): bool
    {
        $role = $this->userRole();
        if ($role === null) return false;
        return \Insophinia\POS\Lib\Permissions::has($role, $permission);
    }

    /** Helper: pagination params from query string. */
    protected function pagination(): array
    {
        $page  = max(1, (int)($this->request->query('page', 1)));
        $limit = min(1000, max(1, (int)($this->request->query('limit', 20))));
        $sortBy = $this->request->query('sortBy', 'created_at');
        $sortOrder = strtolower((string)($this->request->query('sortOrder', 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $search = $this->request->query('search');
        return [
            'page'      => $page,
            'limit'     => $limit,
            'offset'    => ($page - 1) * $limit,
            'sortBy'    => $sortBy,
            'sortOrder' => $sortOrder,
            'search'    => is_string($search) && $search !== '' ? $search : null,
        ];
    }

    /** Helper: build a sortable column clause (whitelist to prevent SQL injection). */
    protected function sortColumn(array $allowed, string $default = 'created_at'): string
    {
        $sortBy = $this->request->query('sortBy', $default);
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = $default;
        }
        return (string)$sortBy;
    }
}
