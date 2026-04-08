<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\ErrorHandler\DevExceptionRenderer;
use Waaseyaa\ErrorHandler\SolutionProviderRegistry;

#[CoversClass(DevExceptionRenderer::class)]
final class DevExceptionRendererTest extends TestCase
{
    #[Test]
    public function render_includes_message_and_trace(): void
    {
        $renderer = new DevExceptionRenderer();
        $html = $renderer->render(new \RuntimeException('boom'));

        self::assertStringContainsString('RuntimeException', $html);
        self::assertStringContainsString('boom', $html);
        self::assertStringContainsString('Stack trace', $html);
    }

    #[Test]
    public function render_includes_solutions_when_registry_matches(): void
    {
        $registry = new SolutionProviderRegistry();
        $registry->register(new class implements \Waaseyaa\ErrorHandler\SolutionProviderInterface {
            public function canSolve(\Throwable $e): bool
            {
                return true;
            }

            public function getSolutions(\Throwable $e): array
            {
                return [new \Waaseyaa\ErrorHandler\SimpleSolution('Fix', 'Try turning it off and on again')];
            }
        });

        $renderer = new DevExceptionRenderer($registry);
        $html = $renderer->render(new \RuntimeException('x'));

        self::assertStringContainsString('Suggestions', $html);
        self::assertStringContainsString('Fix', $html);
    }
}
