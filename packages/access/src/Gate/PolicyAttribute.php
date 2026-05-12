<?php

declare(strict_types=1);

namespace Waaseyaa\Access\Gate;

/**
 * Marks a class as a Gate policy for a specific entity type.
 *
 * Place this attribute on policy classes so the Gate can resolve them
 * by convention. The entityType property maps the policy to the entity
 * type it governs.
 *
 * If `operations` includes GateInterface::VIEW_REVISION ('view_revision'),
 * the annotated class MUST declare a viewRevision() method with the correct
 * signature. Missing that method causes a boot-time failure via validate().
 *
 * Example (basic):
 *
 *     #[PolicyAttribute(entityType: 'node')]
 *     final class NodePolicy { ... }
 *
 * Example (with view_revision):
 *
 *     #[PolicyAttribute(entityType: 'node', operations: ['view_revision'])]
 *     final class NodePolicy {
 *         public function viewRevision(
 *             EntityInterface $entity,
 *             AccountInterface $account,
 *             RevisionMetadata $revision,
 *         ): AccessResult { ... }
 *     }
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PolicyAttribute
{
    /** @var string[] */
    public readonly array $entityTypes;

    /** @var string[] */
    public readonly array $operations;

    /**
     * @param string|string[] $entityType  One entity type ID or an array of them.
     * @param string[]        $operations  Optional list of extra op strings this policy handles
     *                                     (e.g. ['view_revision']). Declaring an op here enables
     *                                     boot-time validation that the required method exists.
     */
    public function __construct(
        string|array $entityType,
        array $operations = [],
    ) {
        $this->entityTypes = is_array($entityType) ? $entityType : [$entityType];
        $this->operations = $operations;
    }

    /**
     * Validate that a policy class satisfies the contracts implied by its declared operations.
     *
     * Call this during kernel boot (attribute scanning) for each discovered policy class.
     * Throws \LogicException if a required method is missing — this is intentional boot failure,
     * not a recoverable runtime error.
     *
     * Currently enforced:
     * - 'view_revision' requires viewRevision(EntityInterface, AccountInterface, RevisionMetadata): AccessResult
     *
     * @param class-string $policyClass The FQCN of the policy class being validated.
     *
     * @throws \LogicException if a declared op is missing its implementation method.
     *
     * @api
     */
    public function validate(string $policyClass): void
    {
        if (in_array(GateInterface::VIEW_REVISION, $this->operations, strict: true)) {
            if (!method_exists($policyClass, 'viewRevision')) {
                throw new \LogicException(sprintf(
                    'Policy class "%s" declares operation "%s" via #[PolicyAttribute] but is missing'
                    . ' the required method viewRevision(EntityInterface $entity,'
                    . ' AccountInterface $account, RevisionMetadata $revision): AccessResult.'
                    . ' Add the method or remove "%s" from the operations list.',
                    $policyClass,
                    GateInterface::VIEW_REVISION,
                    GateInterface::VIEW_REVISION,
                ));
            }
        }
    }
}
