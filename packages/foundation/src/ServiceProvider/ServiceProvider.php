<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\ServiceProvider;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Foundation\Http\Router\DomainRouterInterface;
use Waaseyaa\Foundation\Kernel\HttpKernel;

abstract class ServiceProvider implements ServiceProviderInterface
{
    protected string $projectRoot = '';

    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, class-string> */
    protected array $manifestFormatters = [];

    /** @var array<string, array{concrete: string|callable, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    /** @var array<string, list<string>> */
    private array $tags = [];

    /** @var list<array{entityType: \Waaseyaa\Entity\EntityTypeInterface, registrant: class-string}> */
    private array $entityTypeRegistrations = [];

    /** @var (\Closure(string): ?object)|null */
    private ?\Closure $kernelResolver = null;

    abstract public function register(): void;

    public function boot(): void {}

    public function routes(\Waaseyaa\Routing\WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void {}

    /**
     * Return plugin CLI commands to register with the console application.
     *
     * @return list<\Symfony\Component\Console\Command\Command>
     */
    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        return [];
    }

    /**
     * Return GraphQL mutation overrides.
     *
     * Each key is a mutation name (e.g. 'updateScheduleEntry').
     * Each value has optional 'args' (merged with defaults) and 'resolve' (replaces default).
     *
     * @return array<string, array{args?: array<string, mixed>, resolve?: callable}>
     */
    /**
     * @return array<string, array{args?: array<string, mixed>, resolve?: callable}>
     */
    public function graphqlMutationOverrides(\Waaseyaa\Entity\EntityTypeManager $entityTypeManager): array
    {
        return [];
    }

    /**
     * Return HTTP middleware instances to register with the kernel pipeline.
     *
     * Use #[AsMiddleware] on each class to set pipeline and priority.
     *
     * @return list<\Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface>
     */
    public function middleware(\Waaseyaa\Entity\EntityTypeManager $entityTypeManager): array
    {
        return [];
    }

    /**
     * Contribute domain routers after foundation built-ins up to MCP and before BroadcastRouter.
     *
     * @return iterable<DomainRouterInterface>
     */
    public function httpDomainRouters(?HttpKernel $httpKernel = null): iterable
    {
        return [];
    }

    /**
     * Register render-cache entity listeners. The second argument is the render bin backend
     * from the kernel (CacheBackendInterface); SSR wraps it in RenderCache.
     */
    public function registerRenderCacheListeners(EventDispatcherInterface $dispatcher, mixed $renderCacheBackend): void {}

    /**
     * Late HTTP wiring after database caches exist (e.g. SsrPageHandler construction).
     */
    public function configureHttpKernel(HttpKernel $kernel): void {}

    public function provides(): array
    {
        return [];
    }

    public function isDeferred(): bool
    {
        return $this->provides() !== [];
    }

    /**
     * Provide kernel context to providers before register()/boot().
     *
     * @param array<string, mixed> $config
     * @param array<string, class-string> $manifestFormatters
     */
    public function setKernelContext(string $projectRoot, array $config, array $manifestFormatters = []): void
    {
        $this->projectRoot = $projectRoot;
        $this->config = $config;
        $this->manifestFormatters = $manifestFormatters;
    }

    /**
     * Set a fallback resolver for kernel-level services (e.g. EntityTypeManager).
     *
     * @param \Closure(string): ?object $resolver
     */
    public function setKernelResolver(\Closure $resolver): void
    {
        $this->kernelResolver = $resolver;
    }

    protected function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => true];
    }

    protected function bind(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => false];
    }

    protected function tag(string $abstract, string $tag): void
    {
        $this->tags[$tag] ??= [];
        $this->tags[$tag][] = $abstract;
    }

    /**
     * Resolve a binding registered via singleton() or bind().
     */
    public function resolve(string $abstract): mixed
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            if ($this->kernelResolver !== null) {
                $resolved = ($this->kernelResolver)($abstract);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
            throw new \RuntimeException("No binding registered for {$abstract}.");
        }

        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        $instance = is_callable($concrete) ? $concrete() : new $concrete();

        if ($binding['shared']) {
            $this->resolved[$abstract] = $instance;
        }

        return $instance;
    }

    protected function entityType(\Waaseyaa\Entity\EntityTypeInterface $entityType): void
    {
        $this->entityTypeRegistrations[] = [
            'entityType' => $entityType,
            'registrant' => static::class,
        ];
    }

    /** @return array<string, array{concrete: string|callable, shared: bool}> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /** @return array<string, list<string>> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @return list<\Waaseyaa\Entity\EntityTypeInterface> */
    public function getEntityTypes(): array
    {
        return array_map(
            static fn(array $registration): \Waaseyaa\Entity\EntityTypeInterface => $registration['entityType'],
            $this->entityTypeRegistrations,
        );
    }

    /** @return list<array{entityType: \Waaseyaa\Entity\EntityTypeInterface, registrant: class-string}> */
    public function getEntityTypeRegistrations(): array
    {
        return $this->entityTypeRegistrations;
    }
}
