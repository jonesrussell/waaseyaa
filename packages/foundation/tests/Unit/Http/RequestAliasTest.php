<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Verifies the C-002 class_alias from mission 1107-api-symfony-decoupling.
 *
 * Asserts that Waaseyaa\Foundation\Http\Request resolves to the same class
 * as Symfony's HttpFoundation\Request. App code can type-hint the Waaseyaa
 * name without losing access to any Symfony Request behavior.
 */
#[CoversNothing]
final class RequestAliasTest extends TestCase
{
    #[Test]
    public function alias_resolves_to_symfony_request_class(): void
    {
        $this->assertTrue(
            class_exists('Waaseyaa\\Foundation\\Http\\Request'),
            'Waaseyaa\\Foundation\\Http\\Request must be autoloadable',
        );

        $this->assertSame(
            SymfonyRequest::class,
            (new \ReflectionClass('Waaseyaa\\Foundation\\Http\\Request'))->getName(),
            'The Waaseyaa name must reflect to Symfony\\Component\\HttpFoundation\\Request',
        );
    }

    #[Test]
    public function instance_via_alias_is_a_symfony_request(): void
    {
        $alias = 'Waaseyaa\\Foundation\\Http\\Request';

        /** @var SymfonyRequest $instance */
        $instance = new $alias();

        $this->assertInstanceOf(SymfonyRequest::class, $instance);
    }
}
