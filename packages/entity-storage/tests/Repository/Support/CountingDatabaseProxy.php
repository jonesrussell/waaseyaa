<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Repository\Support;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DeleteInterface;
use Waaseyaa\Database\InsertInterface;
use Waaseyaa\Database\SchemaInterface;
use Waaseyaa\Database\SelectInterface;
use Waaseyaa\Database\TransactionInterface;
use Waaseyaa\Database\UpdateInterface;

/**
 * Test helper: counting proxy in front of a real DatabaseInterface.
 *
 * Used by `EntityRepositoryFindTranslationsTest` to assert the WP10 contract
 * that `EntityRepository::findTranslations()` issues exactly one raw SQL
 * query per call (NFR-005). Tracks raw `query()` calls and `select()` builder
 * invocations independently so a single "no N+1" assertion can hold across
 * both backends.
 */
final class CountingDatabaseProxy implements DatabaseInterface
{
    public int $queryCount = 0;
    public int $selectCount = 0;
    public int $insertCount = 0;
    public int $updateCount = 0;
    public int $deleteCount = 0;

    public function __construct(
        private readonly DatabaseInterface $inner,
    ) {}

    public function resetCounters(): void
    {
        $this->queryCount = 0;
        $this->selectCount = 0;
        $this->insertCount = 0;
        $this->updateCount = 0;
        $this->deleteCount = 0;
    }

    public function select(string $table, string $alias = ''): SelectInterface
    {
        ++$this->selectCount;
        return $this->inner->select($table, $alias);
    }

    public function insert(string $table): InsertInterface
    {
        ++$this->insertCount;
        return $this->inner->insert($table);
    }

    public function update(string $table): UpdateInterface
    {
        ++$this->updateCount;
        return $this->inner->update($table);
    }

    public function delete(string $table): DeleteInterface
    {
        ++$this->deleteCount;
        return $this->inner->delete($table);
    }

    public function schema(): SchemaInterface
    {
        return $this->inner->schema();
    }

    public function transaction(string $name = ''): TransactionInterface
    {
        return $this->inner->transaction($name);
    }

    public function query(string $sql, array $args = []): \Traversable
    {
        ++$this->queryCount;
        return $this->inner->query($sql, $args);
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->inner->quoteIdentifier($identifier);
    }
}
