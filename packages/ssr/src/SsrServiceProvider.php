<?php

declare(strict_types=1);

namespace Waaseyaa\SSR;

use Symfony\Component\EventDispatcher\EventDispatcherInterface as ComponentEventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Waaseyaa\Access\ErrorPageRendererInterface;
use Waaseyaa\Access\Gate\EntityAccessGate;
use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Entity\Event\EntityEvent;
use Waaseyaa\Entity\Event\EntityEvents;
use Waaseyaa\Foundation\Http\LanguagePathStripperInterface;
use Waaseyaa\Foundation\Kernel\HttpKernel;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\SSR\Flash\Flash;
use Waaseyaa\SSR\Flash\FlashMessageService;
use Waaseyaa\SSR\Http\Router\AppControllerRouter;
use Waaseyaa\SSR\Http\Router\SsrRouter;
use Waaseyaa\SSR\Twig\FlashTwigExtension;

final class SsrServiceProvider extends ServiceProvider implements LanguagePathStripperInterface
{
    private static ?Environment $twigEnvironment = null;
    private static ?FieldFormatterRegistry $formatterRegistry = null;

    private ?RenderCache $renderCache = null;

    private ?SsrPageHandler $ssrPageHandler = null;

    public function register(): void
    {
        $this->singleton(ErrorPageRendererInterface::class, function (): ErrorPageRendererInterface {
            $twig = self::getTwigEnvironment();
            if ($twig !== null) {
                return new TwigErrorPageRenderer($twig);
            }

            return new class implements ErrorPageRendererInterface {
                public function render(int $statusCode, string $title, string $detail, Request $request): ?Response
                {
                    return null;
                }
            };
        });
    }

    public function boot(): void
    {
        if ($this->projectRoot === '') {
            return;
        }

        self::$twigEnvironment = ThemeServiceProvider::getTwigEnvironment()
            ?? self::createTwigEnvironment($this->projectRoot, $this->config);
        self::$formatterRegistry = new FieldFormatterRegistry($this->manifestFormatters);

        $flashService = new FlashMessageService();
        Flash::setService($flashService);
        if (self::$twigEnvironment !== null) {
            self::$twigEnvironment->addExtension(new FlashTwigExtension($flashService));
        }
    }

    public function registerRenderCacheListeners(EventDispatcherInterface $dispatcher, mixed $renderCacheBackend): void
    {
        if (!$renderCacheBackend instanceof CacheBackendInterface) {
            return;
        }

        $this->renderCache = new RenderCache($renderCacheBackend);
        $renderCache = $this->renderCache;

        $invalidate = function (object $event) use ($renderCache): void {
            if (!$event instanceof EntityEvent) {
                return;
            }

            $entityType = $event->entity->getEntityTypeId();
            $renderCache->invalidateEntity(
                $entityType,
                $event->entity->id(),
            );

            if (in_array($entityType, [
                'relationship',
                'node',
                'genealogy_person',
                'genealogy_family',
                'genealogy_event',
                'genealogy_tree',
            ], true)) {
                $renderCache->invalidateEntity('node', null);
                $renderCache->invalidateEntity('relationship', null);
                foreach (['genealogy_person', 'genealogy_family', 'genealogy_event', 'genealogy_tree'] as $genealogyType) {
                    $renderCache->invalidateEntity($genealogyType, null);
                }
            }
        };

        if (!$dispatcher instanceof ComponentEventDispatcherInterface) {
            return;
        }

        $dispatcher->addListener(EntityEvents::POST_SAVE->value, $invalidate);
        $dispatcher->addListener(EntityEvents::POST_DELETE->value, $invalidate);
    }

    public function configureHttpKernel(HttpKernel $kernel): void
    {
        if ($this->renderCache === null) {
            return;
        }

        $this->ssrPageHandler = new SsrPageHandler(
            entityTypeManager: $kernel->getEntityTypeManager(),
            database: $kernel->getDatabase(),
            renderCache: $this->renderCache,
            cacheConfigResolver: new \Waaseyaa\Cache\CacheConfigResolver($kernel->getConfig()),
            discoveryHandler: $kernel->getDiscoveryApiHandler(),
            projectRoot: $kernel->getProjectRoot(),
            config: $kernel->getConfig(),
            manifest: $kernel->getManifest(),
            serviceResolver: $kernel->getHttpServiceResolver(),
            gate: new EntityAccessGate($kernel->getAccessHandler()),
            inertiaFullPageRenderer: $kernel->getInertiaFullPageRenderer(),
        );
    }

    /**
     * @return iterable<SsrRouter|AppControllerRouter>
     */
    public function httpDomainRouters(?HttpKernel $httpKernel = null): iterable
    {
        if ($this->ssrPageHandler === null) {
            return [];
        }

        return [
            new SsrRouter($this->ssrPageHandler),
            new AppControllerRouter(
                $this->ssrPageHandler,
                $this->resolve(ErrorPageRendererInterface::class),
            ),
        ];
    }

    public function stripLanguagePrefixForRouting(string $path): string
    {
        return $this->ssrPageHandler?->getLanguageResolver()?->stripLanguagePrefixForRouting($path) ?? $path;
    }

    public static function getTwigEnvironment(): ?Environment
    {
        return self::$twigEnvironment;
    }

    public static function getFormatterRegistry(): ?FieldFormatterRegistry
    {
        return self::$formatterRegistry;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createTwigEnvironment(string $projectRoot, array $config = []): Environment
    {
        return ThemeServiceProvider::createTwigEnvironment($projectRoot, $config);
    }
}
