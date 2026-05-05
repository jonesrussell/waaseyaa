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
use Waaseyaa\SSR\Http\AppController\Exception\InvalidAppControllerBindingException;
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
        self::assertStringContainsString('implicit array parameter', $logger->entries[0]['message']);
        self::assertSame(BindingFixtureController::class, $logger->entries[0]['context']['controller_class']);
        self::assertSame('implicitParams', $logger->entries[0]['context']['method_name']);
        self::assertSame('params', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('#[MapRoute]', $logger->entries[0]['context']['recommended_attribute']);
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
        self::assertSame('query', $logger->entries[0]['context']['parameter_name']);
        self::assertSame('#[MapQuery]', $logger->entries[0]['context']['recommended_attribute']);
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
    public function unannotatedArrayWithUnshimmedNameStillThrows(): void
    {
        $logger = new RecordingLogger();
        $builder = new AppParameterBindingBuilder($logger);

        $this->expectException(InvalidAppControllerBindingException::class);
        $this->expectExceptionMessage('array parameters require #[MapRoute] or #[MapQuery]');

        $builder->build(
            new \ReflectionMethod(BindingFixtureController::class, 'implicitHeaders'),
            new Route('/test'),
            strict: false,
            gate: null,
            serviceResolver: null,
            customResolvers: [],
        );
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

