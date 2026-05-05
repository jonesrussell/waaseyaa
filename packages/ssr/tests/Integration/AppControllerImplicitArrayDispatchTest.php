<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Http\AppController\AppControllerMethodInvoker;
use Waaseyaa\SSR\Http\AppController\AppInvocationContext;
use Waaseyaa\SSR\Tests\Support\RecordingLogger;
use Waaseyaa\User\AnonymousUser;

/**
 * Integration test asserting that a controller method using the historical
 * implicit array signature is dispatched successfully through
 * {@see AppControllerMethodInvoker} and emits exactly two deprecation log
 * entries (one for $params, one for $query) per request.
 *
 * Covers SC-001 and FR-007 of the dispatcher-array-param-compat-shim mission.
 */
#[CoversNothing]
final class AppControllerImplicitArrayDispatchTest extends TestCase
{
    #[Test]
    public function implicitSignatureDispatchesAndEmitsTwoDeprecationLines(): void
    {
        $logger = new RecordingLogger();
        $invoker = new AppControllerMethodInvoker(logger: $logger);

        $controller = new ImplicitSignatureFixtureController();
        $route = new Route('/show/{slug}');
        $request = Request::create('/show/foo');

        $ctx = new AppInvocationContext(
            request: $request,
            route: $route,
            account: new AnonymousUser(),
            entityTypeManager: new EntityTypeManager(new EventDispatcher()),
            twig: new Environment(new ArrayLoader([])),
            routeParams: ['slug' => 'foo'],
            query: ['debug' => '1'],
            gate: null,
            serviceResolver: null,
        );

        $result = $invoker->invoke(
            $controller,
            'show',
            $route,
            'fixture.show',
            $ctx,
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertSame('ok-foo', $result);

        $deprecations = array_values(array_filter(
            $logger->entries,
            static fn (array $entry): bool => $entry['level'] === 'notice'
                && str_contains($entry['message'], 'implicit array parameter'),
        ));

        self::assertCount(2, $deprecations);

        $byParam = [];
        foreach ($deprecations as $entry) {
            $byParam[$entry['context']['parameter_name']] = $entry;
        }

        self::assertArrayHasKey('params', $byParam);
        self::assertArrayHasKey('query', $byParam);
        self::assertSame(ImplicitSignatureFixtureController::class, $byParam['params']['context']['controller_class']);
        self::assertSame('show', $byParam['params']['context']['method_name']);
        self::assertSame('#[MapRoute]', $byParam['params']['context']['recommended_attribute']);
        self::assertSame('#[MapQuery]', $byParam['query']['context']['recommended_attribute']);
    }
}

/**
 * Fixture controller with the canonical pre-alpha.171 implicit-array signature
 * `(array $params, array $query, AccountInterface $account, Request $request)`.
 */
final class ImplicitSignatureFixtureController
{
    public function show(array $params, array $query, AccountInterface $account, Request $request): string
    {
        return 'ok-' . ($params['slug'] ?? 'none');
    }
}
