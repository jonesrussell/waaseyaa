# Contract — `CliTester`

**Mission**: `native-cli-kernel-01KR2NR7`

```php
namespace Waaseyaa\Cli\Testing;

final class CliTester
{
    public static function for(
        \Waaseyaa\Cli\CommandDefinition $definition,
        \Psr\Container\ContainerInterface $container,
        ?\Waaseyaa\Cli\Io\StdinSource $stdin = null,
    ): self;

    public function execute(array $argv): self;
    public function executeMap(array $inputs): self;

    public function getExitCode(): int;
    public function getStdout(): string;
    public function getStderr(): string;
    public function getOutput(): string;   // stdout + stderr in write order
}
```

## Behavioural contract

### Construction

- `CliTester::for($def, $container, $stdin = null)`:
  - Validates `$def` (re-runs `CommandDefinition` invariants in case the caller hand-built one).
  - Builds a single-command `CommandRegistry` containing only `$def`.
  - Wires a `BufferedCliOutput` for stdout and stderr.
  - Wires `$stdin` (defaults to `EmptyStdinSource` — yields nothing, makes `ask`/`confirm` use defaults silently per non-TTY behaviour).
  - Constructs an internal `CliKernel` with the above.

### Invocation

- `execute(['--name=foo', 'positional'])` runs the command exactly as the kernel would given that argv slice. Returns `$this` for chaining.
- `executeMap(['name' => 'foo', 'positional_arg' => 'bar'])` translates the associative map into argv tokens before delegating to `execute()`. Useful for tests that mirror the original `Symfony\Component\Console\Tester\CommandTester::execute()` ergonomics.

### Captures

- `getExitCode()` is the kernel's int return value.
- `getStdout()` / `getStderr()` are the captured buffers as strings.
- `getOutput()` returns interleaved bytes in write order (so a test can assert sequencing).

### Determinism

- Two consecutive `execute()` calls on the same `CliTester` instance MUST yield independent results. (The buffers are reset on `execute()` entry; the registry is unchanged.)
- A test that asserts stdout/stderr content MUST do so AFTER `execute()` returns.

## Migration mapping from Symfony's `CommandTester`

| Symfony API | Waaseyaa `CliTester` |
|---|---|
| `$tester = new CommandTester($command);` | `$tester = CliTester::for($definition, $container);` |
| `$tester->execute(['--opt' => 'v', 'arg' => 'x']);` | `$tester->executeMap(['--opt' => 'v', 'arg' => 'x']);` |
| `$tester->getStatusCode()` | `$tester->getExitCode()` |
| `$tester->getDisplay()` | `$tester->getStdout()` (or `getOutput()` for stdout+stderr) |
| `$tester->setInputs(['yes', 'foo'])` | Pass a `StringQueueStdinSource(['yes', 'foo'])` to `CliTester::for(..., stdin: …)`. |

The migration is a mechanical rewrite per command test, captured by the `tests_fixtures: rename` action in `occurrence_map.yaml`.

## Test surface

- `CliTesterTest`:
  - Round-trip a fake `CommandDefinition`, assert exit code, stdout, stderr.
  - `executeMap` translates correctly for required, optional, array, negatable options.
  - `StringQueueStdinSource` feeds prompts.
  - Two `execute()` calls on the same tester are independent.
