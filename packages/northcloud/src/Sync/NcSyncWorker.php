<?php

declare(strict_types=1);

namespace Waaseyaa\NorthCloud\Sync;

/**
 * Long-running daemon that invokes NcSyncService on a fixed interval.
 *
 * Writes a JSON status file after each cycle. Intended to be supervised (systemd,
 * supervisord, docker restart policy) — the loop itself has no daemonization logic.
 */
final class NcSyncWorker
{
    private bool $running = true;
    private int $cycleCount = 0;

    /**
     * @param int $maxCycles 0 or negative = unlimited
     * @param int $statusSampleLimit When > 0, each cycle records up to this many created/skipped hit samples
     *                                in the status JSON (for admin dashboards). Set 0 to omit samples.
     */
    public function __construct(
        private readonly NcSyncService $syncService,
        private readonly string $statusPath,
        private readonly int $intervalSeconds = 1800,
        private readonly int $maxCycles = 0,
        private readonly int $limit = 20,
        private readonly int $statusSampleLimit = 8,
    ) {}

    public function run(): void
    {
        while ($this->running && ($this->maxCycles <= 0 || $this->cycleCount < $this->maxCycles)) {
            try {
                $sampleLimit = max(0, $this->statusSampleLimit);
                $result = $this->syncService->sync(
                    limit: $this->limit,
                    since: null,
                    dryRun: false,
                    explain: $sampleLimit > 0,
                    sampleLimit: $sampleLimit,
                );
            } catch (\Throwable $e) {
                $result = (new NcSyncResult())->withFetchFailed();
                fprintf(
                    STDERR,
                    "[%s] Sync exception (%s): %s\n%s\n",
                    date('Y-m-d H:i:s'),
                    $e::class,
                    $e->getMessage(),
                    $e->getTraceAsString(),
                );
            }
            ++$this->cycleCount;

            $this->writeStatus($result);
            $this->log($result);

            if (!$this->running || ($this->maxCycles > 0 && $this->cycleCount >= $this->maxCycles)) {
                break;
            }

            $this->sleep();
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function writeStatus(NcSyncResult $result): void
    {
        try {
            $payload = [
                'last_sync' => date('c'),
                'created' => $result->created,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'fetch_failed' => $result->fetchFailed,
                'fetched' => $result->fetched,
                'cycles' => $this->cycleCount,
            ];

            if ($result->skipReasons !== []) {
                $payload['skip_reasons'] = $result->skipReasons;
            }
            if ($result->skippedSamples !== []) {
                $payload['skipped_samples'] = $result->skippedSamples;
            }
            if ($result->createdSamples !== []) {
                $payload['created_samples'] = $result->createdSamples;
            }

            $data = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException $e) {
            fprintf(STDERR, "[%s] WARNING: failed to encode status JSON: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
            return;
        }

        $tmp = $this->statusPath . '.tmp';
        if (file_put_contents($tmp, $data) === false) {
            fprintf(STDERR, "[%s] WARNING: failed to write status file %s\n", date('Y-m-d H:i:s'), $tmp);
            return;
        }
        if (!rename($tmp, $this->statusPath)) {
            fprintf(STDERR, "[%s] WARNING: failed to rename status file %s -> %s\n", date('Y-m-d H:i:s'), $tmp, $this->statusPath);
        }
    }

    private function log(NcSyncResult $result): void
    {
        $ts = date('Y-m-d H:i:s');
        if ($result->fetchFailed) {
            fprintf(STDERR, "[%s] Sync FAILED: could not reach NorthCloud\n", $ts);
            return;
        }
        $cap = $this->maxCycles <= 0 ? '∞' : (string) $this->maxCycles;
        fprintf(
            STDOUT,
            "[%s] Sync: fetched=%d created=%d skipped=%d failed=%d (cycle %d/%s)\n",
            $ts,
            $result->fetched,
            $result->created,
            $result->skipped,
            $result->failed,
            $this->cycleCount,
            $cap,
        );
    }

    private function sleep(): void
    {
        for ($i = 0; $i < $this->intervalSeconds && $this->running; $i++) {
            sleep(1);
        }
    }
}
