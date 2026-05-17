# Contract — `CliIO`

**Mission**: `native-cli-kernel-01KR2NR7`

```php
namespace Waaseyaa\Cli\Io;

final class CliIO
{
    public function __construct(
        private readonly ParsedInput $input,
        private readonly CliOutput $stdout,
        private readonly CliOutput $stderr,
        private readonly StdinSource $stdin,
        private readonly bool $verbose,
    ) {}

    public function getArgument(string $name): string|int|float|bool|array|null;
    public function getOption(string $name): string|int|float|bool|array|null;
    public function hasOption(string $name): bool;
    public function getArguments(): array;
    public function getOptions(): array;

    public function write(string $text): void;          // stdout, NO newline
    public function writeln(string $text = ''): void;   // stdout + LF
    public function error(string $text): void;          // stderr + LF

    public function ask(string $question, ?string $default = null): string;
    public function confirm(string $question, bool $default = false): bool;

    public function isVerbose(): bool;
    public function isInteractive(): bool;
}
```

## Behavioural contract

### Argument & option access

- `getArgument($name)` returns the parsed value, OR the argument's declared default, OR `null`. Throws `UnknownArgumentException` if `$name` is not declared on the command.
- `getOption($name)` mirrors the above for options.
- `hasOption($name)` returns `true` iff the option was passed on argv (regardless of value). False for unset options whose defaults apply.
- `getArguments()` returns the full associative map — declared names mapped to their resolved values (defaults applied).
- `getOptions()` mirrors `getArguments()` for options.

### Output

- `write()`/`writeln()` write to stdout via the injected `CliOutput`. Writers MUST NOT swallow exceptions.
- `error()` writes to stderr.
- No automatic colorisation. Commands that want ANSI emit the escape sequences themselves; the kernel does not inject any.

### Prompts

- `ask($question, $default)`:
  - If `isInteractive()` is `true`: write `$question + ' '` to stderr, read a line from stdin, return trimmed value, or `$default ?? ''` on EOF / empty line.
  - If `isInteractive()` is `false`: write `waaseyaa-cli: stdin is not a tty; using default for prompt "<question>"` to stderr exactly once, return `$default ?? ''`. NEVER block on stdin.
- `confirm($question, $default)`:
  - Same TTY logic. On TTY, accept `y`, `yes`, `Y`, `YES` as `true`; `n`, `no`, `N`, `NO` as `false`; anything else returns `$default`. EOF returns `$default`.

### Verbose / interactive flags

- `isVerbose()` reflects whether `--verbose`/`-v` was on argv.
- `isInteractive()` reflects TTY detection per [research §R-05](../research.md#r-05--tty-detection--non-tty-prompts).

## Test surface

- `CliIOTest`:
  - argument/option getters return defaults when absent
  - `hasOption` distinguishes set-to-default from explicitly-passed
  - `write`/`writeln`/`error` route to correct streams
  - `ask` returns default when stdin not interactive
  - `confirm` accepts y/yes/n/no, falls through to default on garbage
- `BufferedCliOutputTest`: capture buffer is exactly the bytes written.
