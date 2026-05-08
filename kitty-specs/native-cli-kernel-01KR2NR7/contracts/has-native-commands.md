# Contract — `HasNativeCommandsInterface`

**Mission**: `native-cli-kernel-01KR2NR7`

```php
namespace Waaseyaa\Foundation\ServiceProvider\Capability;

interface HasNativeCommandsInterface
{
    /** @return iterable<\Waaseyaa\Cli\CommandDefinition> */
    public function nativeCommands(): iterable;
}
```

## Layer placement

- File location: `packages/foundation/src/ServiceProvider/Capability/HasNativeCommandsInterface.php`.
- Layer: 0 (Foundation).
- The file does NOT `use` any class from Layer 6. The `\Waaseyaa\Cli\CommandDefinition` reference appears only as an FQN in the docblock; the interface body is `iterable` only.
- Verified by `bin/check-package-layers` post-merge.

## Contract for implementors

- A `ServiceProvider` MAY implement this interface. Implementations MUST return an iterable of `CommandDefinition` instances. Yielding non-CommandDefinition values is a programming error caught by the contract test.
- `nativeCommands()` is called exactly once per process boot, during manifest compilation. Implementations SHOULD treat the method as pure — no side effects, idempotent on repeated invocation.
- Multiple providers MAY register commands; ordering across providers does not affect public behaviour (the registry sorts by name).

## Discovery

`PackageManifestCompiler` scans every registered provider for capability interfaces. `HasNativeCommandsInterface` is added to its capability list alongside `HasMiddlewareInterface`, `HasEntityTypesInterface`, etc. — no schema migration; no new manifest field.

## Migration from `HasCommandsInterface`

`HasCommandsInterface` is removed in WP-12. Every provider previously implementing it migrates to `HasNativeCommandsInterface`. The two interfaces never coexist on `main`; the dual-boot adapter introduced in WP-05 reads BOTH only inside `bin/waaseyaa` for the duration of WPs 06–11 and is itself deleted in WP-12.

## Contract test

`packages/foundation/tests/Unit/ServiceProvider/Capability/HasNativeCommandsInterfaceTest.php`:
- Asserts every `CommandDefinition` yielded by a provider passes its own construction invariants.
- Asserts the interface declaration has zero `use Symfony\…` statements (parse the file source).
- Asserts the interface contract method signature returns `iterable`.

## Layer-check assertion

`bin/check-package-layers` confirms `packages/foundation/composer.json` does NOT depend on `waaseyaa/cli` (Layer 6). The interface uses an FQN reference that does not introduce a Composer dep.
