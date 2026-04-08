<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface;

use InvalidArgumentException;

/**
 * Canonical path patterns for the Admin Surface HTTP API.
 *
 * Single source for route registration and URL generation on the PHP side.
 * The admin SPA mirrors relative segments in packages/admin/app/runtime/adminSurfaceRoutes.ts.
 */
final class AdminSurfaceRoutePaths
{
    public const PATH_SESSION = '/admin/_surface/session';

    public const PATH_CATALOG = '/admin/_surface/catalog';

    public const PATH_LIST = '/admin/_surface/{type}';

    public const PATH_GET = '/admin/_surface/{type}/{id}';

    public const PATH_ACTION = '/admin/_surface/{type}/action/{action}';

    /**
     * Build a concrete URL path for a named admin surface route (path only, no scheme or host).
     *
     * @param array{type?: string, id?: string, action?: string} $parameters
     */
    public static function generate(string $name, array $parameters = []): string
    {
        return match ($name) {
            'admin_surface.session' => self::PATH_SESSION,
            'admin_surface.catalog' => self::PATH_CATALOG,
            'admin_surface.list' => self::pathList(self::requireString($parameters, 'type', $name)),
            'admin_surface.get' => self::pathGet(
                self::requireString($parameters, 'type', $name),
                self::requireString($parameters, 'id', $name),
            ),
            'admin_surface.action' => self::pathAction(
                self::requireString($parameters, 'type', $name),
                self::requireString($parameters, 'action', $name),
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unknown admin surface route name: %s', $name),
            ),
        };
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private static function requireString(array $parameters, string $key, string $routeName): string
    {
        if (!isset($parameters[$key]) || !\is_string($parameters[$key]) || $parameters[$key] === '') {
            throw new InvalidArgumentException(sprintf(
                'Missing or invalid required parameter "%s" for route "%s".',
                $key,
                $routeName,
            ));
        }

        return $parameters[$key];
    }

    private static function pathList(string $type): string
    {
        return '/admin/_surface/' . rawurlencode($type);
    }

    private static function pathGet(string $type, string $id): string
    {
        return '/admin/_surface/' . rawurlencode($type) . '/' . rawurlencode($id);
    }

    private static function pathAction(string $type, string $action): string
    {
        return '/admin/_surface/' . rawurlencode($type) . '/action/' . rawurlencode($action);
    }
}
