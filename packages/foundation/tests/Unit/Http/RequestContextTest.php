<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Http\RequestContext;

#[CoversClass(RequestContext::class)]
final class RequestContextTest extends TestCase
{
    #[Test]
    public function defaultsAreEmptyAndNull(): void
    {
        $context = new RequestContext();

        self::assertSame([], $context->roles());
        self::assertNull($context->accountId());
        self::assertNull($context->activeLangcode());
        self::assertNull($context->interfaceLangcode());
        self::assertSame([], $context->getQueryParams());
    }

    #[Test]
    public function accessorsRoundTripConstructorArguments(): void
    {
        $context = new RequestContext(
            roles: ['editor', 'admin'],
            accountId: 42,
            activeLangcode: 'en-CA',
            interfaceLangcode: 'fr-CA',
            queryParams: ['q' => 'rabbits', 'page' => '3'],
        );

        self::assertSame(['editor', 'admin'], $context->roles());
        self::assertSame(42, $context->accountId());
        self::assertSame('en-CA', $context->activeLangcode());
        self::assertSame('fr-CA', $context->interfaceLangcode());
        self::assertSame(['q' => 'rabbits', 'page' => '3'], $context->getQueryParams());
    }

    #[Test]
    public function rolesOrderIsPreservedAtThisLayer(): void
    {
        // Determinism is the consumer's responsibility — RequestContext itself
        // preserves whatever order the caller passes in.
        $context = new RequestContext(roles: ['zeta', 'alpha', 'mu']);

        self::assertSame(['zeta', 'alpha', 'mu'], $context->roles());
    }

    #[Test]
    public function queryParamsAreAssociativeAndPreserveValues(): void
    {
        $context = new RequestContext(queryParams: [
            'category' => 'news',
            'tag' => 'a b',
            'empty' => '',
        ]);

        self::assertSame([
            'category' => 'news',
            'tag' => 'a b',
            'empty' => '',
        ], $context->getQueryParams());
    }
}
