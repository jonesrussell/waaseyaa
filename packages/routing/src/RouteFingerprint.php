<?php

declare(strict_types=1);

namespace Waaseyaa\Routing;

use Symfony\Component\Routing\Route;

/**
 * Stable fingerprint for route metadata that affects app-controller argument binding.
 */
final class RouteFingerprint
{
    public const string BINDINGS_OPTION = '_waaseyaa_app_bindings';

    /**
     * @return non-empty-string
     */
    public static function hash(Route $route): string
    {
        $parameters = $route->getOption('parameters');
        if (!is_array($parameters)) {
            $parameters = [];
        }
        $bindings = $route->getOption(self::BINDINGS_OPTION);
        if (!is_array($bindings)) {
            $bindings = [];
        }

        $payload = [
            'path' => $route->getPath(),
            'methods' => $route->getMethods(),
            'parameters' => $parameters,
            'bindings' => $bindings,
            'defaults' => $route->getDefaults(),
        ];

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
