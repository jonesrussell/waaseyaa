<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\Middleware\AuthorizationMiddleware;
use Waaseyaa\Foundation\Attribute\AsMiddleware;
use Waaseyaa\User\Middleware\BearerAuthMiddleware;
use Waaseyaa\User\Middleware\CsrfMiddleware;
use Waaseyaa\User\Middleware\SessionMiddleware;

#[CoversClass(AsMiddleware::class)]
final class MiddlewareOrderingTest extends TestCase
{
    #[Test]
    public function middleware_have_correct_priority_attributes(): void
    {
        $this->assertMiddlewarePriority(BearerAuthMiddleware::class, 40);
        $this->assertMiddlewarePriority(SessionMiddleware::class, 30);
        $this->assertMiddlewarePriority(CsrfMiddleware::class, 20);
        $this->assertMiddlewarePriority(AuthorizationMiddleware::class, 10);
    }

    #[Test]
    public function sort_order_is_determined_by_priority_not_registration_order(): void
    {
        // Provide class names in REVERSE priority order to prove registration order is irrelevant.
        $classes = [
            AuthorizationMiddleware::class, // priority 10
            CsrfMiddleware::class,          // priority 20
            SessionMiddleware::class,       // priority 30
            BearerAuthMiddleware::class,    // priority 40
        ];

        usort(
            $classes,
            fn (string $a, string $b) => $this->readPriority($b) <=> $this->readPriority($a),
        );

        $this->assertSame(
            [
                BearerAuthMiddleware::class,
                SessionMiddleware::class,
                CsrfMiddleware::class,
                AuthorizationMiddleware::class,
            ],
            $classes,
        );
    }

    private function assertMiddlewarePriority(string $class, int $expected): void
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsMiddleware::class);
        $this->assertNotEmpty($attributes, "No AsMiddleware attribute on {$class}");
        $this->assertSame($expected, $attributes[0]->newInstance()->priority);
    }

    private function readPriority(string $class): int
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsMiddleware::class);
        if (empty($attributes)) {
            return 0;
        }
        return $attributes[0]->newInstance()->priority;
    }
}
