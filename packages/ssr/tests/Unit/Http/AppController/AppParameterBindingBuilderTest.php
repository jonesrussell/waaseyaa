<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Unit\Http\AppController;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\SSR\Http\AppController\AppParameterBindingBuilder;
use Waaseyaa\SSR\Http\AppController\AppParameterKind;
use Waaseyaa\SSR\Tests\Fixtures\AppController\AnnotatedFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\LegacyArrayParamsFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\LegacyArrayQueryFixture;
use Waaseyaa\SSR\Tests\Fixtures\AppController\UnboundArrayFixture;
use Waaseyaa\SSR\Tests\Support\RecordingLogger;

#[CoversClass(AppParameterBindingBuilder::class)]
final class AppParameterBindingBuilderTest extends TestCase
{
    #[Test]
    public function implicitArrayParamsShimsToMapRouteAndLogsOnce(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitParams'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        self::assertSame('notice', $logger->entries[0]['level']);
        self::assertStringContainsString('relies on the implicit-array shim', $logger->entries[0]['message']);
        self::assertSame('dispatcher.deprecation', $logger->entries[0]['context']['channel']);
        self::assertSame('implicit_array_shim', $logger->entries[0]['context']['event']);
        self::assertSame(BindingFixtureController::class, $logger->entries[0]['context']['controller_class']);
        self::assertSame('implicitParams', $logger->entries[0]['context']['method']);
        self::assertSame('params', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('MapRoute', $logger->entries[0]['context']['recommended_attribute']);
    }

    #[Test]
    public function implicitArrayQueryShimsToMapQueryAndLogsOnce(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitQuery'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapQuery, $specs[0]->kind);

        self::assertCount(1, $logger->entries);
        self::assertSame('notice', $logger->entries[0]['level']);
        self::assertStringContainsString('relies on the implicit-array shim', $logger->entries[0]['message']);
        self::assertSame('dispatcher.deprecation', $logger->entries[0]['context']['channel']);
        self::assertSame('implicit_array_shim', $logger->entries[0]['context']['event']);
        self::assertSame(BindingFixtureController::class, $logger->entries[0]['context']['controller_class']);
        self::assertSame('implicitQuery', $logger->entries[0]['context']['method']);
        self::assertSame('query', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('MapQuery', $logger->entries[0]['context']['recommended_attribute']);
    }

    #[Test]
    public function explicitMapRouteAttributeProducesNoDeprecation(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'explicitParams'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
        self::assertCount(0, $logger->entries);
    }

    #[Test]
    public function explicitMapQueryAttributeProducesNoDeprecation(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'explicitQuery'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapQuery, $specs[0]->kind);
        self::assertCount(0, $logger->entries);
    }

    #[Test]
    public function unannotatedArrayWithUnshimmedNameLogsAndBindsEmptyArray(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitHeaders'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        // Per post-#1390 dispatcher contract §3, an unannotated `array $X` whose
        // name is neither `params` nor `query` is classified as
        // `ImplicitEmptyArray` and bound to `[]` at invocation time. The spec
        // carries the kind; the binding pipeline materializes the empty array.
        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::ImplicitEmptyArray, $specs[0]->kind);

        // Exactly one `implicit_array_unbound` notice per (class::method::param)
        // per request (contract §5, §7).
        self::assertCount(1, $logger->entries);
        self::assertSame('notice', $logger->entries[0]['level']);
        self::assertSame('dispatcher.deprecation', $logger->entries[0]['context']['channel']);
        self::assertSame('implicit_array_unbound', $logger->entries[0]['context']['event']);
        self::assertSame(BindingFixtureController::class, $logger->entries[0]['context']['controller_class']);
        self::assertSame('implicitHeaders', $logger->entries[0]['context']['method']);
        self::assertSame('headers', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('', $logger->entries[0]['context']['recommended_attribute']);
    }

    #[Test]
    public function shimAppliesPerParameterWhenOnlyParamsPresent(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitParams'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
        self::assertCount(1, $logger->entries);
    }

    #[Test]
    public function shimAppliesPerParameterWhenOnlyQueryPresent(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitQuery'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapQuery, $specs[0]->kind);
        self::assertCount(1, $logger->entries);
    }

    #[Test]
    public function shimAppliesToNullableArrayParams(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'nullableParams'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
        self::assertCount(1, $logger->entries);
    }

    #[Test]
    public function builderConstructedWithoutLoggerDoesNotCrash(): void
    {
        $builder = new AppParameterBindingBuilder();

        $specs = $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitParams'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        self::assertCount(1, $specs);
        self::assertSame(AppParameterKind::MapRoute, $specs[0]->kind);
    }

    /*
     * --------------------------------------------------------------------
     * WP03 T011 — required test method names per the contract.
     *
     * The five tests below exercise the same behaviours as the cycle-2
     * tests above but use the standalone WP03 fixture controllers under
     * `tests/Fixtures/AppController/` and add the per-(class::method)
     * dedup invariants from the post-#1390 dispatcher contract §7.
     * --------------------------------------------------------------------
     */

    #[Test]
    public function testClassifiesAnnotatedParamsWithoutEmittingNotice(): void
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

    #[Test]
    public function testClassifiesImplicitArrayParamsAsRouteAndEmitsOneNotice(): void
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
        self::assertStringContainsString('relies on the implicit-array shim', $entry['message']);
        self::assertSame('dispatcher.deprecation', $entry['context']['channel']);
        self::assertSame('implicit_array_shim', $entry['context']['event']);
        self::assertSame(LegacyArrayParamsFixture::class, $entry['context']['controller_class']);
        self::assertSame('show', $entry['context']['method']);
        self::assertSame('params', $entry['context']['parameter_name']);
        self::assertSame('MapRoute', $entry['context']['recommended_attribute']);
    }

