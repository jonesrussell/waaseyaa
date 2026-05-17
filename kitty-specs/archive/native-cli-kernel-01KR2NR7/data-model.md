# Phase 1 Data Model — Native CLI Kernel

**Mission**: `native-cli-kernel-01KR2NR7`
**Date**: 2026-05-08

This file defines the in-memory record shapes the runtime exchanges. None
of these are persisted; they live for the duration of one process.

---

## `ArgumentMode` (enum)

```php
enum ArgumentMode
{
    case Required;
    case Optional;
}
```

## `OptionMode` (enum)

```php
enum OptionMode
{
    case None;        // boolean flag, no value
    case Required;    // value mandatory if option present
    case Optional;    // value optional; bare presence yields null
    case Array_;      // accumulates list, repeatable
    case Negatable;   // boolean toggleable via --no-foo
}
```

## `ArgumentDefinition`

```php
final readonly class ArgumentDefinition
{
    public function __construct(
        public string $name,                        // machine name, snake_case
        public ArgumentMode $mode = ArgumentMode::Required,
        public string $description = '',
        public string|int|float|bool|array|null $default = null,
        public bool $isArray = false,               // collects all remaining tokens
    ) {}
}
```

**Invariants**:
- `name` MUST match `/^[a-z][a-z0-9_]*$/`.
- If `isArray === true`, this MUST be the last argument in a `CommandDefinition`'s argument list.
- If `mode === Required` and `isArray === false`, `default` MUST be `null`.

## `OptionDefinition`

```php
final readonly class OptionDefinition
{
    public function __construct(
        public string $name,                        // long form, no leading --
        public ?string $shortcut = null,            // single character, no leading -
        public OptionMode $mode = OptionMode::None,
        public string $description = '',
        public string|int|float|bool|array|null $default = null,
    ) {}
}
```

**Invariants**:
- `name` matches `/^[a-z][a-z0-9-]*$/` and is NOT one of the reserved kernel flags `help`, `verbose`, `quiet`, `no-interaction`, `version`.
- `shortcut`, when present, is exactly one ASCII letter and NOT one of `h`, `v`, `q`.
- For `OptionMode::Negatable`, `name` MUST NOT itself start with `no-`.
- For `OptionMode::Array_`, `default` is `[]` if not supplied.
- For `OptionMode::None` and `OptionMode::Negatable`, `default` is normalised to `false` and `null` respectively if user passes `null`.

## `CommandDefinition`

```php
final readonly class CommandDefinition
{
    /** @var list<ArgumentDefinition> */
    public array $arguments;

    /** @var list<OptionDefinition> */
    public array $options;

    /** @var \Closure(CliIO): int */
    public \Closure $handler;

    public function __construct(
        public string $name,                       // public command name, e.g. "health:check"
        public string $description,
        array $arguments = [],
        array $options = [],
        \Closure|array $handler = null,            // closure OR [class-fqn, method] pair
    ) { /* normalises arrays + handler via Closure::fromCallable through container */ }
}
```

**Invariants**:
- `name` matches `/^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)*$/` (e.g. `migrate`, `health:check`, `make:migration`).
- All `arguments[*]->name` are unique.
- All `options[*]->name` and `options[*]->shortcut` are unique within the command.
- At most one `array`-mode argument, and if present it is last.
- `handler` is required and resolves to a `\Closure(CliIO):int` after normalisation.

## `CommandRegistry`

```php
final class CommandRegistry
{
    /** @var array<string, CommandDefinition> */
    private array $commands = [];

    public function register(CommandDefinition $command): void;       // throws on duplicate name
    public function get(string $name): ?CommandDefinition;
    public function all(): array;                                     // sorted by name
    public function names(): array;                                   // list<string>, sorted
}
```

**Invariants**:
- Once `CliKernel` begins dispatching, the registry is treated as immutable. Mutation after dispatch is an internal error.

## `ParsedInput`

```php
final readonly class ParsedInput
{
    public function __construct(
        /** @var array<string, scalar|array|null> */
        public array $arguments,
        /** @var array<string, scalar|array|null> */
        public array $options,
        /** @var list<string> */
        public array $rawArgv,                     // captured for diagnostics
    ) {}
}
```

## `ParseError`

```php
final readonly class ParseError
{
    public function __construct(
        public ParseErrorKind $kind,
        public string $message,
        public ?string $offendingToken = null,
    ) {}
}

enum ParseErrorKind
{
    case UnknownCommand;
    case UnknownOption;
    case MissingRequiredArgument;
    case MissingRequiredOptionValue;
    case TypeCoercion;
    case TooManyArguments;
}
```

`ParseError` is thrown wrapped in a `ParseException` that the kernel catches at the top level, formats to stderr, and exits with code `2`.

## `CliIO`

See [`contracts/cli-io.md`](./contracts/cli-io.md) for the full surface. Briefly:

```php
final class CliIO
{
    public function getArgument(string $name): string|int|float|bool|array|null;
    public function getOption(string $name): string|int|float|bool|array|null;
    public function hasOption(string $name): bool;
    /** @return array<string, mixed> */ public function getArguments(): array;
    /** @return array<string, mixed> */ public function getOptions(): array;
    public function write(string $text): void;        // stdout, no trailing newline
    public function writeln(string $text = ''): void; // stdout + LF
    public function error(string $text): void;        // stderr + LF
    public function ask(string $question, ?string $default = null): string;
    public function confirm(string $question, bool $default = false): bool;
    public function isVerbose(): bool;                // --verbose flag was set
    public function isInteractive(): bool;            // STDIN is a TTY
}
```

## `HasNativeCommandsInterface`

```php
namespace Waaseyaa\Foundation\ServiceProvider\Capability;

interface HasNativeCommandsInterface
{
    /** @return iterable<\Waaseyaa\Cli\CommandDefinition> */
    public function nativeCommands(): iterable;
}
```

Lives in Layer 0. Imports nothing from Symfony Console. The `\Waaseyaa\Cli\CommandDefinition` reference is via FQN so the interface file does not even `use` the CLI namespace — Layer 0 stays free of Layer 6 imports.

> **Layer note**: `HasNativeCommandsInterface` is a capability *contract*; it returns `iterable<CommandDefinition>` but does not import the CommandDefinition class — the runtime resolves the FQN via reflection at provider iteration time. Identical pattern to how `HasMiddlewareInterface` returns middleware FQNs without importing the middleware classes themselves.

## State transitions

The runtime is stateless per-invocation. The only state-shaped flow:

```
argv[]
  → ArgvParser::parse()
  → ParsedInput | ParseError
  → CliKernel::dispatch()
  → handler closure invoked
  → int exit code
```

No persistent storage, no cross-invocation cache (the `CommandRegistry` is rebuilt per process boot from provider iteration).
