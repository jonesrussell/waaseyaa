<?php

declare(strict_types=1);

namespace Waaseyaa\Entity;

/**
 * Immutable value object carrying per-revision metadata.
 *
 * Stored in the `<entity>__revision` table as `revision_created_at`,
 * `revision_author`, and `revision_log`. All three fields are nullable
 * because revision metadata may not be present on historical rows that
 * were migrated from a non-revision-aware schema.
 *
 * @api
 */
final readonly class RevisionMetadata
{
    /**
     * @param \DateTimeImmutable $revisionCreatedAt  When this revision was created (ISO-8601 in storage).
     * @param int|null           $revisionAuthor     UID of the account that created the revision.
     *                                               Soft FK only — no ON DELETE cascade — so revision
     *                                               history survives user deletion.
     * @param string|null        $revisionLog        Optional human-readable log message for this revision.
     */
    public function __construct(
        public readonly \DateTimeImmutable $revisionCreatedAt,
        public readonly ?int $revisionAuthor = null,
        public readonly ?string $revisionLog = null,
    ) {}
}
