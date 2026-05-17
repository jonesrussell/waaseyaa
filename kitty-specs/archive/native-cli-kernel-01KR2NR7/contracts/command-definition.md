# Contract — `CommandDefinition` and friends

**Mission**: `native-cli-kernel-01KR2NR7`

See [`../data-model.md`](../data-model.md) for the full record shapes
(`CommandDefinition`, `ArgumentDefinition`, `OptionDefinition`, the two enums,
`CommandRegistry`, `ParsedInput`, `ParseError`).

## `CommandDefinition` — construction contract

```php
new CommandDefinition(
    name: 'health:check',
    description: 'Run system health checks and report status.',
    arguments: [
        new ArgumentDefinition(
            name: 'check_id',
            mode: ArgumentMode::Optional,
            description: 'Restrict to a single check by id.',
        ),
    ],
    options: [
        new OptionDefinition(name: 'json', mode: OptionMode::None,
            description: 'Emit output as a JSON envelope.'),
        new OptionDefinition(name: 'timeout', shortcut: 't',
            mode: OptionMode::Required, default: 30,
            description: 'Per-check timeout in seconds.'),
    ],
    handler: [HealthCheckHandler::class, 'execute'],
);
```

## Invariants enforced at construction time

- `name` matches `/^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)*$/`.
- Argument names are unique within the command.
- Option long names AND shortcuts are unique within the command.
- At most one `ArgumentDefinition` has `isArray = true`, and if present it is the last entry.
- A required-mode argument MAY NOT follow an optional-mode argument.
- The reserved long names `help`, `verbose`, `quiet`, `no-interaction`, `version` are forbidden as user-defined options (kernel auto-injects them).
- The reserved shortcuts `h`, `v`, `q` are forbidden.
- `handler` is either:
  - a `\Closure` of arity 1 returning `int`, OR
  - a 2-element array `[ClassFqn::class, 'methodName']` where the FQN is resolvable through the container and the method is public, returning `int`, accepting one parameter typed `CliIO`.

Construction violations throw `InvalidCommandDefinitionException`.

## `CommandRegistry` — registration contract

- `register(CommandDefinition $cmd)` throws `DuplicateCommandException` if `$cmd->name` is already registered.
- `get(string $name)` returns the `CommandDefinition` or `null`. No fuzzy matching.
- `all()` returns the registered commands sorted by `name` ASCII-lexically.
- `names()` returns the sorted list of names.

## Provider integration

`HasNativeCommandsInterface::nativeCommands()` yields `CommandDefinition` instances. The `CliKernelServiceProvider::buildRegistry()` iterates every provider implementing the interface and feeds the yielded definitions into a fresh `CommandRegistry`. Provider-yield order does NOT affect `CommandRegistry::all()` ordering (which is name-sorted).

## Determinism guarantees

- `CommandRegistry::all()` and `::names()` are deterministic across processes given the same provider set.
- `CommandDefinition` is structurally immutable (`readonly` fields; the closure is bound once).
- `ParsedInput` is `readonly`.
