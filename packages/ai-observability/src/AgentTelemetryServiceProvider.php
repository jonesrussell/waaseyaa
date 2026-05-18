<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability;

use Waaseyaa\AI\Agent\Repository\AgentRunRepository;
use Waaseyaa\AI\Observability\Listener\AgentRunTelemetryListener;
use Waaseyaa\AI\Observability\Pricing\ModelPriceTable;
use Waaseyaa\AI\Observability\Recorder\AgentRunMetricsRecorderInterface;
use Waaseyaa\AI\Observability\Recorder\AgentTelescopeRecorderInterface;
use Waaseyaa\AI\Observability\Recorder\NullAgentRunMetricsRecorder;
use Waaseyaa\AI\Observability\Recorder\NullAgentTelescopeRecorder;
use Waaseyaa\Foundation\Event\EventDispatcherInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * Wires the AgentRun telemetry surface for FR-029:
 *
 * - `ModelPriceTable` (singleton, immutable).
 * - `AgentTelescopeRecorderInterface` default-bound to {@see NullAgentTelescopeRecorder};
 *   applications wishing to forward records to Telescope rebind to a
 *   concrete adapter (kept at L6 to preserve the L5→L6 layering rule).
 * - `AgentRunMetricsRecorderInterface` default-bound to {@see NullAgentRunMetricsRecorder};
 *   applications rebind to a Prometheus-backed adapter.
 * - `AgentRunTelemetryListener` registered with the EventDispatcher
 *   inside `boot()` so the AgentRun lifecycle events fan out to telemetry.
 *
 * @api
 */
final class AgentTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(ModelPriceTable::class, fn(): ModelPriceTable => new ModelPriceTable());

        $this->singleton(
            AgentTelescopeRecorderInterface::class,
            fn(): AgentTelescopeRecorderInterface => new NullAgentTelescopeRecorder(),
        );

        $this->singleton(
            AgentRunMetricsRecorderInterface::class,
            fn(): AgentRunMetricsRecorderInterface => new NullAgentRunMetricsRecorder(),
        );

        $this->singleton(AgentRunTelemetryListener::class, function (): AgentRunTelemetryListener {
            $logger = null;
            try {
                $logger = $this->resolve(LoggerInterface::class);
            } catch (\Throwable) {
                // Fall through to NullLogger default.
            }

            return new AgentRunTelemetryListener(
                telescope: $this->resolve(AgentTelescopeRecorderInterface::class),
                runRepository: $this->resolve(AgentRunRepository::class),
                priceTable: $this->resolve(ModelPriceTable::class),
                metrics: $this->resolve(AgentRunMetricsRecorderInterface::class),
                logger: $logger,
            );
        });
    }

    public function boot(): void
    {
        try {
            $dispatcher = $this->resolve(EventDispatcherInterface::class);
            $listener = $this->resolve(AgentRunTelemetryListener::class);
            $dispatcher->addSubscriber($listener);
        } catch (\Throwable) {
            // Best-effort wiring: a missing EventDispatcher / AgentRunRepository
            // binding (e.g. minimal kernel without ai-agent) must not break boot.
        }
    }
}
