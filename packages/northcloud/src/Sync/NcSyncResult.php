<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

/**
 * Immutable result of a single sync cycle.
 */
final class NcSyncResult
{
    /**
     * @param array<string, int> $skipReasons
     * @param list<array<string, mixed>> $createdSamples
     * @param list<array<string, mixed>> $skippedSamples
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly bool $fetchFailed = false,
        public readonly int $fetched = 0,
        public readonly array $skipReasons = [],
        public readonly array $createdSamples = [],
        public readonly array $skippedSamples = [],
    ) {}

    public function withCreated(int $n = 1): self
    {
        return new self(
            $this->created + $n,
            $this->skipped,
            $this->failed,
            $this->fetchFailed,
            $this->fetched,
            $this->skipReasons,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    public function withSkipped(int $n = 1): self
    {
        return new self(
            $this->created,
            $this->skipped + $n,
            $this->failed,
            $this->fetchFailed,
            $this->fetched,
            $this->skipReasons,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    public function withFailed(int $n = 1): self
    {
        return new self(
            $this->created,
            $this->skipped,
            $this->failed + $n,
            $this->fetchFailed,
            $this->fetched,
            $this->skipReasons,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    public function withFetchFailed(): self
    {
        return new self(
            $this->created,
            $this->skipped,
            $this->failed,
            true,
            $this->fetched,
            $this->skipReasons,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    public function withFetched(int $fetched): self
    {
        return new self(
            $this->created,
            $this->skipped,
            $this->failed,
            $this->fetchFailed,
            $fetched,
            $this->skipReasons,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    public function withSkipReason(string $reason, int $n = 1): self
    {
        $counts = $this->skipReasons;
        $counts[$reason] = ($counts[$reason] ?? 0) + $n;

        return new self(
            $this->created,
            $this->skipped,
            $this->failed,
            $this->fetchFailed,
            $this->fetched,
            $counts,
            $this->createdSamples,
            $this->skippedSamples,
        );
    }

    /**
     * @param array<string, mixed> $sample
     */
    public function withCreatedSample(array $sample, int $limit): self
    {
        if ($limit <= 0 || count($this->createdSamples) >= $limit) {
            return $this;
        }

        $samples = $this->createdSamples;
        $samples[] = $sample;

        return new self(
            $this->created,
            $this->skipped,
            $this->failed,
            $this->fetchFailed,
            $this->fetched,
            $this->skipReasons,
            $samples,
            $this->skippedSamples,
        );
    }

    /**
     * @param array<string, mixed> $sample
     */
    public function withSkippedSample(array $sample, int $limit): self
    {
        if ($limit <= 0 || count($this->skippedSamples) >= $limit) {
            return $this;
        }

        $samples = $this->skippedSamples;
        $samples[] = $sample;

        return new self(
            $this->created,
            $this->skipped,
            $this->failed,
            $this->fetchFailed,
            $this->fetched,
            $this->skipReasons,
            $this->createdSamples,
            $samples,
        );
    }

    /**
     * @return array{
     *   created: int,
     *   skipped: int,
     *   failed: int,
     *   fetch_failed: bool,
     *   fetched: int,
     *   skip_reasons: array<string, int>,
     *   created_samples: list<array<string, mixed>>,
     *   skipped_samples: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'fetch_failed' => $this->fetchFailed,
            'fetched' => $this->fetched,
            'skip_reasons' => $this->skipReasons,
            'created_samples' => $this->createdSamples,
            'skipped_samples' => $this->skippedSamples,
        ];
    }
}
