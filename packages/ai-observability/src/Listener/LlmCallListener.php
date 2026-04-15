<?php

declare(strict_types=1);

namespace Waaseyaa\AI\Observability\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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

    public function onLlmCallCompleted(object $event): void
    {
        $traceUuid = $this->readProp($event, 'traceUuid');
        if (!is_string($traceUuid)) {
            return;
        }
        $handle = $this->context->get($traceUuid);
        if ($handle === null) {
            $this->logger->debug('LlmCallListener: no active trace for uuid', ['uuid' => $traceUuid]);

            return;
        }
        $model = (string) ($this->readProp($event, 'model') ?? 'unknown');
        $inputTokens = (int) ($this->readProp($event, 'inputTokens') ?? 0);
        $outputTokens = (int) ($this->readProp($event, 'outputTokens') ?? 0);
        $cachedTokens = (int) ($this->readProp($event, 'cachedTokens') ?? 0);

        $this->accountant->record($handle, $model, $inputTokens, $outputTokens, $cachedTokens);
    }

    private function readProp(object $obj, string $name): mixed
    {
        if (!property_exists($obj, $name)) {
            return null;
        }

        /** @phpstan-ignore property.dynamicName */
        return $obj->{$name};
    }
}