    #[Test]
    public function testClassifiesImplicitArrayQueryAsQueryAndEmitsOneNotice(): void
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
        self::assertStringContainsString('relies on the implicit-array shim', $entry['message']);
        self::assertSame('dispatcher.deprecation', $entry['context']['channel']);
        self::assertSame('implicit_array_shim', $entry['context']['event']);
        self::assertSame(LegacyArrayQueryFixture::class, $entry['context']['controller_class']);
        self::assertSame('show', $entry['context']['method']);
        self::assertSame('query', $entry['context']['parameter_name']);
        self::assertSame('MapQuery', $entry['context']['recommended_attribute']);
    }

    #[Test]
    public function testDedupSuppressesRepeatedRegistration(): void
    {
        // Per contract §7, the dedup map lives on the binding-builder instance.
        // Re-classifying the same `(class::method::parameter)` triple within a
        // single builder lifetime must emit only one notice.
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
        $third = $builder->build(
            $reflection,
            $route,
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );

        // Each invocation must still classify correctly — dedup affects the
        // notice channel, not the binding-spec output.
        self::assertCount(1, $first);
        self::assertCount(1, $second);
        self::assertCount(1, $third);
        self::assertSame(AppParameterKind::MapRoute, $first[0]->kind);
        self::assertSame(AppParameterKind::MapRoute, $second[0]->kind);
        self::assertSame(AppParameterKind::MapRoute, $third[0]->kind);

        // Exactly one notice across three invocations of the same triple.
        self::assertCount(1, $logger->entries);
    }

    #[Test]
    public function testNonShimParametersDoNotTouchDedupMap(): void
    {
        // NFR-001 fast-path: parameters that are neither `implicit_array_shim`
        // nor `implicit_array_unbound` must not contribute entries to the
        // dedup map. We assert this indirectly by showing that processing an
        // annotated method first does not consume a dedup slot used by a
        // later legacy method with the same parameter name — i.e. the legacy
        // method still emits exactly one notice.
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        // Step 1: annotated fixture — no notices, no dedup writes.
        $annotated = $builder->build(
            new \ReflectionMethod(AnnotatedFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );
        self::assertCount(2, $annotated);
        self::assertCount(0, $logger->entries);

        // Step 2: legacy implicit-array fixture — one notice, one dedup write.
        $legacy = $builder->build(
            new \ReflectionMethod(LegacyArrayParamsFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );
        self::assertCount(1, $legacy);
        self::assertCount(1, $logger->entries);
        self::assertSame('implicit_array_shim', $logger->entries[0]['context']['event']);

        // Step 3: re-run the legacy fixture — dedup must suppress the second
        // notice, confirming the legacy entry is the only one recorded.
        $builder->build(
            new \ReflectionMethod(LegacyArrayParamsFixture::class, 'show'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );
        self::assertCount(1, $logger->entries);
    }

    #[Test]
    public function testUnboundArrayFixtureBindsImplicitEmptyArrayAndEmitsNotice(): void
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
}

/**
 * Fixture controller methods reflected by the tests above. Action parameter
 * shapes match the historical Waaseyaa controller signatures plus the
 * post-shim attribute-annotated forms.
 */
final class BindingFixtureController
{
    public function implicitParams(array $params): string
    {
        return 'ok';
    }

    public function implicitQuery(array $query): string
    {
        return 'ok';
    }

    public function explicitParams(#[MapRoute] array $params): string
    {
        return 'ok';
    }

    public function explicitQuery(#[MapQuery] array $query): string
    {
        return 'ok';
    }

    public function implicitHeaders(array $headers): string
    {
        return 'ok';
    }

    public function nullableParams(?array $params = null): string
    {
        return 'ok';
    }
}

