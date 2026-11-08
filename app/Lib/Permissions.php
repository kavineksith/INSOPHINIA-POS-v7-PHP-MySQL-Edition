<?php
/**
 * Permissions — RBAC matrix for the 4 roles and 33 permissions.
 * Mirrors src/lib/permissions.ts.
 */

declare(strict_types=1);

namespace Insophinia\POS\Lib;

class Permissions
{
    public const PERMISSIONS = [
        'view:dashboard',
        'view:items', 'manage:items',
        'view:categories', 'manage:categories',
        'view:inventory', 'manage:inventory',
        'view:promotions', 'manage:promotions',
        'view:customers', 'manage:customers',
        'view:billing', 'manage:billing',
        'view:bills',
        'view:reports', 'export:reports',
        'view:users', 'manage:users',
        'view:settings', 'manage:settings',
        'view:security', 'manage:security',
        'view:logs',
        'authorize:returns',
        'authorize:loyalty',
        'manage:system_lock',
        'force_delete:any', // hard delete — master_admin only
        'view:warranties', 'manage:warranties',
        'view:repairs', 'manage:repairs',
        'view:marketing', 'manage:marketing',
        'view:templates', 'manage:templates',
    ];

    public const ROLE_PERMISSIONS = [
        'master_admin' => [
            'view:dashboard',
            'view:items', 'manage:items',
            'view:categories', 'manage:categories',
            'view:inventory', 'manage:inventory',
            'view:promotions', 'manage:promotions',
            'view:customers', 'manage:customers',
            'view:billing', 'manage:billing',
            'view:bills',
            'view:reports', 'export:reports',
            'view:users', 'manage:users',
            'view:settings', 'manage:settings',
            'view:security', 'manage:security',
            'view:logs',
            'authorize:returns', 'authorize:loyalty', 'manage:system_lock',
            'force_delete:any',
            'view:warranties', 'manage:warranties',
            'view:repairs', 'manage:repairs',
            'view:marketing', 'manage:marketing',
            'view:templates', 'manage:templates',
        ],
        'admin' => [
            'view:dashboard',
            'view:items', 'manage:items',
            'view:categories', 'manage:categories',
            'view:inventory', 'manage:inventory',
            'view:promotions', 'manage:promotions',
            'view:customers', 'manage:customers',
            'view:billing', 'manage:billing',
            'view:bills',
            'view:reports', 'export:reports',
            'view:users', 'manage:users',
            'view:settings', 'manage:settings',
            'view:security', 'manage:security',
            'view:logs',
            'authorize:returns', 'authorize:loyalty',
            'view:warranties', 'manage:warranties',
            'view:repairs', 'manage:repairs',
            'view:marketing', 'manage:marketing',
            'view:templates', 'manage:templates',
        ],
        'supervisor' => [
            'view:dashboard',
            'view:items', 'manage:items',
            'view:categories', 'manage:categories',
            'view:inventory', 'manage:inventory',
            'view:promotions', 'manage:promotions',
            'view:customers', 'manage:customers',
            'view:billing', 'manage:billing',
            'view:bills',
            'view:reports', 'export:reports',
            'authorize:returns', 'authorize:loyalty',
            'view:warranties', 'manage:warranties',
            'view:repairs', 'manage:repairs',
            'view:marketing',
            'view:templates',
        ],
        'cashier' => [
            'view:dashboard',
            'view:items',
            'view:categories',
            'view:inventory',
            'view:customers', 'manage:customers',
            'view:billing', 'manage:billing',
            'view:bills',
            'view:warranties',
            'view:repairs',
        ],
    ];

    public static function has(string $role, string $permission): bool
    {
        $perms = self::ROLE_PERMISSIONS[$role] ?? [];
        return in_array($permission, $perms, true);
    }

    public static function hasAll(string $role, array $required): bool
    {
        foreach ($required as $p) {
            if (!self::has($role, $p)) return false;
        }
        return true;
    }

    public static function hasAny(string $role, array $required): bool
    {
        foreach ($required as $p) {
            if (self::has($role, $p)) return true;
        }
        return false;
    }

    /** Returns the list of permissions for a role. */
    public static function forRole(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Can the user perform a HARD (permanent) delete?
     * Only master_admin role is granted this permission.
     */
    public static function canForceDelete(string $role): bool
    {
        return self::has($role, 'force_delete:any');
    }

    /**
     * Can the user perform a SOFT delete on this resource?
     * Requires the matching manage:* permission.
     */
    public static function canSoftDelete(string $role, string $resource): bool
    {
        return self::has($role, "manage:{$resource}");
    }
}
