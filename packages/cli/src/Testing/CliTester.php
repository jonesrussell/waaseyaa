<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Testing;

use Psr\Container\ContainerInterface;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\CLI\Io\BufferedCliOutput;
use Waaseyaa\CLI\Io\ConsoleCliIO;
use Waaseyaa\CLI\Io\EmptyStdinSource;
use Waaseyaa\CLI\Io\StdinSource;
use Waaseyaa\CLI\OptionDefinition;
use Waaseyaa\CLI\OptionMode;
use Waaseyaa\CLI\Parser\ArgvParser;

/**
 * Test harness for native CLI commands.
 *
 * Wraps a CommandDefinition in an isolated dispatch path — no process boundary,
 * no real STDIN/STDOUT — so tests can assert exit codes and captured output.
 *
 * Usage:
 *   $tester = CliTester::for($definition, $container);
 *   $tester->execute(['foo', '--shout']);
 *   self::assertSame(0, $tester->getExitCode());
 *   self::assertStringContainsString('FOO', $tester->getStdout());
 *
 * Two consecutive execute() calls on the same instance yield independent results.
 *
 * Full contract: kitty-specs/native-cli-kernel-01KR2NR7/contracts/cli-tester.md
 */
final class CliTester
{
    private int $exitCode = 0;
    private BufferedCliOutput $stdoutBuffer;
    private BufferedCliOutput $stderrBuffer;
    /** Interleaved stdout+stderr bytes in write order. */
    private string $outputLog = '';

    private function __construct(
        private readonly CommandDefinition $definition,
        private readonly ContainerInterface $container,
        private readonly StdinSource $stdin,
    ) {
        $this->stdoutBuffer = new BufferedCliOutput();
        $this->stderrBuffer = new BufferedCliOutput();
    }

    /**
     * Create a tester for a single command definition.
     *
     * @param StdinSource|null $stdin Defaults to EmptyStdinSource (non-interactive).
     */
    public static function for(
        CommandDefinition $definition,
        ContainerInterface $container,
        ?StdinSource $stdin = null,
    ): self {
        return new self(
            definition: $definition,
            container: $container,
            stdin: $stdin ?? new EmptyStdinSource(),
        );
    }

    /**
     * Execute the command with a raw argv token array (excluding the command name).
     *
     * @param list<string> $argv e.g. ['positional', '--flag', '--opt=val']
     */
    public function execute(array $argv): self
    {
        $this->reset();

        $stdoutProxy  = $this->makeInterleaveProxy($this->stdoutBuffer, 'out');
        $stderrProxy  = $this->makeInterleaveProxy($this->stderrBuffer, 'err');

        $parser  = new ArgvParser();
        $parsed  = $parser->parse($argv, $this->definition);
        $verbose = (bool) ($parsed->options['verbose'] ?? false);

        $io = new ConsoleCliIO(
            input: $parsed,
            stdout: $stdoutProxy,
            stderr: $stderrProxy,
            stdin: $this->stdin,
            verbose: $verbose,
        );

        $handler = $this->resolveHandler();
        $this->exitCode = $handler($io);

        return $this;
    }

    /**
     * Execute using an associative map of argument/option name → value.
     *
     * Mirrors Symfony CommandTester::execute() ergonomics:
     *   ['name' => 'foo', '--shout' => true, '--count' => '3']
     *
     * @param array<string, mixed> $inputs
     */
    public function executeMap(array $inputs): self
    {
        return $this->execute($this->mapToArgv($inputs));
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStdout(): string
    {
        return $this->stdoutBuffer->getContents();
    }

    public function getStderr(): string
    {
        return $this->stderrBuffer->getContents();
    }

    /**
     * Stdout and stderr interleaved in write order.
     */
    public function getOutput(): string
    {
        return $this->outputLog;
    }

    // -------------------------------------------------------------------------

    private function reset(): void
    {
        $this->exitCode  = 0;
        $this->outputLog = '';
        $this->stdoutBuffer->reset();
        $this->stderrBuffer->reset();
    }

    /**
     * Resolve the handler closure, injecting the container for [FQN, method] handlers.
     */
    private function resolveHandler(): \Closure
    {
        $ref = $this->definition->handlerReference;

        if ($ref === null) {
            // Already a plain closure.
            return $this->definition->handler;
        }

        [$fqn, $method] = $ref;
        $instance = $this->container->get($fqn);

        return static fn(\Waaseyaa\CLI\CliIO $io): int => $instance->{$method}($io);
    }

    /**
     * Build an interleave-aware CliOutput proxy that writes to both a
     * BufferedCliOutput and the shared outputLog.
     */
    private function makeInterleaveProxy(BufferedCliOutput $target, string $_channel): \Waaseyaa\CLI\Io\CliOutput
    {
        return new class ($target, $this) implements \Waaseyaa\CLI\Io\CliOutput {
            public function __construct(
                private readonly BufferedCliOutput $buffer,
                private readonly CliTester $tester,
            ) {}

            public function write(string $text): void
            {
                $this->buffer->write($text);
                $this->tester->appendOutput($text);
            }

            public function writeln(string $text = ''): void
            {
                $this->buffer->writeln($text);
                $this->tester->appendOutput($text . "\n");
            }
        };
    }

    /**
     * Append to the interleaved output log. Called by the proxy.
     *
     * @internal
     */
    public function appendOutput(string $text): void
    {
        $this->outputLog .= $text;
    }

    /**
     * Convert an associative map to a flat argv token list.
     *
     * @param array<string, mixed> $inputs
     * @return list<string>
     */
    private function mapToArgv(array $inputs): array
    {
        $positionals = [];
        $flags       = [];

        // Bucket arguments vs options by inspecting the definition.
        $argNames = array_map(
            static fn(ArgumentDefinition $a) => $a->name,
            $this->definition->arguments,
        );

        foreach ($inputs as $key => $value) {
            // Keys without '--' prefix are argument names.
            if (!str_starts_with((string) $key, '--') && in_array($key, $argNames, true)) {
                // Array argument → multiple positional tokens.
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $positionals[] = (string) $v;
                    }
                } else {
                    $positionals[] = (string) $value;
                }
                continue;
            }

            // Options.
            $longName = ltrim((string) $key, '-');
            $optDef   = $this->findOption($longName);

            if ($optDef === null) {
                // Unknown option — pass through as raw token.
                if ($value === true) {
                    $flags[] = '--' . $longName;
                } elseif ($value !== false && $value !== null) {
                    $flags[] = '--' . $longName . '=' . $value;
                }
                continue;
            }

            match ($optDef->mode) {
                OptionMode::None => $value ? ($flags[] = '--' . $longName) : null,
                OptionMode::Negatable => $value
                    ? ($flags[] = '--' . $longName)
                    : ($flags[] = '--no-' . $longName),
                OptionMode::Required, OptionMode::Optional => is_array($value)
                    ? array_push($flags, ...array_map(
                        static fn($v) => '--' . $longName . '=' . $v,
                        $value,
                    ))
                    : ($flags[] = '--' . $longName . '=' . $value),
                OptionMode::Array_ => is_array($value)
                    ? array_push($flags, ...array_map(
                        static fn($v) => '--' . $longName . '=' . $v,
                        $value,
                    ))
                    : ($flags[] = '--' . $longName . '=' . $value),
            };
        }

        return [...$positionals, ...$flags];
    }

    private function findOption(string $longName): ?OptionDefinition
    {
        foreach ($this->definition->options as $opt) {
            if ($opt->name === $longName) {
                return $opt;
            }
        }

        return null;
    }
}
