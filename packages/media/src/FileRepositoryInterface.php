<?php

declare(strict_types=1);

namespace Waaseyaa\Media;

/**
 * Interface for file repository implementations.
 *
 * File repositories manage the persistence of File value objects.
 * They provide CRUD operations for files keyed by their URI.
 * @api
 */
interface FileRepositoryInterface
{
    /**
     * Saves a file to the repository.
     *
     * If a file with the same URI already exists, it is replaced.
     *
     * @param File $file The file to save.
     * @return File The saved file.
     */
    public function save(File $file): File;

    /**
     * Loads a file by its URI.
     *
     * @param string $uri The file URI.
     * @return File|null The file, or null if not found.
     */
    public function load(string $uri): ?File;

    /**
     * Deletes a file by its URI.
     *
     * @param string $uri The file URI.
     * @return bool TRUE if the file was deleted, FALSE if it was not found.
     */
    public function delete(string $uri): bool;

    /**
     * Finds all files belonging to a specific owner.
     *
     * @param int $ownerId The owner user ID.
     * @return File[] An array of files belonging to the owner.
     */
    public function findByOwner(int $ownerId): array;
}
