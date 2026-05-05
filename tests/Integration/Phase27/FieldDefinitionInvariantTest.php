<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase27;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Field\FieldDefinitionRegistry;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Groups\GroupsServiceProvider;
use Waaseyaa\Taxonomy\TaxonomyServiceProvider;

/**
 * Manifest-level regression for the alpha.171 FieldDefinition binding invariant.
 *
 * Issue #1388: when a framework provider constructs a core FieldDefinition
 * without `targetEntityTypeId`, kernel boot explodes during entity-type
 * registration because `FieldDefinitionRegistry::registerCoreFields()` rejects
 * the bind. This sweep walks every framework provider that ships an EntityType
 * with `_fieldDefinitions` and drives those definitions through a real
 * registry, exactly reproducing the production failure mode.
 *
 * Pre-fix expectation: this test fails on the first defective provider with
 * `\InvalidArgumentException` and the documented message format. Post-fix
 * expectation: every provider's bundle entity type passes the bind invariant.
 *
 * Add new framework providers to `frameworkProvidersWithCoreFields()` whenever
 * a new package starts shipping core FieldDefinitions.
 */
#[CoversNothing]
final class FieldDefinitionInvariantTest extends TestCase
{
    /**
     * Drive every known framework provider through a fresh
     * FieldDefinitionRegistry, asserting that every core FieldDefinition binds
     * cleanly. Any defect surfaces with the same exception class and message
     * that would crash a real kernel boot.
     */
    #[Test]
    public function all_framework_provider_core_fields_satisfy_binding_invariant(): void
    {
        $registry = new FieldDefinitionRegistry();
        $providers = $this->frameworkProvidersWithCoreFields();
        self::assertNotSame([], $providers, 'Sanity: at least one framework provider must be exercised.');

        $checked = 0;
        $failures = [];

        foreach ($providers as $providerLabel => $provider) {
            $provider->register();

            foreach ($provider->getEntityTypes() as $entityType) {
                $entityTypeId = $entityType->id();
                $fields = $entityType->getFieldDefinitions();
                if ($fields === []) {
                    continue;
                }

                try {
                    $registry->registerCoreFields($entityTypeId, $fields);
                } catch (\InvalidArgumentException $e) {
                    $failures[] = sprintf(
                        '  - provider=%s entityType=%s: %s',
                        $providerLabel,
                        $entityTypeId,
                        $e->getMessage(),
                    );
                    continue;
                }

                foreach ($fields as $name => $field) {
                    self::assertNotSame(
                        '',
                        $field->getTargetEntityTypeId(),
                        sprintf(
                            '#1388 invariant: provider %s entity type "%s" field "%s" must declare a non-empty targetEntityTypeId.',
                            $providerLabel,
                            $entityTypeId,
                            $name,
                        ),
                    );
                    self::assertSame(
                        $entityTypeId,
                        $field->getTargetEntityTypeId(),
                        sprintf(
                            '#1388 invariant: provider %s entity type "%s" field "%s" must declare targetEntityTypeId === "%s"; got "%s".',
                            $providerLabel,
                            $entityTypeId,
                            $name,
                            $entityTypeId,
                            $field->getTargetEntityTypeId(),
                        ),
                    );
                    ++$checked;
                }
            }
        }

        if ($failures !== []) {
            self::fail(sprintf(
                "FieldDefinition binding invariant violated by %d framework provider call site(s) (#1388):\n%s",
                count($failures),
                implode("\n", $failures),
            ));
        }

        self::assertGreaterThan(
            0,
            $checked,
            'Sanity: at least one framework provider FieldDefinition must have been verified.',
        );
    }

    /**
     * Framework providers known to ship core FieldDefinitions on a config
     * bundle entity. When a new framework provider starts registering an
     * EntityType with `_fieldDefinitions`, add it here so the manifest sweep
     * picks it up.
     *
     * @return array<string, ServiceProvider>
     */
    private function frameworkProvidersWithCoreFields(): array
    {
        return [
            'waaseyaa/groups' => new GroupsServiceProvider(),
            'waaseyaa/taxonomy' => new TaxonomyServiceProvider(),
        ];
    }
}
