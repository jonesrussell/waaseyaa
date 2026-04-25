<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Http\AppController;

/**
 * Immutable description of how one controller action parameter is resolved.
 */
final readonly class AppParameterBindingSpec
{
    public const int NO_RESOLVER = -1;

    public function __construct(
        public int $index,
        public AppParameterKind $kind,
        public ?string $serviceClass = null,
        public ?string $routeKey = null,
        public ?string $entityTypeId = null,
        public ?string $entityPhpClass = null,
        public ?string $boundClass = null,
        public ?string $scalarKind = null,
        public ?string $enumClass = null,
        public int $customResolverIndex = self::NO_RESOLVER,
    ) {}
}
