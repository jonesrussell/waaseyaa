<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\PluginFixtures;

use Waaseyaa\Migration\Plugin\SourcePluginInterface;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\SourceId;

/**
 * In-memory source plugin used by Runner unit tests.
 *
 * Yields a fixed list of {@see SourceRecord}s. Configurable failure injection:
 *   - `$throwAtIndex` — when set, throws `\RuntimeException` mid-iteration on
 *     yielding the Nth record (R2 coverage).
 *   - `$throwInCountWith` — when non-null, `count()` re-throws this throwable.
 */
final class InMemorySource implements SourcePluginInterface
{
    /**
     * @param string $id Source plugin id surface (snake_case).
     * @param list<SourceRecord> $records Fixture records.
     * @param array<string, string> $keys Source-record field name -> SourceId key entry (e.g. ['id' => 'wp_id']).
     * @param string $sourceType SourceId::$sourceType to attach to every emitted record.
     * @param int|null $throwAtIndex Optional index at which `records()` should raise mid-iteration.
     * @param \Throwable|null $throwInCountWith Optional exception `count()` should re-throw.
     * @param bool $reportCount When false, `count()` returns null (simulates unknown-up-front sources).
     */
    public function __construct(
        private readonly string $id,
        private readonly array $records,
        private readonly array $keys = ['id' => 'id'],
        private readonly string $sourceType = 'in_memory',
        private readonly ?int $throwAtIndex = null,
        private readonly ?\Throwable $throwInCountWith = null,
        private readonly bool $reportCount = true,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function stability(): string
    {
        return 'stable';
    }

    public function records(): iterable
    {
        $i = 0;
        foreach ($this->records as $record) {
            if ($this->throwAtIndex !== null && $i === $this->throwAtIndex) {
                throw new \RuntimeException(\sprintf(
                    'InMemorySource: synthetic mid-iteration failure at index %d.',
                    $i,
                ));
            }
            yield $record;
            $i++;
        }
    }

    public function sourceIdFor(SourceRecord $record): SourceId
    {
        $values = [];
        foreach ($this->keys as $sourceField => $keyName) {
            $values[$keyName] = $record->field($sourceField);
        }
        return new SourceId($this->sourceType, $values);
    }

    public function count(): ?int
    {
        if ($this->throwInCountWith !== null) {
            throw $this->throwInCountWith;
        }
        return $this->reportCount ? \count($this->records) : null;
    }
}
