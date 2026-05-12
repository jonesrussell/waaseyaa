<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Query;

use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\EntityStorage\BackendResolver;
use Waaseyaa\EntityStorage\Exception\UnsupportedQueryException;
use Waaseyaa\Field\FieldDefinition;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

/**
 * Validates that every indexed field's backend can satisfy a query,
 * at definition time (boot), not at query execution time.
 *
 * ## Declared-query scope decision (WP06)
 *
 * {@see EntityTypeInterface} does not yet expose a "declared queries" API
 * (that belongs to a later WP). This validator therefore uses
 * {@see FieldDefinition::isIndexed()} as the sole signal that a field has
 * declared query needs. A field is skipped unless `isIndexed() === true`.
 *
 * When an EntityType declared-queries API is added, extend `validateType()`
 * to iterate those declarations and probe `supportsQuery()` per operator.
 *
 * ## Fail-fast contract
 *
 * {@see validateAll()} throws {@see UnsupportedQueryException} on the first
 * field whose backend returns false from `supportsQuery()`. Boot fails
 * immediately. There is NO silent fallback and NO runtime retry.
 */
final class DefinitionValidator
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly BackendResolver $backendResolver,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Validate query support for all registered entity types.
     *
     * Called once at boot, after {@see \Waaseyaa\EntityStorage\Backend\BackendRegistrar::build()}
     * completes. Throws on the first unsatisfied query declaration.
     *
     * @throws UnsupportedQueryException When a backend cannot satisfy a declared query need.
     */
    public function validateAll(): void
    {
        foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
            $this->validateType($entityType);
        }
    }

    /**
     * Validate query support for a single entity type.
     *
     * @throws UnsupportedQueryException
     */
    public function validateType(EntityTypeInterface $entityType): void
    {
        $fieldDefinitions = $entityType->getFieldDefinitions();

        foreach ($fieldDefinitions as $field) {
            if (!$field instanceof FieldDefinition) {
                // Non-FieldDefinition entries (e.g. FieldDefinitionInterface stubs) are skipped.
                continue;
            }

            if (!$field->isIndexed()) {
                // No declared query need — skip.
                continue;
            }

            $this->validateField($entityType, $field);
        }
    }

    /**
     * Validate that the backend for a single indexed field supports querying.
     *
     * @throws UnsupportedQueryException
     */
    private function validateField(EntityTypeInterface $entityType, FieldDefinition $field): void
    {
        $backend = $this->backendResolver->resolve($entityType, $field);

        // Use a bare EntityQuery marker — EntityQuery is still a marker interface
        // (WP06 enriches it). The validator probes general query support for the
        // field; operator-level granularity belongs to the declared-queries API
        // introduced in a later WP.
        $probe = new class implements EntityQuery {};

        $supported = $backend->supportsQuery($field, $probe);

        if (!$supported) {
            $backendId = $backend->id();
            $fieldId = $field->getName();
            $reason = sprintf(
                'field "%s" on entity type "%s" is marked indexed but backend "%s" '
                . 'returned supportsQuery()=false. '
                . 'Route the field to a backend that supports querying, '
                . 'or remove the indexed() declaration.',
                $fieldId,
                $entityType->id(),
                $backendId,
            );

            $this->logger->debug('DefinitionValidator: query support check failed', [
                'entity_type' => $entityType->id(),
                'field'       => $fieldId,
                'backend'     => $backendId,
            ]);

            throw new UnsupportedQueryException($backendId, $fieldId, $reason);
        }

        $this->logger->debug('DefinitionValidator: query support check passed', [
            'entity_type' => $entityType->id(),
            'field'       => $field->getName(),
            'backend'     => $backend->id(),
        ]);
    }
}
