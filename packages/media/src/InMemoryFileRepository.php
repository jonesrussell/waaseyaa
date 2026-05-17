<?php

declare(strict_types=1);

namespace Waaseyaa\Media;

/**
 * In-memory implementation of the file repository.
 *
 * Stores files in a PHP array, suitable for testing and prototyping.
 * File data does not persist beyond the lifetime of the object.
 * @api
 */
final class InMemoryFileRepository implements FileRepositoryInterface
{
    /**
     * Files stored in memory, keyed by URI.
     *
     * @var array<string, File>
     */
    private array $files = [];

    public function save(File $file): File
    {
        $this->files[$file->uri] = $file;

        return $file;
    }

    public function load(string $uri): ?File
    {
        return $this->files[$uri] ?? null;
    }

    public function delete(string $uri): bool
    {
        if (isset($this->files[$uri])) {
            unset($this->files[$uri]);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function findByOwner(int $ownerId): array
    {
        $result = [];

        foreach ($this->files as $file) {
            if ($file->ownerId === $ownerId) {
                $result[] = $file;
            }
        }

        return $result;
    }
}
