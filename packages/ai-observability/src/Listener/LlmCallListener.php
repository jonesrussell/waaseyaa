<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Waaseyaa\AI\Agent\Event\LlmCallCompleted;
use Waaseyaa\AI\Observability\Cost\TokenAccountant;
use Waaseyaa\AI\Observability\TraceContext;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\NullLogger;

final class LlmCallListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TraceContext $context,
        private readonly TokenAccountant $accountant,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'Waaseyaa\\AI\\Agent\\Event\\LlmCallCompleted' => 'onLlmCallCompleted',
        ];
    }

    public function onLlmCallCompleted(LlmCallCompleted $event): void
    {
        $handle = $this->context->get($event->traceUuid);
        if ($handle === null) {
            $this->logger->debug('LlmCallListener: no active trace for uuid', ['uuid' => $event->traceUuid]);

            return;
        }

        $this->accountant->record(
            $handle,
            $event->model,
            $event->inputTokens,
            $event->outputTokens,
            $event->cachedTokens,
        );
    }
}
