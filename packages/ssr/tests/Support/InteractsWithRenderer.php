<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Tests\Support;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

trait InteractsWithRenderer
{
    /**
     * Render a Twig template string with the given context.
     *
     * @param array<string, mixed> $context
     */
    protected function render(string $template, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['__inline__' => $template]));

        return $twig->render('__inline__', $context);
    }

    /**
     * Render a Twig template file from a directory.
     *
     * @param array<string, mixed> $context
     */
    protected function renderFile(string $templateDir, string $templateName, array $context = []): string
    {
        $twig = new Environment(new FilesystemLoader($templateDir));

        return $twig->render($templateName, $context);
    }

    /**
     * Assert that rendered template output contains the given string.
     *
     * @param array<string, mixed> $context
     */
    protected function assertRenderContains(string $needle, string $template, array $context = []): void
    {
        $html = $this->render($template, $context);

        $this->assertStringContainsString(
            $needle,
            $html,
            "Expected rendered output to contain '{$needle}'.",
        );
    }

    /**
     * Assert that rendered template output matches the given regex pattern.
     *
     * @param array<string, mixed> $context
     */
    protected function assertRenderMatches(string $pattern, string $template, array $context = []): void
    {
        $html = $this->render($template, $context);

        $this->assertMatchesRegularExpression(
            $pattern,
            $html,
            "Expected rendered output to match pattern '{$pattern}'.",
        );
    }
}
