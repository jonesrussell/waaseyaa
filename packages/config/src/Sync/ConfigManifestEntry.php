<?php

declare(strict_types=1);

namespace Waaseyaa\Config\Sync;

/**
 * Manifest record derived from a single sync-store file.
 *
 * Used by `ConfigStatusReporter` and `ConfigDiffer` for set-diff computations
 * without round-trip-parsing every file. Carries the cheap-to-compute
 * identifiers (ref, uuid, content hash, mtime) so callers can fast-skip
 * unchanged files.
 *
 * @see \Waaseyaa\Config\Sync\ConfigSyncFile
 */
final readonly class ConfigManifestEntry
{
    /**
     * @param non-empty-string $ref         `<entity_type>.<entity_id>` canonical reference
     * @param non-empty-string $entityType
     * @param non-empty-string $entityId
     * @param non-empty-string $uuid
     * @param non-empty-string $path        absolute filesystem path to the YAML file
     * @param non-empty-string $contentHash SHA-256 of canonical YAML content
     * @param int              $mtime       Unix mtime of the file when the entry was recorded
     */
    public function __construct(
        public string $ref,
        public string $entityType,
        public string $entityId,
        public string $uuid,
        public string $path,
        public string $contentHash,
        public int $mtime,
    ) {}

    /**
     * Build a manifest entry directly from a {@see ConfigSyncFile} plus
     * filesystem metadata.
     */
    public static function fromSyncFile(ConfigSyncFile $file, string $path, int $mtime): self
    {
        return new self(
            ref: $file->ref(),
            entityType: $file->entityType,
            entityId: $file->entityId,
            uuid: $file->uuid,
            path: $path,
            contentHash: $file->contentHash(),
            mtime: $mtime,
        );
    }
}
