<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

/**
 * Immutable result of a single sync cycle.
 */
final class NcSyncResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly bool $fetchFailed = false,
    ) {}

    public function withCreated(int $n = 1): self
    {
        return new self($this->created + $n, $this->skipped, $this->failed, $this->fetchFailed);
    }

    public function withSkipped(int $n = 1): self
    {
        return new self($this->created, $this->skipped + $n, $this->failed, $this->fetchFailed);
    }

    public function withFailed(int $n = 1): self
    {
        return new self($this->created, $this->skipped, $this->failed + $n, $this->fetchFailed);
    }

    public function withFetchFailed(): self
    {
        return new self($this->created, $this->skipped, $this->failed, true);
    }

    /** @return array{created: int, skipped: int, failed: int, fetch_failed: bool} */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'fetch_failed' => $this->fetchFailed,
        ];
    }
}
