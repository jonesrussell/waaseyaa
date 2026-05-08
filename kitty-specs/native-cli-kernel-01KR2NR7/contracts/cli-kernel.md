# Contract — `CliKernel`

**Mission**: `native-cli-kernel-01KR2NR7`

```php
namespace Waaseyaa\Cli;

final class CliKernel
{
    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly ContainerInterface $container,
        private readonly Help\HelpRenderer $help,
        private readonly Io\CliOutput $stdout,
        private readonly Io\CliOutput $stderr,
        private readonly Io\StdinSource $stdin,
        private readonly ?LoggerInterface $logger = null,
    );

    /**
     * @param list<string> $argv  The argv WITHOUT the script name (i.e. $_SERVER['argv'] sliced from index 1).
     * @return int                Exit code (0 success, 1 handler failure, 2 parse error, 130 SIGINT).
     */
    public function run(array $argv): int;
}
```

## Behavioural contract

1. `run([])` → emit the listing of all registered commands to stdout, exit `0`.
2. `run(['--help'])` → same as `run([])`.
3. `run(['--version'])` → emit framework version to stdout, exit `0`.
4. `run(['unknown-name'])` → emit `Unknown command: unknown-name` to stderr, exit `2`.
5. `run(['health:check', '--help'])` → emit help block for `health:check` to stdout, exit `0`. Handler is NOT invoked.
6. `run(['health:check', ...])` → resolve command, parse remaining argv against its arg/option declarations, build `CliIO`, resolve handler closure, invoke it, return its int.
7. Parse errors during step 6 → emit single-line error to stderr, exit `2`. No stack trace unless `--verbose` was present in argv.
8. Uncaught exceptions during handler execution → emit `<class>: <message>` to stderr, exit `1`. With `--verbose`, also emit the full trace.
9. SIGINT during handler execution → return `130` (kernel registers a signal handler; PHP must have `pcntl` for the handler to fire — otherwise the OS default exits with whatever it returns).

## Reentrancy

Not reentrant. One `CliKernel` instance, one `run()` call per process.

## Side effects

- Writes to stdout/stderr via the injected `CliOutput` instances.
- Reads `STDIN` only via the injected `StdinSource` (which `CliIO::ask`/`confirm` proxy through).
- May invoke `pcntl_signal()` if `pcntl` is loaded.
- Does NOT call `exit()`. The caller (`CliApplication::main()`) does.

## Test surface

- `CliKernelTest`: dispatches a fake `CommandDefinition` via in-memory registry, asserts exit code, captured stdout/stderr.
- `ArgvParserTest`: full edge-case matrix (FR-002).
- `HelpRendererTest`: golden-snapshot comparisons.
- Snapshot integration tests: byte-equality vs pre-cut Symfony fixtures, one per public command (FR-015).
