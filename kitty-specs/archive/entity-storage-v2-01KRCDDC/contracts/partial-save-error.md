# Contract — Partial-Save Error Model

**Owning WP**: WP04.
**Source**: spec §3.9, §6.5; ADR 010.
**Stable surface**: yes (charter §5.3).

---

## Exception class

```php
namespace Waaseyaa\EntityStorage\Exception;

use Waaseyaa\Entity\EntityInterface;

/**
 * @api — stable surface; charter §5.3.
 *
 * Raised by {@see EntityStorageCoordinator::save()} / ::delete() when one or
 * more registered backends fail mid-fan-out. The framework does NOT attempt
 * silent rollback across backends — true cross-backend atomicity is not
 * achievable for arbitrary backends (vector stores, remote services).
 */
final class PartialSaveException extends \RuntimeException
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly \Throwable $causedBy,
        /** @var list<string> Backend ids that committed before the failure. */
        public readonly array $committedBackends,
        /** @var list<string> Backend ids that did NOT commit. */
        public readonly array $uncommittedBackends,
        public readonly string $errorCode = 'PARTIAL_SAVE',
    ) {
        parent::__construct(
            sprintf(
                'Partial save for entity type "%s" id "%s": %d committed, %d uncommitted. Caused by: %s',
                $entity->getEntityTypeId(),
                (string) $entity->id(),
                count($committedBackends),
                count($uncommittedBackends),
                $causedBy->getMessage(),
            ),
            previous: $causedBy,
        );
    }
}
```

> The property is named `$errorCode`, not `$code`. PHP refuses to redeclare the inherited `\Exception::$code` (int, non-readonly) as a readonly string in any subclass. Consumers must read `$exception->errorCode`, not `$exception->code` (which still resolves to the inherited int).

## Coordinator contract (recap of spec §6.5)

When backend fan-out partially fails:

1. The failing backend's write/delete throws (any `\Throwable`).
2. The coordinator catches, builds `committedBackends` from backends that completed before the throw, and `uncommittedBackends` from those that did not run.
3. The coordinator throws `PartialSaveException` carrying both lists plus the original `$causedBy`.
4. `AfterSaveEvent` / `AfterDeleteEvent` does NOT fire.
5. A log line on `entity.lifecycle` fires with `outcome=partial_save`.

## Recovery contract

Recovery is OPERATOR concern. The framework provides the diagnostic; rollback strategy is per-app:

- Apps MAY catch `PartialSaveException` and run a per-backend compensating delete on `committedBackends`.
- Apps MAY surface the entity to a manual reconciliation queue.
- Apps MUST NOT assume framework-side compensation occurred.

Document patterns in WP12 `entity-system.md` update.

## Test surface

- Coordinator integration tests with deliberately-failing backend fakes: verify committed/uncommitted partitioning, that AfterSave/AfterDelete do not fire, and that the log line fires with the correct `outcome` field.
- WP11 validation: integration test in Minoo that injects a failing alternate backend and verifies the application-level recovery path.
