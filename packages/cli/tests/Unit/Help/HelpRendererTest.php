<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Help;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CliIO;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Help\HelpRenderer;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;

#[CoversClass(HelpRenderer::class)]
final class HelpRendererTest extends TestCase
{
    private HelpRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HelpRenderer();
    }

    private function fixturesDir(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/help';
    }

    /**
     * Assert rendered output matches a golden fixture file.
     * If the file doesn't exist, write it (bootstrap mode).
     */
    private function assertMatchesGolden(string $fixtureName, string $actual): void
    {
        $path = $this->fixturesDir() . '/' . $fixtureName . '.txt';

        if (!file_exists($path)) {
            file_put_contents($path, $actual);
            self::markTestIncomplete("Golden fixture '$fixtureName.txt' created — re-run to verify.");
        }

        $expected = file_get_contents($path);
        self::assertSame($expected, $actual, "Help output does not match golden fixture '$fixtureName.txt'.");
    }

    private function noopHandler(): \Closure
    {
        return static function (CliIO $io): int { return 0; };
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function noArgCommand(): void
    {
        $cmd = new CommandDefinition(
            name: 'list',
            description: 'List all registered commands.',
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);
        $this->assertMatchesGolden('no-arg-command', $output);
    }

    #[Test]
    public function commandWithRequiredOptionalAndArrayArgs(): void
    {
        $cmd = new CommandDefinition(
            name: 'make',
            description: 'Scaffold a new entity type.',
            arguments: [
                new ArgumentDefinition(name: 'type', mode: ArgumentMode::Required, description: 'Entity type machine name.'),
                new ArgumentDefinition(name: 'label', mode: ArgumentMode::Optional, default: null, description: 'Human-readable label.'),
                new ArgumentDefinition(name: 'fields', mode: ArgumentMode::Optional, isArray: true, description: 'Field names to add.'),
            ],
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);
        $this->assertMatchesGolden('required-optional-array-args', $output);
    }

    #[Test]
    public function commandWithAllOptionModes(): void
    {
        $cmd = new CommandDefinition(
            name: 'export',
            description: 'Export data in various formats.',
            options: [
                new OptionDefinition(name: 'format',  mode: OptionMode::Required,  description: 'Output format.', default: 'json'),
                new OptionDefinition(name: 'compress', mode: OptionMode::None,      description: 'Enable compression.'),
                new OptionDefinition(name: 'output',  mode: OptionMode::Optional,  description: 'Output path.'),
                new OptionDefinition(name: 'pretty',  mode: OptionMode::Negatable, description: 'Pretty-print output.'),
                new OptionDefinition(name: 'tag',     mode: OptionMode::Array_,    description: 'Tags to apply.'),
            ],
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);
        $this->assertMatchesGolden('all-option-modes', $output);
    }

    #[Test]
    public function commandWithShortcuts(): void
    {
        $cmd = new CommandDefinition(
            name: 'build',
            description: 'Build the project.',
            options: [
                new OptionDefinition(name: 'env',    shortcut: 'e', mode: OptionMode::Required, description: 'Target environment.', default: 'local'),
                new OptionDefinition(name: 'watch',  shortcut: 'w', mode: OptionMode::None,     description: 'Watch for changes.'),
            ],
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);
        $this->assertMatchesGolden('with-shortcuts', $output);
    }

    #[Test]
    public function optionsAreSortedAlphabetically(): void
    {
        $cmd = new CommandDefinition(
            name: 'z-test',
            description: 'Test option sort order.',
            options: [
                new OptionDefinition(name: 'zebra', mode: OptionMode::None, description: 'Z option.'),
                new OptionDefinition(name: 'apple', mode: OptionMode::None, description: 'A option.'),
                new OptionDefinition(name: 'mango', mode: OptionMode::None, description: 'M option.'),
            ],
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);

        // apple < mango < zebra before kernel options
        $applePos = strpos($output, '--apple');
        $mangoPos = strpos($output, '--mango');
        $zebraPos = strpos($output, '--zebra');

        self::assertIsInt($applePos);
        self::assertIsInt($mangoPos);
        self::assertIsInt($zebraPos);
        self::assertLessThan($mangoPos, $applePos);
        self::assertLessThan($zebraPos, $mangoPos);
    }

    #[Test]
    public function kernelFlagsAreAlwaysPresent(): void
    {
        $cmd = new CommandDefinition(
            name: 'simple',
            description: 'A simple command.',
            handler: $this->noopHandler(),
        );

        $output = $this->renderer->render($cmd);

        self::assertStringContainsString('--help', $output);
        self::assertStringContainsString('--verbose', $output);
        self::assertStringContainsString('--quiet', $output);
        self::assertStringContainsString('--no-interaction', $output);
        self::assertStringContainsString('--version', $output);
    }

    #[Test]
    public function outputIsDeterministic(): void
    {
        $cmd = new CommandDefinition(
            name: 'idempotent',
            description: 'Repeated render yields same bytes.',
            handler: $this->noopHandler(),
        );

        $first  = $this->renderer->render($cmd);
        $second = $this->renderer->render($cmd);

        self::assertSame($first, $second);
    }

    #[Test]
    public function commandWithNoSymfonyImports(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Help/HelpRenderer.php',
        );
        assert(is_string($source));

        self::assertStringNotContainsString(
            'Symfony\\',
            $source,
            'HelpRenderer must not import any Symfony classes.',
        );
    }
}
