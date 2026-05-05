<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Waaseyaa\SSR\Http\AppController\AppParameterBindingBuilder;
use Waaseyaa\SSR\Http\AppController\AppParameterKind;
use Waaseyaa\SSR\Tests\Fixtures\AppController\AnnotatedFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\LegacyArrayParamsFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\LegacyArrayQueryFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\MixedFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\UnboundArrayFixture;
use Waaseyaa\SSR\Tests\Support\RecordingLogger;

/**
 * Dispatcher deprecation contract tests for the post-#1390 implicit-array shim.
 *
 * Each test maps 1:1 to a row in `contracts/dispatcher-deprecation-contract.md`
 * §"Test contract" (mission `post-1390-dispatcher-reconciliation-01KQTTJS`).
 * The seven cases verify the locked log schema (§5), the per-(class::method::param)
 * dedup invariant (§7), and the `ImplicitEmptyArray` binding for unbound
 * implicit-array parameters (§3).
 */
#[CoversNothing]
final class DispatcherDeprecationContractTest extends TestCase
{
    /**
     * Test 1 — Implicit `array $params` resolves to `MapRoute` and emits a
     * single `implicit_array_shim` notice with `recommended_attribute=MapRoute`.
     */
    #[Test]
    public function testImplicitArrayParamsResolveAndEmitNotice(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(LegacyArrayParamsFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertSame('notice', $entry['level']);
        self::assertSame('dispatcher.deprecation', $entry['context']['channel']);
        self::assertSame('implicit_array_shim', $entry['context']['event']);
        self::assertSame(LegacyArrayParamsFixture::class, $entry['context']['controller_class']);
        self::assertSame('show', $entry['context']['method']);
        self::assertSame('params', $entry['context']['parameter_name']);
        self::assertSame('MapRoute', $entry['context']['recommended_attribute']);
    }

    /**
     * Test 2 — Implicit `array $query` resolves to `MapQuery` and emits a single
     * `implicit_array_shim` notice with `recommended_attribute=MapQuery`.
     */
    #[Test]
    public function testImplicitArrayQueryResolvesAndEmitsNotice(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(LegacyArrayQueryFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapQuery, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertSame('notice', $entry['level']);
        self::assertSame('dispatcher.deprecation', $entry['context']['channel']);
        self::assertSame('implicit_array_shim', $entry['context']['event']);
        self::assertSame(LegacyArrayQueryFixture::class, $entry['context']['controller_class']);
        self::assertSame('show', $entry['context']['method']);
        self::assertSame('query', $entry['context']['parameter_name']);
        self::assertSame('MapQuery', $entry['context']['recommended_attribute']);
    }

    /**
     * Test 3 — Annotated `#[MapRoute] array $params` and `#[MapQuery] array $query`
     * produce the corresponding binding kinds without any deprecation notice.
     */
    #[Test]
    public function testAnnotatedAttributesEmitNoNotice(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(AnnotatedFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(2, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
        self::assertSame(AppParameterKind::MapQuery, $specs[1]->kind);

        self::assertCount(0, $logger->entries);
    }

    /**
     * Test 4 — Mixed signature: legacy `array $params`, legacy `array $query`,
     * `AccountInterface $account`, and `HttpRequest $request`. Exactly two
     * `implicit_array_shim` notices fire — one per implicit-array parameter —
     * and the typed services resolve as `FrameworkService` bindings (built-in
     * service types do not require a service resolver).
     */
    #[Test]
    public function testMixedSignatureResolvesAndEmitsTwoNotices(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(MixedFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        // Binding-spec shape: [MapRoute, MapQuery, FrameworkService, FrameworkService].
        self::assertCount(4, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
        self::assertSame(AppParameterKind::MapQuery, $specs[1]->kind);
        self::assertSame(AppParameterKind::FrameworkService, $specs[2]->kind);
        self::assertSame(\Waaseyaa\Access\AccountInterface::class, $specs[2]->serviceClass);
        self::assertSame(AppParameterKind::FrameworkService, $specs[3]->kind);
        self::assertSame(\Symfony\Component\HttpFoundation\Request::class, $specs[3]->serviceClass);

        // Exactly two notices — one for `params`, one for `query`.
        self::assertCount(2, $logger->entries);

        $paramsEntry = $logger->entries[0];
        self::assertSame('implicit_array_shim', $paramsEntry['context']['event']);
        self::assertSame('params', $paramsEntry['context']['parameter_name']);
        self::assertSame('MapRoute', $paramsEntry['context']['recommended_attribute']);
        self::assertSame(MixedFixture::class, $paramsEntry['context']['controller_class']);

        $queryEntry = $logger->entries[1];
        self::assertSame('implicit_array_shim', $queryEntry['context']['event']);
        self::assertSame('query', $queryEntry['context']['parameter_name']);
        self::assertSame('MapQuery', $queryEntry['context']['recommended_attribute']);
        self::assertSame(MixedFixture::class, $queryEntry['context']['controller_class']);
    }

    /**
     * Test 5 — Query-only legacy signature: `array $query` alone (no sibling
     * `array $params`) still triggers the `MapQuery` shim and a single notice.
     */
    #[Test]
    public function testQueryOnlyShimWorks(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(LegacyArrayQueryFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapQuery, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        self::assertSame('implicit_array_shim', $logger->entries[0]['context']['event']);
        self::assertSame('query', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('MapQuery', $logger->entries[0]['context']['recommended_attribute']);
    }

    /**
     * Test 6 — Unbound implicit-array parameter (`array $somethingElse`)
     * resolves to `ImplicitEmptyArray` and emits a single
     * `implicit_array_unbound` notice with `recommended_attribute=''` per
     * post-#1390 dispatcher contract §3.
     */
    #[Test]
    public function testImplicitArrayUnboundEmitsBoundlessNotice(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(UnboundArrayFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        // The parameter classifies as ImplicitEmptyArray; argument resolution
        // (downstream of the binding-spec) materializes `[]` per contract §3.
        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::ImplicitEmptyArray, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertSame('notice', $entry['level']);
        self::assertSame('dispatcher.deprecation', $entry['context']['channel']);
        self::assertSame('implicit_array_unbound', $entry['context']['event']);
        self::assertSame(UnboundArrayFixture::class, $entry['context']['controller_class']);
        self::assertSame('show', $entry['context']['method']);
        self::assertSame('somethingElse', $entry['context']['parameter_name']);
        self::assertSame('', $entry['context']['recommended_attribute']);
    }

    /**
     * Test 7 — Per-request dedup (contract §7): two binding-pipeline
     * invocations against the same `(class::method::parameter)` triple within
     * a single builder lifetime emit exactly one notice in total.
     */
    #[Test]
    public function testDedupHoldsAcrossSecondInvocation(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $reflection = new \ReflectionMethod(LegacyArrayParamsFixture::class, 'show');
        $route = new Route('/test');

        $first = $builder->build(
            $reflection,
            $route,
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );
        $second = $builder->build(
            $reflection,
            $route,
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        // Both invocations classify identically — dedup is a notice-channel
        // concern, not a binding-spec concern.
        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertSame(AppParameterKind::MapRoute, $first[0]->kind);
        self::assertSame(AppParameterKind::MapRoute, $second[0]->kind);

        // Exactly one notice across the two invocations.
        self::assertCount(1, $logger->entries);
        self::assertSame('implicit_array_shim', $logger->entries[0]['context']['event']);
        self::assertSame('params', $logger->entries[0]['context']['parameter_name']);
    }
}
