<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Introspection\PublicSurface;

use Symfony\Component\Routing\RouteCollection;
use Waaseyaa\Bimaaji\Graph\GraphSection;
use Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface;

final class PublicSurfaceProvider implements GraphSectionProviderInterface
{
    public function __construct(
        private readonly RouteCollection $routes,
    ) {}

    public function getKey(): string
    {
        return 'public_surface';
    }

    public function provide(): GraphSection
    {
        $data = [];

        foreach ($this->routes->all() as $name => $route) {
            $options = $route->getOptions();

            $isRendered = ($options['_render'] ?? false) === true;
            $isPublic = ($options['_public'] ?? false) === true;

            if (!$isRendered && !$isPublic) {
                continue;
            }

            $data[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods(),
                'auth' => $this->classifyAuth($options),
            ];
        }

        return new GraphSection(
            key: 'public_surface',
            version: '1.0',
            data: $data,
        );
    }

    private function classifyAuth(array $options): string
    {
        if (\array_key_exists('_permission', $options)
            || \array_key_exists('_role', $options)
            || \array_key_exists('_gate', $options)) {
            return 'restricted';
        }

        if (($options['_public'] ?? false) === true) {
            return 'public';
        }

        if (($options['_authenticated'] ?? false) === true) {
            return 'authenticated';
        }

        if (\array_key_exists('_session', $options)) {
            return 'session';
        }

        return 'unknown';
    }
}
